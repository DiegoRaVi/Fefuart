<?php

namespace App\Modules\IdentityAccess\Application\UseCases;

use App\Models\User;
use App\Modules\IdentityAccess\Application\DTOs\RegisterUserData;
use App\Modules\IdentityAccess\Domain\Contracts\UserIdentityRepository;
use Illuminate\Support\Facades\Hash;

final class RegisterUserUseCase
{
    public function __construct(private readonly UserIdentityRepository $userIdentityRepository)
    {
    }

    public function execute(RegisterUserData $data): User
    {
        return $this->userIdentityRepository->create([
            'name' => $data->name,
            'role' => 'user',
            'email' => $data->email,
            'password' => Hash::make($data->password),
        ]);
    }
}
