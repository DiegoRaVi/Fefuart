<?php

namespace App\Modules\IdentityAccess\Application\UseCases;

use App\Modules\IdentityAccess\Domain\Contracts\TokenManager;

final class LogoutCurrentUserUseCase
{
    public function __construct(private readonly TokenManager $tokenManager)
    {
    }

    public function execute(): void
    {
        $this->tokenManager->invalidateCurrentToken();
    }
}
