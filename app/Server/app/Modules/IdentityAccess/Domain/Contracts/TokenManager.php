<?php

namespace App\Modules\IdentityAccess\Domain\Contracts;

interface TokenManager
{
    public function attempt(array $credentials): ?string;

    public function invalidateCurrentToken(): void;
}
