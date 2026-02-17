<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Journey;
use App\Models\JourneyAttempt;
use App\Models\Institution;
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
        
        // Get basic statistics based on user role
        if ($user->role === 'administrator') {
            $stats = $this->getAdminStats();
        } elseif ($user->role === 'institution') {
            $stats = $this->getInstitutionStats($user->institution_id);
        } elseif ($user->role === 'editor') {
            $stats = $this->getEditorStats($user->id);
        } else {
            abort(403);
        }

        return view('reports.index', compact('stats'));
    }

    /**
     * Display journey reports.
     */
    public function journeys(Request $request)
    {
        $user = Auth::user();
        $query = Journey::with(['collection.institution', 'creator']);

        // Filter based on user role
        if ($user->role === 'institution') {
            $query->whereHas('collection', function($q) use ($user) {
                $q->where('institution_id', $user->institution_id);
            });
        } elseif ($user->role === 'editor') {
            $query->where('created_by', $user->id);
        }

        // Apply filters
        if ($request->filled('institution_id')) {
            $query->whereHas('collection', function($q) use ($request) {
                $q->where('institution_id', $request->institution_id);
            });
        }

        if ($request->filled('difficulty')) {
            $query->where('difficulty_level', $request->difficulty);
        }

        if ($request->filled('status')) {
            $query->where('is_published', $request->status === 'published');
        }

        $journeys = $query->withCount(['attempts', 'attempts as completed_attempts_count' => function($q) {
            $q->where('status', 'completed');
        }])->paginate(15);

        // Get filter options
        $institutions = $user->role === 'administrator' 
            ? Institution::all() 
            : Institution::where('id', $user->institution_id)->get();

        return view('reports.journeys', compact('journeys', 'institutions'));
    }

    /**
     * Display user reports.
     */
    public function users(Request $request)
    {
        $user = Auth::user();
        $query = User::with(['institution', 'memberships.institution']);

        // Filter based on user role
        if ($user->role === UserRole::INSTITUTION) {
            $query->whereHas('memberships', function ($q) use ($user) {
                $q->where('institution_id', $user->institution_id);
            });
        } elseif ($user->role === UserRole::EDITOR) {
            // Editors can only see users who have attempted their journeys
            $journeyIds = Journey::where('created_by', $user->id)->pluck('id');
            $userIds = JourneyAttempt::whereIn('journey_id', $journeyIds)->pluck('user_id')->unique();
            $query->whereIn('id', $userIds);
        }

        // Apply filters
        if ($request->filled('institution_id')) {
            $query->whereHas('memberships', function ($q) use ($request) {
                $q->where('institution_id', $request->institution_id);
            });
        }

        if ($request->filled('role')) {
            $query->withRole($request->role);
        }

        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->active();
            } elseif ($request->status === 'inactive') {
                $query->where(function ($builder) {
                    $builder->whereDoesntHave('memberships', function ($q) {
                        $q->where('is_active', true);
                    })->whereDoesntHave('roles', function ($q) {
                        $q->where('name', UserRole::ADMINISTRATOR);
                    });
                });
            }
        }

        $users = $query->withCount([
            'journeyAttempts',
            'journeyAttempts as completed_attempts_count' => function($q) {
                $q->where('status', 'completed');
            }
        ])->paginate(15);

        // Get filter options
        $institutions = $user->role === 'administrator' 
            ? Institution::all() 
            : Institution::where('id', $user->institution_id)->get();

        return view('reports.users', compact('users', 'institutions'));
    }

    /**
     * Get administrator statistics.
     */
    private function getAdminStats()
    {
        return [
            'total_users' => User::count(),
            'active_users' => User::active()->count(),
            'total_institutions' => Institution::count(),
            'active_institutions' => Institution::where('is_active', true)->count(),
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
     * Get institution-specific statistics.
     */
    private function getInstitutionStats($institutionId)
    {
        return [
            'total_users' => $this->institutionUsersQuery($institutionId)->count(),
            'active_users' => $this->institutionUsersQuery($institutionId, true)->count(),
            'total_editors' => $this->institutionUsersQuery($institutionId, true, UserRole::EDITOR)->count(),
            'total_collections' => \App\Models\JourneyCollection::where('institution_id', $institutionId)->count(),
            'total_journeys' => Journey::whereHas('collection', function($q) use ($institutionId) {
                $q->where('institution_id', $institutionId);
            })->count(),
            'published_journeys' => Journey::whereHas('collection', function($q) use ($institutionId) {
                $q->where('institution_id', $institutionId);
            })->where('is_published', true)->count(),
            'total_attempts' => JourneyAttempt::whereHas('journey.collection', function($q) use ($institutionId) {
                $q->where('institution_id', $institutionId);
            })->count(),
            'completed_attempts' => JourneyAttempt::whereHas('journey.collection', function($q) use ($institutionId) {
                $q->where('institution_id', $institutionId);
            })->where('status', 'completed')->count(),
            'average_completion_rate' => $this->getAverageCompletionRate($institutionId),
            'recent_activity' => $this->getRecentActivity($institutionId),
            'popular_journeys' => $this->getPopularJourneys($institutionId),
        ];
    }

    /**
     * Get editor-specific statistics.
     */
    private function getEditorStats($editorId)
    {
        return [
            'total_collections' => \App\Models\JourneyCollection::whereHas('editors', function ($q) use ($editorId) {
                $q->where('users.id', $editorId);
            })->count(),
            'total_journeys' => Journey::where('created_by', $editorId)->count(),
            'published_journeys' => Journey::where('created_by', $editorId)->where('is_published', true)->count(),
            'total_attempts' => JourneyAttempt::whereHas('journey', function($q) use ($editorId) {
                $q->where('created_by', $editorId);
            })->count(),
            'completed_attempts' => JourneyAttempt::whereHas('journey', function($q) use ($editorId) {
                $q->where('created_by', $editorId);
            })->where('status', 'completed')->count(),
            'average_completion_rate' => $this->getAverageCompletionRate(null, $editorId),
            'recent_activity' => $this->getRecentActivity(null, $editorId),
            'popular_journeys' => $this->getPopularJourneys(null, $editorId),
        ];
    }

    /**
     * Aggregate role distribution across memberships and global roles.
     */
    private function getRoleDistribution(): array
    {
        $distribution = DB::table('institution_user')
            ->select('role', DB::raw('count(distinct user_id) as count'))
            ->where('is_active', true)
            ->groupBy('role')
            ->pluck('count', 'role')
            ->toArray();

        $distribution[UserRole::ADMINISTRATOR] = User::withRole(UserRole::ADMINISTRATOR)->count();

        foreach (UserRole::all() as $role) {
            $distribution[$role] = $distribution[$role] ?? 0;
        }

        ksort($distribution);

        return $distribution;
    }

    /**
     * Base query builder for institution-bound users.
     */
    private function institutionUsersQuery(int $institutionId, ?bool $onlyActive = null, ?string $role = null)
    {
        return User::whereHas('memberships', function ($q) use ($institutionId, $onlyActive, $role) {
            $q->where('institution_id', $institutionId);

            if (!is_null($onlyActive)) {
                $q->where('is_active', $onlyActive);
            }

            if ($role) {
                $q->where('role', $role);
            }
        });
    }

    /**
     * Calculate average completion rate.
     */
    private function getAverageCompletionRate($institutionId = null, $editorId = null)
    {
        $query = JourneyAttempt::query();

        if ($institutionId) {
            $query->whereHas('journey.collection', function($q) use ($institutionId) {
                $q->where('institution_id', $institutionId);
            });
        }

        if ($editorId) {
            $query->whereHas('journey', function($q) use ($editorId) {
                $q->where('created_by', $editorId);
            });
        }

        $total = $query->count();
        $completed = $query->where('status', 'completed')->count();

        return $total > 0 ? round(($completed / $total) * 100, 2) : 0;
    }

    /**
     * Get recent activity.
     */
    private function getRecentActivity($institutionId = null, $editorId = null)
    {
        $query = JourneyAttempt::with(['user', 'journey'])
            ->orderBy('created_at', 'desc')
            ->limit(10);

        if ($institutionId) {
            $query->whereHas('journey.collection', function($q) use ($institutionId) {
                $q->where('institution_id', $institutionId);
            });
        }

        if ($editorId) {
            $query->whereHas('journey', function($q) use ($editorId) {
                $q->where('created_by', $editorId);
            });
        }

        return $query->get();
    }

    /**
     * Get popular journeys.
     */
    private function getPopularJourneys($institutionId = null, $editorId = null)
    {
        $query = Journey::withCount('attempts')
            ->orderBy('attempts_count', 'desc')
            ->limit(5);

        if ($institutionId) {
            $query->whereHas('collection', function($q) use ($institutionId) {
                $q->where('institution_id', $institutionId);
            });
        }

        if ($editorId) {
            $query->where('created_by', $editorId);
        }

        return $query->get();
    }
}
