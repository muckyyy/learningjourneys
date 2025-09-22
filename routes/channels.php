<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\User;
/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('audio-session.{sessionId}', function ($user, $sessionId) {
    // Check if user owns the audio recording session
    return \App\Models\AudioRecording::where('session_id', $sessionId)
        ->where('user_id', $user->id)
        ->exists();
});

Broadcast::channel('voice.mode.{attemptid}', function ($user, $attemptid) {
    // Check if user owns the voice attempt
    return \App\Models\JourneyAttempt::where('id', $attemptid)
        ->where('user_id', $user->id)
        ->exists();
});
