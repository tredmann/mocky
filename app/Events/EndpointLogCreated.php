<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\EndpointLog;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EndpointLogCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly string $endpointId;

    public readonly string $method;

    public readonly int $statusCode;

    public readonly ?string $ip;

    public readonly string $createdAt;

    public function __construct(EndpointLog $log)
    {
        $this->endpointId = $log->endpoint_id;
        $this->method = $log->request_method;
        $this->statusCode = $log->response_status_code;
        $this->ip = $log->request_ip;
        $this->createdAt = ($log->created_at ?? now())->toISOString();
    }

    public function broadcastOn(): Channel
    {
        return new Channel('endpoint.'.$this->endpointId);
    }
}
