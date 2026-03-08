<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\EndpointCollection;
use App\Models\User;
use Illuminate\Support\Str;

class CreateEndpointCollection
{
    public function handle(User $user, string $name, ?string $description = null, ?string $slug = null): EndpointCollection
    {
        if ($slug !== null && EndpointCollection::where('slug', $slug)->exists()) {
            $slug = Str::uuid()->toString();
        }

        return $user->endpointCollections()->create([
            'name' => $name,
            'description' => $description,
            'slug' => $slug,
        ]);
    }
}
