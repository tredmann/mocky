<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('inbox.{userId}', function (User $user, string $userId) {
    return $user->id === $userId;
});
