<?php

namespace App\Models;

use App\Enums\ConditionOperator;
use App\Enums\ConditionSource;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConditionalResponse extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'endpoint_id',
        'condition_source',
        'condition_field',
        'condition_operator',
        'condition_value',
        'status_code',
        'content_type',
        'response_body',
        'priority',
    ];

    protected $casts = [
        'condition_source' => ConditionSource::class,
        'condition_operator' => ConditionOperator::class,
        'status_code' => 'integer',
        'priority' => 'integer',
    ];

    /** @return BelongsTo<Endpoint, $this> */
    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(Endpoint::class);
    }
}
