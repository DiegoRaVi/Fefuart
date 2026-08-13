<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * D23 — `users.role_id` va NOT NULL y sin DEFAULT a proposito: un insert
 * incompleto debe fallar en vez de conceder un rol por omision. En v1 la
 * columna era un varchar con el valor por defecto puesto en el controller,
 * y de ahi salio SEC-001.
 */
it('rechaza en base de datos un usuario sin rol', function () {
    expect(fn () => DB::table('users')->insert([
        'name' => 'Sin rol',
        'email' => 'sin-rol@fefuart.test',
        'password' => 'da-igual',
    ]))->toThrow(QueryException::class);
});

it('rechaza un role_id que no existe en la tabla roles', function () {
    expect(fn () => DB::table('users')->insert([
        'name' => 'Rol inventado',
        'email' => 'rol-inventado@fefuart.test',
        'password' => 'da-igual',
        'role_id' => 99,
    ]))->toThrow(QueryException::class);
});

it('siembra los dos roles con sus ids fijos', function () {
    expect(DB::table('roles')->pluck('name', 'id')->all())
        ->toBe([1 => 'customer', 2 => 'admin']);
});

it('devuelve el rol como enum al leer el usuario', function () {
    $user = User::factory()->create();

    expect($user->fresh()->role_id)->toBe(UserRole::Customer);
});

it('reconoce a la administradora', function () {
    expect(User::factory()->admin()->create()->isAdmin())->toBeTrue()
        ->and(User::factory()->create()->isAdmin())->toBeFalse();
});
