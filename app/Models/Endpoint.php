<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Endpoint extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'slug',
        'method',
        'status_code',
        'content_type',
        'response_body',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'status_code' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function booted(): void
    {
        static::creating(function (Endpoint $endpoint) {
            if (empty($endpoint->slug)) {
                $endpoint->slug = Str::uuid();
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<EndpointLog, $this> */
    public function logs(): HasMany
    {
        return $this->hasMany(EndpointLog::class)->latest('created_at');
    }

    /** @return HasMany<ConditionalResponse, $this> */
    public function conditionalResponses(): HasMany
    {
        return $this->hasMany(ConditionalResponse::class)->orderBy('priority');
    }

    public function getMockUrlAttribute(): string
    {
        return url("/mock/{$this->slug}");
    }
}
