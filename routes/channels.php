<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{id}', function (User $user, string $id): bool {
    return (string) $user->getKey() === $id;
});
