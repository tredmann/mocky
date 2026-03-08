<?php

namespace App\Models;

use App\Enums\ConditionOperator;
use App\Enums\ConditionSource;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

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

    public function matches(Request $request, array $pathSegments = []): bool
    {
        $actual = match ($this->condition_source) {
            ConditionSource::Body => data_get($request->json()->all(), $this->condition_field),
            ConditionSource::Query => $request->query($this->condition_field),
            ConditionSource::Header => $request->header($this->condition_field),
            ConditionSource::Path => $pathSegments[(int) $this->condition_field] ?? null,
        };

        if ($actual === null) {
            return false;
        }

        $actual = (string) $actual;
        $expected = $this->condition_value;

        return match ($this->condition_operator) {
            ConditionOperator::Equals => $actual === $expected,
            ConditionOperator::NotEquals => $actual !== $expected,
            ConditionOperator::Contains => str_contains($actual, $expected),
        };
    }
}
