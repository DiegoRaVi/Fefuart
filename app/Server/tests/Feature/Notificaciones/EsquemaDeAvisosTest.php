<?php

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * D10 — el centro de avisos se apoya en la tabla nativa de Laravel
 * (`notifications:table`), no en una propia.
 *
 * Es la excepcion declarada a D25: las migraciones de v2 se reescriben en
 * sitio para que el arbol se lea como el esquema objetivo, pero esta la
 * genera el framework y su forma la fija el, no nosotros. Reescribirla seria
 * quedarse con una copia que hay que mantener a mano cada vez que cambie
 * `DatabaseNotification`.
 */
it('tiene la tabla de avisos con la forma que espera Laravel', function () {
    expect(Schema::hasTable('notifications'))->toBeTrue();

    expect(Schema::hasColumns('notifications', [
        'id',
        'type',
        'notifiable_type',
        'notifiable_id',
        'data',
        'read_at',
    ]))->toBeTrue();
});

/**
 * La clave primaria es un UUID y no un entero autoincremental. Importa para
 * el endpoint: `PATCH /api/notifications/{id}/read` recibe una cadena, y un
 * aviso ajeno no se encuentra por tanteo incremental como si se numeraran
 * del 1 en adelante.
 */
it('identifica cada aviso por uuid', function () {
    $usuario = User::factory()->create();

    $usuario->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\Prueba',
        'data' => ['titulo' => 'Hola'],
    ]);

    $aviso = $usuario->notifications()->sole();

    expect($aviso->id)->toBeString()
        ->and($aviso->id)->toMatch('/^[0-9a-f-]{36}$/')
        ->and($aviso->read_at)->toBeNull();
});
