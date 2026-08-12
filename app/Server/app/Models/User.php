<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * SEC-001: `role` NO figura aqui. En v1 si lo hacia, y el registro lo
     * tomaba del cuerpo de la peticion (`$request->get('role', 'user')`),
     * de modo que cualquiera podia crearse una cuenta de administrador
     * enviando `"role": "admin"` a un endpoint publico.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function events()
    {
        return $this->hasMany(Event::class, 'user_id');
    }
}
