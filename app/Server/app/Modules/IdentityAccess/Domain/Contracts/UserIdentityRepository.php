<?php

namespace App\Modules\IdentityAccess\Domain\Contracts;

use App\Models\User;

interface UserIdentityRepository
{
    public function create(array $attributes): User;

    public function findByEmail(string $email): ?User;
}
