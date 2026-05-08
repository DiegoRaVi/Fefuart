<?php

namespace App\Modules\IdentityAccess\Infrastructure\Auth;

use App\Modules\IdentityAccess\Domain\Contracts\TokenManager;
use Tymon\JWTAuth\Facades\JWTAuth;

final class JwtTokenManager implements TokenManager
{
    public function attempt(array $credentials): ?string
    {
        $token = JWTAuth::attempt($credentials);

        return $token ?: null;
    }

    public function invalidateCurrentToken(): void
    {
        $token = JWTAuth::getToken();

        if ($token) {
            JWTAuth::invalidate($token);
        }
    }
}
