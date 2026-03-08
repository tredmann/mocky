<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EndpointCollection;
use App\Models\User;

class EndpointCollectionPolicy
{
    public function view(User $user, EndpointCollection $collection): bool
    {
        return $user->id === $collection->user_id;
    }

    public function update(User $user, EndpointCollection $collection): bool
    {
        return $user->id === $collection->user_id;
    }

    public function delete(User $user, EndpointCollection $collection): bool
    {
        return $user->id === $collection->user_id;
    }

    public function createEndpoint(User $user, EndpointCollection $collection): bool
    {
        return $user->id === $collection->user_id;
    }
}
