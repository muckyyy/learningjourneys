<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class VoiceChunk implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    public $message;
    public $type;
    public $attemptid;
    public $index = 0;
    /**
     * Create a new event instance.
     */
    public function __construct($message, $type, $attemptid, $index = 0)
    {
        $this->message = $message;
        $this->type = $type;
        $this->attemptid = $attemptid;
        $this->index = $index;
        Log::info('Broadcasting on channel: voice.mode.' . $this->attemptid. ' with event name: voice.chunk.sent and message : ' . $this->message);
        Log::info('Broadcast driver:', ['driver' => config('broadcasting.default')]);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('voice.mode.' . $this->attemptid),
        ];
    }
    public function broadcastAs(): string
    {
        return 'voice.chunk.sent';
    }
}
