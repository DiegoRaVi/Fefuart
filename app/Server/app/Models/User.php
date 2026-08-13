<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * N19: implementa MustVerifyEmail. En v1 la columna `email_verified_at`
 * existia desde la primera migracion y no se usaba.
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * SEC-001: `role_id` NO figura aqui. En v1 el equivalente (`role`) si lo
     * hacia, y el registro lo tomaba del cuerpo de la peticion
     * (`$request->get('role', 'user')`), de modo que cualquiera podia crearse
     * una cuenta de administrador enviando `"role": "admin"` a un endpoint
     * publico.
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
     * `role_id` se lee siempre como enum, nunca como numero (D23). Es la
     * columna real —la clave foranea a `roles`— y no se duplica en un
     * accesor `role`: un mismo dato con dos nombres acaba divergiendo.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role_id' => UserRole::class,
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role_id === UserRole::Admin;
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
