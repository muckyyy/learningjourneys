<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Journey;
use App\Models\JourneyAttempt;
use App\Models\JourneyStepResponse;
use App\Models\JourneyPromptLog;
use App\Models\TokenTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:reports.view');
    }

    /**
     * Display reports dashboard.
     */
    public function index()
    {
        $user = Auth::user();
        
        if ($user->role !== 'administrator') {
            abort(403);
        }

        $stats = $this->getAdminStats();

        return view('reports.index', compact('stats'));
    }

    /**
     * Display journey reports.
     */
    public function journeys(Request $request)
    {
        $query = Journey::query()->with(['collection', 'creator']);

        if ($request->filled('difficulty')) {
            $query->where('difficulty_level', $request->difficulty);
        }

        if ($request->filled('status')) {
            $query->where('is_published', $request->status === 'published');
        }

        $sort = $request->get('sort', 'most_attempted');

        $query
            ->withCount([
                'attempts',
                'attempts as completed_attempts_count' => function ($q) {
                    $q->where('status', 'completed');
                },
            ])
            ->withAvg([
                'attempts as avg_rating' => function ($q) {
                    $q->whereNotNull('rating');
                },
            ], 'rating')
            ->addSelect([
                'avg_score' => JourneyStepResponse::query()
                    ->join('journey_attempts as ja', 'ja.id', '=', 'journey_step_responses.journey_attempt_id')
                    ->selectRaw('AVG(journey_step_responses.step_rate)')
                    ->whereColumn('ja.journey_id', 'journeys.id')
                    ->whereNotNull('journey_step_responses.step_rate'),
                'avg_completion_seconds' => JourneyAttempt::query()
                    ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, started_at, completed_at))')
                    ->whereColumn('journey_id', 'journeys.id')
                    ->where('status', 'completed')
                    ->whereNotNull('started_at')
                    ->whereNotNull('completed_at'),
                'token_spend_total' => TokenTransaction::query()
                    ->selectRaw('COALESCE(SUM(amount), 0)')
                    ->whereColumn('journey_id', 'journeys.id')
                    ->where('type', TokenTransaction::TYPE_DEBIT),
                'prompt_tokens_total' => JourneyPromptLog::query()
                    ->join('journey_attempts as ja', 'ja.id', '=', 'journey_prompt_logs.journey_attempt_id')
                    ->selectRaw('COALESCE(SUM(journey_prompt_logs.tokens_used), 0)')
                    ->whereColumn('ja.journey_id', 'journeys.id'),
            ]);

        if ($sort === 'top_rated') {
            $query->orderByDesc('avg_score');
        } elseif ($sort === 'most_tokens') {
            $query->orderByDesc('token_spend_total');
        } elseif ($sort === 'fastest_completion') {
            $query->orderBy('avg_completion_seconds');
        } else {
            $query->orderByDesc('attempts_count');
        }

        $journeys = $query->paginate(15)->withQueryString();

        $journeyIds = (clone $query)->toBase()->pluck('id');

        $attemptsQuery = JourneyAttempt::query()->whereIn('journey_id', $journeyIds);
        $totalAttempts = (clone $attemptsQuery)->count();
        $completedAttempts = (clone $attemptsQuery)->where('status', 'completed')->count();
        $avgScore = (float) JourneyStepResponse::query()
            ->join('journey_attempts as ja', 'ja.id', '=', 'journey_step_responses.journey_attempt_id')
            ->whereIn('ja.journey_id', $journeyIds)
            ->whereNotNull('journey_step_responses.step_rate')
            ->avg('journey_step_responses.step_rate');
        $avgCompletionSeconds = (float) ((clone $attemptsQuery)
            ->where('status', 'completed')
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, started_at, completed_at)) as seconds')
            ->value('seconds') ?? 0);

        $tokenSpendTotal = (int) TokenTransaction::query()
            ->whereIn('journey_id', $journeyIds)
            ->where('type', TokenTransaction::TYPE_DEBIT)
            ->sum('amount');

        $promptTokensTotal = (int) JourneyPromptLog::query()
            ->join('journey_attempts as ja', 'ja.id', '=', 'journey_prompt_logs.journey_attempt_id')
            ->whereIn('ja.journey_id', $journeyIds)
            ->sum('journey_prompt_logs.tokens_used');

        $topRatedJourneys = Journey::query()
            ->whereIn('id', $journeyIds)
            ->withCount('attempts')
            ->addSelect([
                'avg_score' => JourneyStepResponse::query()
                    ->join('journey_attempts as ja', 'ja.id', '=', 'journey_step_responses.journey_attempt_id')
                    ->selectRaw('AVG(journey_step_responses.step_rate)')
                    ->whereColumn('ja.journey_id', 'journeys.id')
                    ->whereNotNull('journey_step_responses.step_rate'),
            ])
            ->having('attempts_count', '>=', 3)
            ->orderByDesc('avg_score')
            ->limit(5)
            ->get();

        $summary = [
            'total_journeys' => $journeyIds->count(),
            'total_attempts' => $totalAttempts,
            'completed_attempts' => $completedAttempts,
            'completion_rate' => $totalAttempts > 0 ? round(($completedAttempts / $totalAttempts) * 100, 2) : 0,
            'avg_score' => round($avgScore, 2),
            'avg_completion_seconds' => round($avgCompletionSeconds, 0),
            'token_spend_total' => $tokenSpendTotal,
            'prompt_tokens_total' => $promptTokensTotal,
            'top_rated' => $topRatedJourneys,
        ];

        return view('reports.journeys', compact('journeys', 'summary', 'sort'));
    }

    /**
     * Display an individual journey report.
     */
    public function journeyDetail(Request $request, Journey $journey)
    {
        $journey->load(['collection', 'creator']);

        $attemptsBase = JourneyAttempt::query()->where('journey_id', $journey->id);

        $totalAttempts = (clone $attemptsBase)->count();
        $completedAttempts = (clone $attemptsBase)->where('status', 'completed')->count();
        $avgScore = (float) JourneyStepResponse::query()
            ->join('journey_attempts as ja', 'ja.id', '=', 'journey_step_responses.journey_attempt_id')
            ->where('ja.journey_id', $journey->id)
            ->whereNotNull('journey_step_responses.step_rate')
            ->avg('journey_step_responses.step_rate');
        $avgRating = (float) ((clone $attemptsBase)->whereNotNull('rating')->avg('rating') ?? 0);
        $feedbackCount = (clone $attemptsBase)
            ->whereNotNull('feedback')
            ->where('feedback', '!=', '')
            ->count();
        $avgFeedbackRating = (float) ((clone $attemptsBase)->whereNotNull('rating')->avg('rating') ?? 0);
        $avgCompletionSeconds = (float) ((clone $attemptsBase)
            ->where('status', 'completed')
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, started_at, completed_at)) as seconds')
            ->value('seconds') ?? 0);

        $tokenSpendTotal = (int) TokenTransaction::query()
            ->where('journey_id', $journey->id)
            ->where('type', TokenTransaction::TYPE_DEBIT)
            ->sum('amount');

        $promptTokensTotal = (int) JourneyPromptLog::query()
            ->join('journey_attempts as ja', 'ja.id', '=', 'journey_prompt_logs.journey_attempt_id')
            ->where('ja.journey_id', $journey->id)
            ->sum('journey_prompt_logs.tokens_used');

        $statusBreakdown = (clone $attemptsBase)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $topLearners = JourneyStepResponse::query()
            ->join('journey_attempts as ja', 'ja.id', '=', 'journey_step_responses.journey_attempt_id')
            ->select('ja.user_id', DB::raw('AVG(journey_step_responses.step_rate) as avg_score'), DB::raw('COUNT(journey_step_responses.id) as attempts_count'))
            ->where('ja.journey_id', $journey->id)
            ->whereNotNull('journey_step_responses.step_rate')
            ->groupBy('ja.user_id')
            ->havingRaw('COUNT(journey_step_responses.id) >= 3')
            ->orderByDesc('avg_score')
            ->limit(10)
            ->get();

        $topLearnerUsers = User::query()
            ->select('id', 'name', 'email')
            ->whereIn('id', $topLearners->pluck('user_id')->all())
            ->get()
            ->keyBy('id');

        $topLearners = $topLearners->map(function ($row) use ($topLearnerUsers) {
            $row->user = $topLearnerUsers->get($row->user_id);

            return $row;
        });

        $recentAttempts = JourneyAttempt::query()
            ->with('user:id,name,email')
            ->where('journey_id', $journey->id)
            ->orderByDesc('created_at')
            ->paginate(20, ['*'], 'attempts_page')
            ->withQueryString();

        $feedbackQuery = JourneyAttempt::query()
            ->with('user:id,name,email')
            ->where('journey_id', $journey->id)
            ->whereNotNull('feedback')
            ->where('feedback', '!=', '');

        if ($request->filled('feedback_rating')) {
            $feedbackRatingFilter = $request->get('feedback_rating');

            if ($feedbackRatingFilter === 'low') {
                $feedbackQuery->whereNotNull('rating')->where('rating', '<=', 2);
            } elseif ($feedbackRatingFilter === 'mid') {
                $feedbackQuery->whereNotNull('rating')->whereBetween('rating', [2.01, 3.99]);
            } elseif ($feedbackRatingFilter === 'high') {
                $feedbackQuery->whereNotNull('rating')->where('rating', '>=', 4);
            } elseif ($feedbackRatingFilter === 'unrated') {
                $feedbackQuery->whereNull('rating');
            }
        }

        if ($request->filled('feedback_search')) {
            $feedbackQuery->where('feedback', 'like', '%' . trim($request->get('feedback_search')) . '%');
        }

        $feedbackEntries = $feedbackQuery
            ->orderByDesc('updated_at')
            ->paginate(12, ['*'], 'feedback_page')
            ->withQueryString();

        $weeklyTrend = JourneyAttempt::query()
            ->selectRaw('YEARWEEK(created_at, 1) as year_week')
            ->selectRaw('COUNT(*) as attempts_total')
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_total")
            ->where('journey_id', $journey->id)
            ->where('created_at', '>=', now()->subWeeks(12))
            ->groupBy('year_week')
            ->orderBy('year_week')
            ->get();

        $stats = [
            'total_attempts' => $totalAttempts,
            'completed_attempts' => $completedAttempts,
            'completion_rate' => $totalAttempts > 0 ? round(($completedAttempts / $totalAttempts) * 100, 2) : 0,
            'avg_score' => round($avgScore, 2),
            'avg_rating' => round($avgRating, 2),
            'avg_feedback_rating' => round($avgFeedbackRating, 2),
            'feedback_count' => $feedbackCount,
            'avg_completion_seconds' => round($avgCompletionSeconds, 0),
            'token_spend_total' => $tokenSpendTotal,
            'prompt_tokens_total' => $promptTokensTotal,
            'status_breakdown' => $statusBreakdown,
        ];

        return view('reports.journey-detail', [
            'journey' => $journey,
            'stats' => $stats,
            'topLearners' => $topLearners,
            'recentAttempts' => $recentAttempts,
            'feedbackEntries' => $feedbackEntries,
            'weeklyTrend' => $weeklyTrend,
        ]);
    }

    /**
     * Display user reports.
     */
    public function users(Request $request)
    {
        $query = User::query();

        if ($request->filled('role')) {
            $query->withRole($request->role);
        }

        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->active();
            }
        }

        $users = $query->withCount([
            'journeyAttempts',
            'journeyAttempts as completed_attempts_count' => function($q) {
                $q->where('status', 'completed');
            }
        ])->paginate(15);

        return view('reports.users', compact('users'));
    }

    /**
     * Get administrator statistics.
     */
    private function getAdminStats()
    {
        return [
            'total_users' => User::count(),
            'active_users' => User::active()->count(),
            'total_journeys' => Journey::count(),
            'published_journeys' => Journey::where('is_published', true)->count(),
            'total_attempts' => JourneyAttempt::count(),
            'completed_attempts' => JourneyAttempt::where('status', 'completed')->count(),
            'average_completion_rate' => $this->getAverageCompletionRate(),
            'recent_activity' => $this->getRecentActivity(),
            'popular_journeys' => $this->getPopularJourneys(),
            'user_roles' => $this->getRoleDistribution(),
        ];
    }

    /**
     * Aggregate role distribution.
     */
    private function getRoleDistribution(): array
    {
        $distribution = [];
        $distribution[UserRole::ADMINISTRATOR] = User::withRole(UserRole::ADMINISTRATOR)->count();
        $distribution[UserRole::REGULAR] = User::count() - $distribution[UserRole::ADMINISTRATOR];

        return $distribution;
    }

    /**
     * Calculate average completion rate.
     */
    private function getAverageCompletionRate()
    {
        $total = JourneyAttempt::count();
        $completed = JourneyAttempt::where('status', 'completed')->count();

        return $total > 0 ? round(($completed / $total) * 100, 2) : 0;
    }

    /**
     * Get recent activity.
     */
    private function getRecentActivity()
    {
        return JourneyAttempt::with(['user', 'journey'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
    }

    /**
     * Get popular journeys.
     */
    private function getPopularJourneys()
    {
        return Journey::withCount('attempts')
            ->orderBy('attempts_count', 'desc')
            ->limit(5)
            ->get();
    }
}
