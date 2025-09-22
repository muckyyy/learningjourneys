<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Events\VoiceChunk;

class VoiceModeController extends Controller
{
    /**
     * Receive a voice chunk and broadcast it.
     */
    public function start(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
            'attemptid' => 'required|numeric',
            'type' => 'nullable|string',
        ]);

        $message = $request->input('message');
        $type = $request->input('type', 'audio');
        $attemptid = $request->input('attemptid');

        // Broadcast immediately (no queue)
        broadcast(new VoiceChunk($message, $type, $attemptid))->toOthers();

        return response()->json(['status' => 'chunk broadcasted']);
    }
}
