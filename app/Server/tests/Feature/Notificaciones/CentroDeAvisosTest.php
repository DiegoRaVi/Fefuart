<?php

use App\Models\Event;
use App\Models\User;
use App\Notifications\QuoteReady;

/**
 * D10 — `GET /api/notifications` y `PATCH /api/notifications/{id}/read`.
 *
 * El aviso se resuelve **por la relacion del usuario en sesion**, no
 * buscandolo en toda la tabla para comparar despues de quien es. Es la misma
 * distincion que cierra SEC-003, SEC-004, SEC-008 y SEC-009: en v1 la
 * pertenencia se comprobaba a mano en cada sitio, y donde se olvidaba
 * quedaba un IDOR.
 */
beforeEach(function () {
    $this->withHeader('Referer', config('app.url'));

    $this->cliente = User::factory()->create();
    $this->otro = User::factory()->create();
});

/** Un aviso real, con el `toArray()` que consume la SPA. */
function avisarA(User $usuario): void
{
    $evento = Event::factory()->quoted()->for($usuario)->create();

    $usuario->notify(new QuoteReady($evento));
}

it('exige sesion', function () {
    $this->getJson('/api/notifications')->assertUnauthorized();
});

it('devuelve los avisos del usuario en sesion', function () {
    avisarA($this->cliente);

    $this->actingAs($this->cliente)
        ->getJson('/api/notifications')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.tipo', 'presupuesto_listo')
        ->assertJsonPath('data.0.enlace', '/live-art#mias')
        ->assertJsonPath('data.0.leido', false);
});

/** SEC-008 llevado a los avisos: los de otra persona no se ven. */
it('no devuelve los avisos de otro', function () {
    avisarA($this->otro);

    $this->actingAs($this->cliente)
        ->getJson('/api/notifications')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

/** El contador es lo que pinta la cabecera, asi que viaja con la lista. */
it('cuenta los avisos sin leer', function () {
    avisarA($this->cliente);
    avisarA($this->cliente);

    $this->actingAs($this->cliente)
        ->getJson('/api/notifications')
        ->assertOk()
        ->assertJsonPath('meta.no_leidos', 2);
});

it('ordena el mas reciente primero', function () {
    avisarA($this->cliente);
    $primero = $this->cliente->notifications()->sole();
    $primero->created_at = now()->subDay();
    $primero->save();

    avisarA($this->cliente);

    $respuesta = $this->actingAs($this->cliente)->getJson('/api/notifications')->assertOk();

    expect($respuesta->json('data.1.id'))->toBe($primero->id);
});

describe('marcar como leido', function () {
    it('pone la fecha de lectura', function () {
        avisarA($this->cliente);
        $aviso = $this->cliente->notifications()->sole();

        $this->actingAs($this->cliente)
            ->patchJson("/api/notifications/{$aviso->id}/read")
            ->assertOk()
            ->assertJsonPath('data.leido', true);

        expect($aviso->fresh()->read_at)->not->toBeNull();
    });

    /**
     * El IDOR. Responde **404 y no 403**: que el aviso exista no es asunto
     * de quien pregunta, y un 403 se lo confirmaria.
     */
    it('no deja marcar el aviso de otro', function () {
        avisarA($this->otro);
        $ajeno = $this->otro->notifications()->sole();

        $this->actingAs($this->cliente)
            ->patchJson("/api/notifications/{$ajeno->id}/read")
            ->assertNotFound();

        expect($ajeno->fresh()->read_at)->toBeNull();
    });

    it('exige sesion', function () {
        avisarA($this->cliente);
        $aviso = $this->cliente->notifications()->sole();

        $this->patchJson("/api/notifications/{$aviso->id}/read")->assertUnauthorized();
    });

    /** Marcar dos veces no cambia la fecha original. */
    it('no reescribe la fecha si ya estaba leido', function () {
        avisarA($this->cliente);
        $aviso = $this->cliente->notifications()->sole();

        $this->actingAs($this->cliente)->patchJson("/api/notifications/{$aviso->id}/read")->assertOk();
        $primeraLectura = $aviso->fresh()->read_at;

        $this->travel(1)->hours();

        $this->actingAs($this->cliente)->patchJson("/api/notifications/{$aviso->id}/read")->assertOk();

        expect($aviso->fresh()->read_at->timestamp)->toBe($primeraLectura->timestamp);
    });

    it('descuenta del contador de sin leer', function () {
        avisarA($this->cliente);
        avisarA($this->cliente);
        $aviso = $this->cliente->notifications()->first();

        $this->actingAs($this->cliente)->patchJson("/api/notifications/{$aviso->id}/read")->assertOk();

        $this->actingAs($this->cliente)
            ->getJson('/api/notifications')
            ->assertJsonPath('meta.no_leidos', 1);
    });
});
