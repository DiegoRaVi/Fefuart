<?php

use App\Models\Setting;
use App\Models\User;
use App\Services\SettingsService;

/**
 * N15 — los ajustes que la artista cambia sin tocar codigo.
 *
 * Que claves existen no lo decide la peticion sino SettingsService. Sin eso,
 * `PATCH /api/admin/settings` seria un almacen de claves arbitrarias
 * escribible desde fuera, que es la version de esta tabla del `role` de
 * SEC-001.
 */
beforeEach(function () {
    $this->withHeader('Referer', config('app.url'));

    $this->admin = User::factory()->admin()->create();
});

it('devuelve los ajustes con sus limites', function () {
    $this->actingAs($this->admin)->getJson('/api/admin/settings')
        ->assertOk()
        ->assertJsonPath('data.deposit_percentage.valor', 30)
        ->assertJsonPath('data.deposit_percentage.max', 100)
        ->assertJsonPath('data.quote_validity_days.valor', 14);
});

it('guarda un ajuste nuevo', function () {
    $this->actingAs($this->admin)->patchJson('/api/admin/settings', [
        'deposit_percentage' => 45,
    ])
        ->assertOk()
        ->assertJsonPath('data.deposit_percentage.valor', 45);

    expect(app(SettingsService::class)->porcentajeDeSenal())->toBe(45)
        ->and(Setting::query()->count())->toBe(1);
});

it('cambia uno sin tocar el otro', function () {
    $this->actingAs($this->admin)->patchJson('/api/admin/settings', ['quote_validity_days' => 30]);

    $ajustes = app(SettingsService::class);

    expect($ajustes->diasDeValidezDelPresupuesto())->toBe(30)
        ->and($ajustes->porcentajeDeSenal())->toBe(30);
});

it('rechaza un valor fuera de sus limites', function () {
    $this->actingAs($this->admin)->patchJson('/api/admin/settings', ['deposit_percentage' => 150])
        ->assertJsonValidationErrors('ajustes');

    expect(app(SettingsService::class)->porcentajeDeSenal())->toBe(30);
});

/** Lo que no esta en la lista no se guarda, aunque venga bien formado. */
it('ignora una clave que no existe', function () {
    $this->actingAs($this->admin)->patchJson('/api/admin/settings', [
        'precio_secreto' => 1,
    ])->assertJsonValidationErrors('ajustes');

    expect(Setting::query()->count())->toBe(0);
});

it('rechaza un valor que no es entero', function () {
    $this->actingAs($this->admin)->patchJson('/api/admin/settings', [
        'deposit_percentage' => 'gratis',
    ])->assertJsonValidationErrors('deposit_percentage');
});

describe('quien puede', function () {
    it('no deja a un cliente ver los ajustes', function () {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/admin/settings')
            ->assertForbidden();
    });

    it('no deja a un cliente cambiarlos', function () {
        $this->actingAs(User::factory()->create())
            ->patchJson('/api/admin/settings', ['deposit_percentage' => 0])
            ->assertForbidden();

        expect(Setting::query()->count())->toBe(0);
    });

    /** ARCH-002 — un invitado recibe 401 y un cliente 403, no 403 los dos. */
    it('devuelve 401 a un invitado', function () {
        $this->getJson('/api/admin/settings')->assertUnauthorized();
    });
});
