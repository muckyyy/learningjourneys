<?php

namespace App\Http\Controllers;

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
            'activeGrants'
        ));
    }
}
