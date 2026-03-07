<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Journey;
use App\Models\JourneyAttempt;
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
        $query = Journey::with(['collection', 'creator']);

        if ($request->filled('difficulty')) {
            $query->where('difficulty_level', $request->difficulty);
        }

        if ($request->filled('status')) {
            $query->where('is_published', $request->status === 'published');
        }

        $journeys = $query->withCount(['attempts', 'attempts as completed_attempts_count' => function($q) {
            $q->where('status', 'completed');
        }])->paginate(15);

        return view('reports.journeys', compact('journeys'));
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
