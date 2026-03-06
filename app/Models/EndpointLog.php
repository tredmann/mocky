<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EndpointLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'endpoint_id',
        'matched_conditional_response_id',
        'request_method',
        'request_ip',
        'request_user_agent',
        'request_headers',
        'request_query',
        'request_body',
        'response_status_code',
        'response_body',
        'created_at',
    ];

    protected $casts = [
        'request_headers' => 'array',
        'request_query' => 'array',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<Endpoint, $this> */
    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(Endpoint::class);
    }

    /** @return BelongsTo<ConditionalResponse, $this> */
    public function matchedConditionalResponse(): BelongsTo
    {
        return $this->belongsTo(ConditionalResponse::class, 'matched_conditional_response_id');
    }
}
