<?php

namespace App\Models;

use App\Enums\EndpointType;
use App\Models\Concerns\FormatsResponseBody;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Endpoint extends Model
{
    use FormatsResponseBody, HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'collection_id',
        'name',
        'description',
        'slug',
        'method',
        'status_code',
        'content_type',
        'response_body',
        'is_active',
        'type',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'status_code' => 'integer',
        'type' => EndpointType::class,
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<EndpointCollection, $this> */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(EndpointCollection::class, 'collection_id');
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
        $collectionSlug = $this->collection->slug ?? $this->collection_id;

        return url("/mock/{$collectionSlug}/{$this->slug}");
    }

    public function getSoapUrlAttribute(): string
    {
        $collectionSlug = $this->collection->slug ?? $this->collection_id;

        return url("/soap/{$collectionSlug}/{$this->slug}");
    }

    public function isSoap(): bool
    {
        return $this->type === EndpointType::Soap;
    }
}
