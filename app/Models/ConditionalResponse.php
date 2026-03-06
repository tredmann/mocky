<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

class ConditionalResponse extends Model
{
    use HasFactory;

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
            'body' => data_get($request->json()->all(), $this->condition_field),
            'query' => $request->query($this->condition_field),
            'header' => $request->header($this->condition_field),
            'path' => $pathSegments[(int) $this->condition_field] ?? null,
            default => null,
        };

        if ($actual === null) {
            return false;
        }

        $actual = (string) $actual;
        $expected = $this->condition_value;

        if ($this->condition_operator === 'equals') {
            return $actual === $expected;
        }

        if ($this->condition_operator === 'not_equals') {
            return $actual !== $expected;
        }

        return str_contains($actual, $expected);
    }
}
