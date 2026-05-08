<?php

namespace App\Modules\IdentityAccess\Application\DTOs;

final class LoginData
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            email: $data['email'],
            password: $data['password'],
        );
    }

    public function toCredentials(): array
    {
        return [
            'email' => $this->email,
            'password' => $this->password,
        ];
    }
}
