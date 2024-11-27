<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class SelfieCaptured
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $imageData;

    public function __construct($imageData)
    {
        $this->imageData = $imageData;
    }

    public function broadcastOn()
    {
        return new Channel('selfie');
    }
}

