<?php

namespace App\Modules\IdentityAccess\Application\UseCases;

use App\Modules\IdentityAccess\Application\DTOs\LoginData;
use App\Modules\IdentityAccess\Domain\Contracts\TokenManager;

final class LoginUserUseCase
{
    public function __construct(private readonly TokenManager $tokenManager)
    {
    }

    public function execute(LoginData $data): ?string
    {
        return $this->tokenManager->attempt($data->toCredentials());
    }
}
