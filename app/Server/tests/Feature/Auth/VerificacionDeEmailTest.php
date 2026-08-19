<?php

use App\Models\User;
use Illuminate\Support\Facades\URL;

/**
 * N19 — el enlace de verificacion que llega por correo.
 *
 * No tenia ni un solo test, y por eso llevaba roto desde la Fase 1: la ruta
 * se declaro **sin `auth`** a proposito —quien pincha desde su gestor de
 * correo puede no traer cookie de sesion, y lo que autoriza es la firma de
 * la URL— pero el controlador pedia un `EmailVerificationRequest`, cuyo
 * `authorize()` hace `$this->user()->getKey()`. Sin sesion, `user()` es null
 * y la respuesta era un 500.
 *
 * El caso roto era justo el normal: abrir el enlace desde el correo.
 */
function enlaceDeVerificacion(User $usuario, ?string $hash = null): string
{
    return URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        [
            'id' => $usuario->getKey(),
            'hash' => $hash ?? sha1($usuario->getEmailForVerification()),
        ],
    );
}

it('verifica el correo sin sesion iniciada', function () {
    $usuario = User::factory()->unverified()->create();

    $this->get(enlaceDeVerificacion($usuario))
        ->assertRedirect(config('app.frontend_url').'/perfil?verificado=1');

    expect($usuario->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('verifica el correo tambien con sesion iniciada', function () {
    $usuario = User::factory()->unverified()->create();

    $this->actingAs($usuario)
        ->get(enlaceDeVerificacion($usuario))
        ->assertRedirect(config('app.frontend_url').'/perfil?verificado=1');

    expect($usuario->fresh()->hasVerifiedEmail())->toBeTrue();
});

/**
 * El hash es `sha1(correo)`, asi que un enlace emitido antes de un cambio de
 * direccion deja de valer. Sin esta comprobacion, el enlace viejo verificaria
 * una direccion que ya nadie ha demostrado tener.
 */
it('rechaza un enlace cuyo hash no corresponde al correo', function () {
    $usuario = User::factory()->unverified()->create();

    $this->get(enlaceDeVerificacion($usuario, hash: sha1('otro@fefuart.test')))
        ->assertForbidden();

    expect($usuario->fresh()->hasVerifiedEmail())->toBeFalse();
});

/** Sin firma valida no se mira nada: es lo unico que autoriza esta ruta. */
it('rechaza un enlace sin firmar', function () {
    $usuario = User::factory()->unverified()->create();

    $this->get(route('verification.verify', [
        'id' => $usuario->getKey(),
        'hash' => sha1($usuario->getEmailForVerification()),
    ]))->assertForbidden();

    expect($usuario->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('rechaza un enlace caducado', function () {
    $usuario = User::factory()->unverified()->create();

    $enlace = URL::temporarySignedRoute(
        'verification.verify',
        now()->subMinute(),
        ['id' => $usuario->getKey(), 'hash' => sha1($usuario->getEmailForVerification())],
    );

    $this->get($enlace)->assertForbidden();

    expect($usuario->fresh()->hasVerifiedEmail())->toBeFalse();
});

/** Pinchar dos veces no es un error: el segundo clic no cambia nada. */
it('no falla si el correo ya estaba verificado', function () {
    $usuario = User::factory()->create();
    $cuando = $usuario->email_verified_at;

    $this->get(enlaceDeVerificacion($usuario))->assertRedirect();

    expect($usuario->fresh()->email_verified_at->timestamp)->toBe($cuando->timestamp);
});
