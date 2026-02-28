<?php

namespace App\Http\Controllers;

use App\Models\CertificateIssue;
use App\Models\JourneyAttempt;
use App\Services\TokenLedger;
use Illuminate\Support\Facades\Auth;

class UserReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display the authenticated user's personal report.
     */
    public function index(TokenLedger $ledger)
    {
        $user = Auth::user();

        // Token balance
        $balance = $ledger->balance($user);

        // Journey stats
        $attempts = $user->journeyAttempts()
            ->with('journey')
            ->orderByDesc('created_at')
            ->get();

        $totalAttempts   = $attempts->count();
        $completed       = $attempts->where('status', 'completed')->count();
        $inProgress      = $attempts->where('status', 'in_progress')->count();
        $completionRate  = $totalAttempts > 0 ? round(($completed / $totalAttempts) * 100, 1) : 0;
        $avgScore        = $attempts->where('status', 'completed')->avg('score');

        // Recent token transactions (last 20)
        $transactions = $user->tokenTransactions()
            ->orderByDesc('occurred_at')
            ->take(20)
            ->get();

        // Active grants
        $activeGrants = $user->tokenGrants()->active()->get();

        // Certificates earned
        $certificateIssues = CertificateIssue::with(['certificate', 'collection'])
            ->where('user_id', $user->id)
            ->orderByDesc('issued_at')
            ->get();

        return view('users.report', compact(
            'user',
            'balance',
            'attempts',
            'totalAttempts',
            'completed',
            'inProgress',
            'completionRate',
            'avgScore',
            'transactions',
            'activeGrants',
            'certificateIssues'
        ));
    }

    /**
     * Show the report for a specific journey attempt.
     */
    public function attemptReport(JourneyAttempt $attempt)
    {
        // Ensure the authenticated user owns this attempt
        if ($attempt->user_id !== Auth::id()) {
            abort(403);
        }

        $attempt->load('journey');

        return view('users.attempt-report', compact('attempt'));
    }
}
