<?php

namespace App\Modules\IdentityAccess\Infrastructure\Persistence;

use App\Models\User;
use App\Modules\IdentityAccess\Domain\Contracts\UserIdentityRepository;

final class EloquentUserIdentityRepository implements UserIdentityRepository
{
    public function create(array $attributes): User
    {
        return User::create($attributes);
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }
}
