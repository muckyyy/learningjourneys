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
            'attemptid' => 'required|numeric',
        ]);
        // For testing: send textual chunks to attemptid 3
        
        $chunks = [
            ['message' => 'This is the first test textual chunk.', 'type' => 'text'],
            ['message' => 'This is the second test textual chunk.', 'type' => 'text'],
        ];
        foreach ($chunks as $chunk) {
            broadcast(new VoiceChunk($chunk['message'], $chunk['type'], $request->input('attemptid')))->toOthers();
        }
        // Optionally, you can return here if you don't want to process further
        return response()->json(['status' => 'multiple chunks broadcasted']);
        
        
        $message = $request->input('message');
        $type = $request->input('type', 'audio');
        $attemptid = $request->input('attemptid');

        // Broadcast immediately (no queue)
        broadcast(new VoiceChunk($message, $type, $attemptid))->toOthers();

        return response()->json(['status' => 'chunk broadcasted']);
    }
}
