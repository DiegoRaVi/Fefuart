<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;

beforeEach(function () {
    $this->withHeader('Referer', config('app.url'));

    $this->user = User::factory()->create();
    $this->intruso = User::factory()->create();
    $this->admin = User::factory()->admin()->create();
});

function solicitud(array $extra = []): array
{
    return array_merge([
        'title' => 'Boda de Marta y Luis',
        'description' => 'Queremos dibujo en directo durante el coctel.',
        'phone' => '600123456',
        'event_date' => now()->addMonths(6)->format('Y-m-d'),
        'schedule' => 'evening',
        'location' => 'Finca El Olivar, Toledo',
        'guest_count' => 120,
        'duration_hours' => 4,
        'event_type' => 'boda',
    ], $extra);
}

it('exige sesion para pedir un evento', function () {
    $this->postJson(route('events.store'), solicitud())->assertUnauthorized();
});

it('crea la solicitud con los datos que hacen falta para presupuestar', function () {
    $this->actingAs($this->user)
        ->postJson(route('events.store'), solicitud())
        ->assertCreated()
        ->assertJsonPath('data.title', 'Boda de Marta y Luis')
        // N14 — invitados y duracion son los que determinan la tarifa.
        ->assertJsonPath('data.guest_count', 120)
        ->assertJsonPath('data.duration_hours', 4)
        ->assertJsonPath('data.event_type', 'boda')
        ->assertJsonPath('data.status', 'requested');
});

/**
 * SEC-010 — regresion, ahora sobre HTTP.
 *
 * En v1 `EventController::updateEvent` hacia
 * `$event->update($request->only([… 'status']))`, sin restringir el rol ni
 * los valores admitidos, de modo que el propietario podia pasar su propio
 * evento de `pending` a `confirmed` y colarse en la agenda. Era latente solo
 * porque ese metodo no existia y la ruta respondia 500 (BUG-002).
 *
 * Ahora la ruta existe —eso es justo lo que activaba el hallazgo— y el
 * estado sigue sin poder tocarse desde el cliente.
 */
it('no deja al cliente confirmarse su propio evento al crearlo', function () {
    $this->actingAs($this->user)
        ->postJson(route('events.store'), solicitud(['status' => 'confirmed']))
        ->assertCreated()
        ->assertJsonPath('data.status', 'requested');

    expect(Event::query()->sole()->status)->toBe(EventStatus::Requested);
});

it('no deja al cliente confirmarse su propio evento al editarlo', function () {
    $evento = Event::factory()->for($this->user)->create();

    $this->actingAs($this->user)
        ->patchJson(route('events.update', $evento), [
            'title' => 'Boda de Marta y Luis',
            'status' => 'confirmed',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'requested')
        ->assertJsonPath('data.title', 'Boda de Marta y Luis');

    expect($evento->fresh()->status)->toBe(EventStatus::Requested);
});

/**
 * BUG-002 — regresion.
 *
 * `routes/api.php:35` apuntaba a `EventController@updateEvent`, un metodo
 * que no existia, asi que `PATCH /api/events/{id}` respondia 500 y el
 * usuario no podia corregir su propia solicitud.
 */
it('deja al cliente corregir su solicitud', function () {
    $evento = Event::factory()->for($this->user)->create(['location' => 'Sin decidir']);

    $this->actingAs($this->user)
        ->patchJson(route('events.update', $evento), ['location' => 'Finca El Olivar, Toledo'])
        ->assertOk()
        ->assertJsonPath('data.location', 'Finca El Olivar, Toledo');
});

it('no deja corregirla una vez presupuestada', function () {
    $evento = Event::factory()->for($this->user)->status(EventStatus::Quoted)->create();

    $this->actingAs($this->user)
        ->patchJson(route('events.update', $evento), ['location' => 'Otro sitio'])
        ->assertForbidden();
});

describe('el evento de cada cual', function () {
    it('lista solo los mios', function () {
        Event::factory()->for($this->user)->create();
        Event::factory()->for($this->intruso)->create();

        $this->actingAs($this->user)->getJson(route('events.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('no deja ver el de otro', function () {
        $ajeno = Event::factory()->for($this->intruso)->create();

        $this->actingAs($this->user)->getJson(route('events.show', $ajeno))
            ->assertForbidden();
    });

    it('no deja editar el de otro', function () {
        $ajeno = Event::factory()->for($this->intruso)->create();

        $this->actingAs($this->user)
            ->patchJson(route('events.update', $ajeno), ['location' => 'Mi sitio'])
            ->assertForbidden();

        expect($ajeno->fresh()->location)->not->toBe('Mi sitio');
    });

    it('no deja cancelar el de otro', function () {
        $ajeno = Event::factory()->for($this->intruso)->create();

        $this->actingAs($this->user)
            ->postJson(route('events.cancel', $ajeno))
            ->assertForbidden();

        expect($ajeno->fresh()->status)->toBe(EventStatus::Requested);
    });

    it('deja a la administradora ver cualquiera', function () {
        $evento = Event::factory()->for($this->user)->create();

        $this->actingAs($this->admin)->getJson(route('events.show', $evento))
            ->assertOk()
            ->assertJsonPath('data.id', $evento->id);
    });
});

it('deja al cliente cancelar su solicitud', function () {
    $evento = Event::factory()->for($this->user)->create();

    $this->actingAs($this->user)->postJson(route('events.cancel', $evento))
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');
});

it('rechaza cancelar un evento ya completado', function () {
    $evento = Event::factory()->for($this->user)->status(EventStatus::Completed)->create();

    $this->actingAs($this->admin)->postJson(route('events.cancel', $evento))
        ->assertStatus(422);
});

describe('validacion de la solicitud', function () {
    it('exige titulo, fecha, franja y lugar', function () {
        $this->actingAs($this->user)
            ->postJson(route('events.store'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'event_date', 'schedule', 'location']);
    });

    /** N17 — las solicitudes si pueden solaparse; solo una llega a confirmarse. */
    it('admite dos solicitudes para la misma fecha', function () {
        $fecha = now()->addMonths(6)->format('Y-m-d');

        $this->actingAs($this->user)
            ->postJson(route('events.store'), solicitud(['event_date' => $fecha]))
            ->assertCreated();

        $this->actingAs($this->intruso)
            ->postJson(route('events.store'), solicitud(['event_date' => $fecha]))
            ->assertCreated();
    });

    it('no admite una fecha que ya ha pasado', function () {
        $this->actingAs($this->user)
            ->postJson(route('events.store'), solicitud([
                'event_date' => now()->subDay()->format('Y-m-d'),
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('event_date');
    });

    it('no admite una franja inventada', function () {
        $this->actingAs($this->user)
            ->postJson(route('events.store'), solicitud(['schedule' => 'madrugada']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('schedule');
    });
});

/**
 * SEC-005 — el titulo y la descripcion los escribe el cliente y los lee la
 * administradora en el backoffice. React los escapa, pero el dato tiene que
 * llegar intacto: escaparlo tambien aqui produciria doble escapado.
 */
it('guarda el titulo tal cual lo escribe el cliente', function () {
    $payload = '<img src=x onerror="alert(1)">';

    $this->actingAs($this->user)
        ->postJson(route('events.store'), solicitud(['title' => $payload]))
        ->assertCreated()
        ->assertJsonPath('data.title', $payload);
});
