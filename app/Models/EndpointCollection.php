<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class EndpointCollection extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'description',
    ];

    protected static function booted(): void
    {
        static::creating(function (EndpointCollection $collection) {
            if (empty($collection->slug)) {
                $collection->slug = Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Endpoint, $this> */
    public function endpoints(): HasMany
    {
        return $this->hasMany(Endpoint::class, 'collection_id');
    }

    public function getMockUrlPrefixAttribute(): string
    {
        return url("/mock/{$this->slug}");
    }
}
