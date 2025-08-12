<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApiTokenController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display API token management page
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $tokens = $user->tokens;

        // Return JSON response for AJAX requests (for authentication checking)
        if ($request->expectsJson()) {
            return response()->json([
                'authenticated' => true,
                'tokens_count' => $tokens->count(),
                'user' => $user->name
            ]);
        }

        return view('api-tokens.index', compact('tokens'));
    }

    /**
     * Create a new API token
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $user = Auth::user();
        
        // Create token with all abilities (you can customize this)
        $token = $user->createToken($request->name);

        // Return JSON response for AJAX requests
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'token' => $token->plainTextToken,
                'message' => 'API token created successfully!'
            ]);
        }

        return back()->with('success', 'API token created successfully!')
                    ->with('token', $token->plainTextToken);
    }

    /**
     * Revoke an API token
     */
    public function destroy($tokenId)
    {
        $user = Auth::user();
        $user->tokens()->where('id', $tokenId)->delete();

        return back()->with('success', 'API token revoked successfully!');
    }
}
