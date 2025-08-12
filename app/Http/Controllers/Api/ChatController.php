<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ChatController extends Controller
{
    // Placeholder methods to allow route registration
    public function startChat(Request $request)
    {
        return response()->json(['message' => 'startChat placeholder'], 200);
    }

    public function chatSubmit(Request $request)
    {
        return response()->json(['message' => 'chatSubmit placeholder'], 200);
    }
}
