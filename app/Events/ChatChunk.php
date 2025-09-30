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

class ChatChunk implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    public $message;
    public $type;
    public $attemptid;
    public $jsrid;
    public $config;
    /**
     * Create a new event instance.
     */
    public function __construct($attemptid,$type, $message, $jsrid = 0, $config = '')
    {
        $this->type = $type;
        $this->attemptid = $attemptid;
        $this->message = $message;
        $this->jsrid = $jsrid;
        $this->config = $config;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.mode.' . $this->attemptid),
        ];
    }
    public function broadcastAs(): string
    {
        return 'chat.chunk.sent';
    }
}
