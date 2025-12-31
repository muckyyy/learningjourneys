<?php

namespace App\Http\Controllers;

use App\Exceptions\VirtualVendorDisabledException;
use App\Models\TokenBundle;
use App\Services\TokenLedger;
use App\Services\TokenPurchaseService;
use Illuminate\Http\Request;

class TokenPurchaseController extends Controller
{
    public function __construct(private TokenLedger $ledger, private TokenPurchaseService $purchaseService)
    {
        $this->middleware(['auth', 'verified']);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $bundles = TokenBundle::active()->orderBy('token_amount')->get();
        $balance = $this->ledger->balance($user);
        $transactions = $user->tokenTransactions()->with(['journey'])->latest()->limit(15)->get();

        return view('tokens.index', [
            'bundles' => $bundles,
            'balance' => $balance,
            'transactions' => $transactions,
            'virtualVendorEnabled' => config('tokens.virtual_vendor.enabled'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'bundle_id' => ['required', 'exists:token_bundles,id'],
        ]);

        $bundle = TokenBundle::active()->findOrFail($data['bundle_id']);

        try {
            $this->purchaseService->createVirtualPurchase($request->user(), $bundle);
        } catch (VirtualVendorDisabledException $e) {
            return redirect()->route('tokens.index')->with('error', $e->getMessage());
        }

        return redirect()->route('tokens.index')->with('status', 'Tokens added to your account.');
    }

    public function balance(Request $request)
    {
        return response()->json($this->ledger->balance($request->user()));
    }
}
