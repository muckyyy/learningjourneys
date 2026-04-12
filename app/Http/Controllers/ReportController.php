<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Journey;
use App\Models\JourneyAttempt;
use App\Models\JourneyStep;
use App\Models\JourneyStepResponse;
use App\Models\JourneyPromptLog;
use App\Models\TokenTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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

        $journeySteps = JourneyStep::query()
            ->where('journey_id', $journey->id)
            ->orderBy('order')
            ->orderBy('id')
            ->get(['id', 'title', 'order']);

        $chartFilters = [
            'rating' => (string) $request->get('chart_rating', ''),
            'user_search' => trim((string) $request->get('chart_user_search', '')),
            'two_plus' => $request->boolean('chart_two_plus'),
            'time' => (string) $request->get('chart_time', 'all'),
        ];

        $chartAttemptsQuery = JourneyAttempt::query()
            ->where('journey_id', $journey->id);

        if ($chartFilters['rating'] === 'rated_1_2') {
            $chartAttemptsQuery->whereNotNull('rating')->whereBetween('rating', [1, 2]);
        } elseif ($chartFilters['rating'] === 'rated_2_01_4') {
            $chartAttemptsQuery->whereNotNull('rating')->whereBetween('rating', [2.01, 4]);
        } elseif ($chartFilters['rating'] === 'rated_4_01_5') {
            $chartAttemptsQuery->whereNotNull('rating')->whereBetween('rating', [4.01, 5]);
        }

        if ($chartFilters['user_search'] !== '') {
            $searchTerm = $chartFilters['user_search'];
            $chartAttemptsQuery->whereHas('user', function ($query) use ($searchTerm) {
                $query
                    ->where('name', 'like', '%' . $searchTerm . '%')
                    ->orWhere('email', 'like', '%' . $searchTerm . '%');
            });
        }

        if ($chartFilters['time'] === 'week') {
            $chartAttemptsQuery->where('created_at', '>=', now()->startOfWeek());
        } elseif ($chartFilters['time'] === 'month') {
            $chartAttemptsQuery->where('created_at', '>=', now()->startOfMonth());
        } elseif ($chartFilters['time'] === 'year') {
            $chartAttemptsQuery->where('created_at', '>=', now()->startOfYear());
        }

        if ($chartFilters['two_plus']) {
            $eligibleUserIds = (clone $chartAttemptsQuery)
                ->select('user_id')
                ->whereNotNull('user_id')
                ->groupBy('user_id')
                ->havingRaw('COUNT(*) >= 2')
                ->pluck('user_id');

            $chartAttemptsQuery->whereIn('user_id', $eligibleUserIds);
        }

        $filteredAttemptIds = (clone $chartAttemptsQuery)->pluck('id');

        $stepResponses = JourneyStepResponse::query()
            ->whereIn('journey_step_responses.journey_attempt_id', $filteredAttemptIds)
            ->whereNotNull('journey_step_responses.step_rate')
            ->orderBy('journey_step_responses.journey_attempt_id')
            ->orderBy('journey_step_responses.journey_step_id')
            ->orderBy('journey_step_responses.submitted_at')
            ->orderBy('journey_step_responses.id')
            ->get([
                'journey_step_responses.journey_attempt_id',
                'journey_step_responses.journey_step_id',
                'journey_step_responses.step_rate',
            ]);

        $stepBuckets = [];
        $attemptCounter = [];

        foreach ($stepResponses as $response) {
            $attemptId = (int) $response->journey_attempt_id;
            $stepId = (int) $response->journey_step_id;
            $stepRate = (float) $response->step_rate;

            $attemptCounter[$attemptId] ??= [];
            $attemptCounter[$attemptId][$stepId] = ($attemptCounter[$attemptId][$stepId] ?? 0) + 1;
            $attemptIndex = $attemptCounter[$attemptId][$stepId];

            $stepBuckets[$stepId]['all'][] = $stepRate;

            if ($attemptIndex >= 1 && $attemptIndex <= 3) {
                $stepBuckets[$stepId]['attempt_' . $attemptIndex][] = $stepRate;
            }
        }

        $stepScoreChart = [
            'labels' => [],
            'average' => [],
            'attempt_1' => [],
            'attempt_2' => [],
            'attempt_3' => [],
            'has_data' => $stepResponses->isNotEmpty(),
            'filtered_attempts_count' => $filteredAttemptIds->count(),
            'filters' => $chartFilters,
        ];

        foreach ($journeySteps as $step) {
            $stepId = (int) $step->id;
            $stepBucketsForStep = $stepBuckets[$stepId] ?? [];
            $stepLabel = 'Step ' . ($step->order ?? $step->id);

            if (!empty($step->title)) {
                $stepLabel .= ': ' . $step->title;
            }

            $stepScoreChart['labels'][] = $stepLabel;

            $allValues = $stepBucketsForStep['all'] ?? [];
            $firstValues = $stepBucketsForStep['attempt_1'] ?? [];
            $secondValues = $stepBucketsForStep['attempt_2'] ?? [];
            $thirdValues = $stepBucketsForStep['attempt_3'] ?? [];

            $stepScoreChart['average'][] = count($allValues) > 0 ? round(array_sum($allValues) / count($allValues), 2) : null;
            $stepScoreChart['attempt_1'][] = count($firstValues) > 0 ? round(array_sum($firstValues) / count($firstValues), 2) : null;
            $stepScoreChart['attempt_2'][] = count($secondValues) > 0 ? round(array_sum($secondValues) / count($secondValues), 2) : null;
            $stepScoreChart['attempt_3'][] = count($thirdValues) > 0 ? round(array_sum($thirdValues) / count($thirdValues), 2) : null;
        }

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
            'stepScoreChart' => $stepScoreChart,
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

        $filteredUserIds = (clone $query)->pluck('id');

        $avgStepRateSubquery = JourneyStepResponse::query()
            ->join('journey_attempts as ja', 'ja.id', '=', 'journey_step_responses.journey_attempt_id')
            ->selectRaw('AVG(journey_step_responses.step_rate)')
            ->whereColumn('ja.user_id', 'users.id')
            ->whereNotNull('journey_step_responses.step_rate');

        $stepResponsesCountSubquery = JourneyStepResponse::query()
            ->join('journey_attempts as ja', 'ja.id', '=', 'journey_step_responses.journey_attempt_id')
            ->selectRaw('COUNT(journey_step_responses.id)')
            ->whereColumn('ja.user_id', 'users.id')
            ->whereNotNull('journey_step_responses.step_rate');

        $users = $query
            ->withCount([
                'journeyAttempts',
                'journeyAttempts as completed_attempts_count' => function ($q) {
                    $q->where('status', 'completed');
                },
            ])
            ->addSelect([
                'avg_step_rate' => $avgStepRateSubquery,
            ])
            ->paginate(15)
            ->withQueryString();

        $topActiveUsers = User::query()
            ->whereIn('id', $filteredUserIds)
            ->withCount([
                'journeyAttempts',
                'journeyAttempts as completed_attempts_count' => function ($q) {
                    $q->where('status', 'completed');
                },
            ])
            ->addSelect([
                'avg_step_rate' => $avgStepRateSubquery,
            ])
            ->orderByDesc('journey_attempts_count')
            ->orderByDesc('completed_attempts_count')
            ->limit(10)
            ->get();

        $topCapableUsers = User::query()
            ->whereIn('id', $filteredUserIds)
            ->withCount([
                'journeyAttempts',
                'journeyAttempts as completed_attempts_count' => function ($q) {
                    $q->where('status', 'completed');
                },
            ])
            ->addSelect([
                'avg_step_rate' => $avgStepRateSubquery,
                'step_responses_count' => $stepResponsesCountSubquery,
            ])
            ->having('step_responses_count', '>=', 3)
            ->orderByDesc('avg_step_rate')
            ->orderByDesc('completed_attempts_count')
            ->limit(10)
            ->get();

        // ── 10-minute cached trend charts ──────────────────────────────────
        $cacheKey = 'reports.users.trends';
        $trendCharts = Cache::remember($cacheKey, now()->addMinutes(10), function () {

            // Helper: generate a zero-filled weekly skeleton
            $weeks = collect();
            for ($i = 25; $i >= 0; $i--) {
                $weeks->push(now()->subWeeks($i)->startOfWeek()->format('Y-W'));
            }

            // 1. Active users WAU (users who started a journey attempt that week)
            $activeUsersRaw = JourneyAttempt::query()
                ->selectRaw('DATE_FORMAT(MIN(created_at),"%Y-%v") as yw, COUNT(DISTINCT user_id) as val')
                ->whereNotNull('user_id')
                ->where('created_at', '>=', now()->subWeeks(25)->startOfWeek())
                ->groupByRaw('YEARWEEK(created_at,3)')
                ->orderByRaw('YEARWEEK(created_at,3)')
                ->pluck('val', 'yw');

            // 2. New registrations WAU
            $newUsersRaw = User::query()
                ->selectRaw('DATE_FORMAT(MIN(created_at),"%Y-%v") as yw, COUNT(*) as val')
                ->where('created_at', '>=', now()->subWeeks(25)->startOfWeek())
                ->groupByRaw('YEARWEEK(created_at,3)')
                ->orderByRaw('YEARWEEK(created_at,3)')
                ->pluck('val', 'yw');

            // 3. Completion rate WAU (completed / total attempts that week)
            $attemptsRaw = JourneyAttempt::query()
                ->selectRaw('DATE_FORMAT(MIN(created_at),"%Y-%v") as yw, COUNT(*) as total, SUM(CASE WHEN status="completed" THEN 1 ELSE 0 END) as completed')
                ->where('created_at', '>=', now()->subWeeks(25)->startOfWeek())
                ->groupByRaw('YEARWEEK(created_at,3)')
                ->orderByRaw('YEARWEEK(created_at,3)')
                ->get()
                ->keyBy('yw');

            // 4. Avg step score WAU
            $stepScoreRaw = JourneyStepResponse::query()
                ->join('journey_attempts as ja', 'ja.id', '=', 'journey_step_responses.journey_attempt_id')
                ->selectRaw('DATE_FORMAT(MIN(journey_step_responses.submitted_at),"%Y-%v") as yw, ROUND(AVG(journey_step_responses.step_rate),2) as val')
                ->whereNotNull('journey_step_responses.step_rate')
                ->where('journey_step_responses.submitted_at', '>=', now()->subWeeks(25)->startOfWeek())
                ->groupByRaw('YEARWEEK(journey_step_responses.submitted_at,3)')
                ->orderByRaw('YEARWEEK(journey_step_responses.submitted_at,3)')
                ->pluck('val', 'yw');

            // 5. Avg feedback rating WAU
            $feedbackRatingRaw = JourneyAttempt::query()
                ->selectRaw('DATE_FORMAT(MIN(completed_at),"%Y-%v") as yw, ROUND(AVG(rating),2) as val')
                ->whereNotNull('rating')
                ->whereNotNull('completed_at')
                ->where('completed_at', '>=', now()->subWeeks(25)->startOfWeek())
                ->groupByRaw('YEARWEEK(completed_at,3)')
                ->orderByRaw('YEARWEEK(completed_at,3)')
                ->pluck('val', 'yw');

            // 6. Attempts per active user WAU
            $attemptsPerUserRaw = JourneyAttempt::query()
                ->selectRaw('DATE_FORMAT(MIN(created_at),"%Y-%v") as yw, ROUND(COUNT(*)/COUNT(DISTINCT user_id),2) as val')
                ->whereNotNull('user_id')
                ->where('created_at', '>=', now()->subWeeks(25)->startOfWeek())
                ->groupByRaw('YEARWEEK(created_at,3)')
                ->orderByRaw('YEARWEEK(created_at,3)')
                ->pluck('val', 'yw');

            // 7. Token spend per user WAU
            $tokenRaw = TokenTransaction::query()
                ->selectRaw('DATE_FORMAT(MIN(created_at),"%Y-%v") as yw, ROUND(SUM(amount)/NULLIF(COUNT(DISTINCT user_id),0),2) as val')
                ->where('type', TokenTransaction::TYPE_DEBIT)
                ->whereNotNull('user_id')
                ->where('created_at', '>=', now()->subWeeks(25)->startOfWeek())
                ->groupByRaw('YEARWEEK(created_at,3)')
                ->orderByRaw('YEARWEEK(created_at,3)')
                ->pluck('val', 'yw');

            // Build uniform weekly series
            $labels          = $weeks->values()->toArray();
            $activeUsers     = $weeks->map(fn($w) => (int) ($activeUsersRaw[$w] ?? 0))->values()->toArray();
            $newUsers        = $weeks->map(fn($w) => (int) ($newUsersRaw[$w] ?? 0))->values()->toArray();
            $completionRate  = $weeks->map(function ($w) use ($attemptsRaw) {
                $row = $attemptsRaw[$w] ?? null;
                if (!$row || (int)$row->total === 0) return null;
                return round(($row->completed / $row->total) * 100, 2);
            })->values()->toArray();
            $avgStepScore    = $weeks->map(fn($w) => isset($stepScoreRaw[$w]) ? (float)$stepScoreRaw[$w] : null)->values()->toArray();
            $avgRating       = $weeks->map(fn($w) => isset($feedbackRatingRaw[$w]) ? (float)$feedbackRatingRaw[$w] : null)->values()->toArray();
            $attemptsPerUser = $weeks->map(fn($w) => isset($attemptsPerUserRaw[$w]) ? (float)$attemptsPerUserRaw[$w] : null)->values()->toArray();
            $tokenPerUser    = $weeks->map(fn($w) => isset($tokenRaw[$w]) ? (float)$tokenRaw[$w] : null)->values()->toArray();

            // 8. First-attempt cohort retention
            //    For each signup cohort week, track: week-0 (all), week-1 (attempt in week after signup), week-4 (attempt 4 weeks after)
            $cohortSignups = User::query()
                ->selectRaw('YEARWEEK(created_at,3) as cohort_yw, DATE_FORMAT(MIN(created_at),"%Y-%v") as cohort_label, COUNT(*) as total')
                ->where('created_at', '>=', now()->subWeeks(25)->startOfWeek())
                ->groupByRaw('YEARWEEK(created_at,3)')
                ->orderByRaw('YEARWEEK(created_at,3)')
                ->get();

            $cohortLabels   = [];
            $cohortWeek0    = [];
            $cohortWeek1    = [];
            $cohortWeek4    = [];

            foreach ($cohortSignups as $cohort) {
                $yw    = (int) $cohort->cohort_yw;
                $total = (int) $cohort->total;
                if ($total === 0) continue;

                $w1Yw = (int) DB::selectOne("SELECT YEARWEEK(DATE_ADD(STR_TO_DATE(CONCAT(?,'-1'),'%X-%V-%w'),INTERVAL 1 WEEK),3) as yw", [$cohort->cohort_label])->yw;
                $w4Yw = (int) DB::selectOne("SELECT YEARWEEK(DATE_ADD(STR_TO_DATE(CONCAT(?,'-1'),'%X-%V-%w'),INTERVAL 4 WEEK),3) as yw", [$cohort->cohort_label])->yw;

                $retained1 = (int) JourneyAttempt::query()
                    ->whereHas('user', fn($q) => $q->whereRaw('YEARWEEK(users.created_at,3) = ?', [$yw]))
                    ->whereRaw('YEARWEEK(journey_attempts.created_at,3) = ?', [$w1Yw])
                    ->distinct('user_id')
                    ->count('user_id');

                $retained4 = (int) JourneyAttempt::query()
                    ->whereHas('user', fn($q) => $q->whereRaw('YEARWEEK(users.created_at,3) = ?', [$yw]))
                    ->whereRaw('YEARWEEK(journey_attempts.created_at,3) = ?', [$w4Yw])
                    ->distinct('user_id')
                    ->count('user_id');

                $cohortLabels[] = $cohort->cohort_label;
                $cohortWeek0[]  = $total;
                $cohortWeek1[]  = $total > 0 ? round($retained1 / $total * 100, 1) : 0;
                $cohortWeek4[]  = $total > 0 ? round($retained4 / $total * 100, 1) : 0;
            }

            // 9. Top users leaderboard (best avg step score, min 3 rated responses)
            $leaderboard = JourneyStepResponse::query()
                ->join('journey_attempts as ja', 'ja.id', '=', 'journey_step_responses.journey_attempt_id')
                ->join('users', 'users.id', '=', 'ja.user_id')
                ->selectRaw('users.id, users.name, users.email, ROUND(AVG(journey_step_responses.step_rate),2) as avg_score, COUNT(journey_step_responses.id) as rated_count')
                ->whereNotNull('journey_step_responses.step_rate')
                ->groupBy('users.id', 'users.name', 'users.email')
                ->havingRaw('COUNT(journey_step_responses.id) >= 3')
                ->orderByDesc('avg_score')
                ->limit(10)
                ->get();

            return compact(
                'labels',
                'activeUsers',
                'newUsers',
                'completionRate',
                'avgStepScore',
                'avgRating',
                'attemptsPerUser',
                'tokenPerUser',
                'cohortLabels',
                'cohortWeek0',
                'cohortWeek1',
                'cohortWeek4',
                'leaderboard'
            );
        });

        return view('reports.users', compact('users', 'topActiveUsers', 'topCapableUsers', 'trendCharts'));
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
