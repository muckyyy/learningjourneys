<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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
        try {
            $request->validate([
                'name' => 'required|string|max:255',
            ]);

            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'error' => 'User not authenticated'
                ], 401);
            }
            
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
        } catch (\Exception $e) {
            Log::error('API Token creation error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'request_data' => $request->all(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Failed to create API token: ' . $e->getMessage()
                ], 500);
            }
            
            return back()->withErrors('Failed to create API token: ' . $e->getMessage());
        }
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
