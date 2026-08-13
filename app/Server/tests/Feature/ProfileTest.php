<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withHeader('Referer', config('app.url'));
    Notification::fake();
});

it('exige sesion para ver el perfil', function () {
    $this->getJson(route('profile.show'))->assertUnauthorized();
});

it('actualiza el nombre', function () {
    $user = User::factory()->create(['name' => 'Nombre viejo']);

    $this->actingAs($user)
        ->patchJson(route('profile.update'), ['name' => 'Felicitas Varela'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Felicitas Varela');
});

/**
 * SEC-001 — regresion por otra via. El perfil no puede ser una puerta
 * trasera para cambiarse el rol: `role` no esta en las reglas del
 * UpdateProfileRequest ni es fillable en el modelo.
 */
it('ignora el rol enviado al actualizar el perfil', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson(route('profile.update'), ['name' => 'Intruso', 'role' => 'admin'])
        ->assertOk()
        ->assertJsonPath('data.role', 'user');

    expect($user->fresh()->role)->toBe(UserRole::User);
});

it('invalida la verificacion al cambiar de correo', function () {
    $user = User::factory()->create(['email' => 'viejo@fefuart.test']);

    expect($user->hasVerifiedEmail())->toBeTrue();

    $this->actingAs($user)
        ->patchJson(route('profile.update'), ['email' => 'nuevo@fefuart.test'])
        ->assertOk();

    $user->refresh();

    expect($user->email)->toBe('nuevo@fefuart.test')
        ->and($user->hasVerifiedEmail())->toBeFalse();

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('rechaza un correo que ya usa otra cuenta', function () {
    User::factory()->create(['email' => 'ocupado@fefuart.test']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson(route('profile.update'), ['email' => 'ocupado@fefuart.test'])
        ->assertStatus(422)->assertJsonValidationErrors('email');
});

it('cambia la contrasena con la actual correcta', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->putJson(route('profile.password'), [
        'current_password' => 'password',
        'password' => 'contrasena-nueva',
        'password_confirmation' => 'contrasena-nueva',
    ])->assertOk();

    expect(Hash::check('contrasena-nueva', $user->fresh()->password))->toBeTrue();
});

/**
 * Sin exigir la contrasena actual, una sesion secuestrada bastaria para
 * expulsar al dueno de su propia cuenta.
 */
it('rechaza el cambio de contrasena sin la actual', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->putJson(route('profile.password'), [
        'current_password' => 'no-es-esta',
        'password' => 'contrasena-nueva',
        'password_confirmation' => 'contrasena-nueva',
    ])->assertStatus(422)->assertJsonValidationErrors('current_password');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});
