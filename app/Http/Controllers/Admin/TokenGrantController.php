<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserTokenGrant;
use App\Services\TokenLedger;
use Illuminate\Http\Request;

class TokenGrantController extends Controller
{
    public function __construct(private TokenLedger $ledger)
    {
    }

    public function show(User $user)
    {
        $summary = $this->ledger->balance($user);
        $transactions = $user->tokenTransactions()->latest()->limit(25)->with('journey')->get();

        return response()->json([
            'balance' => $summary['total'],
            'expiring_soon' => $summary['expiring_soon'],
            'grants' => $summary['grants'],
            'transactions' => $transactions,
        ]);
    }

    public function store(Request $request, User $user)
    {
        $data = $request->validate([
            'tokens' => ['required', 'integer', 'min:1'],
            'expires_after_days' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $tokens = (int) $data['tokens'];
        $expiresAfterDays = array_key_exists('expires_after_days', $data) && $data['expires_after_days'] !== null
            ? (int) $data['expires_after_days']
            : null;

        $grant = $this->ledger->grant($user, $tokens, [
            'source' => UserTokenGrant::SOURCE_MANUAL,
            'granted_by' => $request->user()->id,
            'notes' => $data['notes'] ?? null,
            'expires_after_days' => $expiresAfterDays,
            'description' => sprintf('Manual grant by %s', $request->user()->name),
        ]);

        return response()->json($grant, 201);
    }
}
