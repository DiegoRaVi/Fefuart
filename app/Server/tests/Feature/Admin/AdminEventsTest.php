<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->withHeader('Referer', config('app.url'));

    $this->admin = User::factory()->admin()->create();
    $this->cliente = User::factory()->create(['email' => 'marta@fefuart.test']);
    $this->otroCliente = User::factory()->create(['email' => 'luis@fefuart.test']);
});

it('exige sesion', function () {
    $this->getJson(route('admin.events.index'))->assertUnauthorized();
});

it('cierra el backoffice de eventos a un cliente', function () {
    $evento = Event::factory()->for($this->cliente)->create();

    $this->actingAs($this->cliente)->getJson(route('admin.events.index'))->assertForbidden();
    $this->getJson(route('admin.events.show', $evento))->assertForbidden();
    $this->postJson(route('admin.events.status', $evento), ['status' => 'rejected'])->assertForbidden();
});

it('lista las solicitudes de todos los clientes', function () {
    Event::factory()->for($this->cliente)->create();
    Event::factory()->for($this->otroCliente)->create();

    $this->actingAs($this->admin)->getJson(route('admin.events.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 2);
});

it('filtra por estado', function () {
    Event::factory()->for($this->cliente)->create();
    Event::factory()->for($this->cliente)->status(EventStatus::Rejected)->create();

    $this->actingAs($this->admin)
        ->getJson(route('admin.events.index', ['status' => 'requested']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'requested');
});

it('rechaza un estado inventado en el filtro', function () {
    $this->actingAs($this->admin)
        ->getJson(route('admin.events.index', ['status' => 'inventado']))
        ->assertStatus(422);
});

/**
 * N14 — sin invitados y duracion no se puede presupuestar, asi que es lo
 * primero que la administradora necesita ver.
 */
it('ensena los datos con los que se presupuesta y quien lo pide', function () {
    $evento = Event::factory()->for($this->cliente)->create([
        'guest_count' => 120,
        'duration_hours' => 4,
        'event_type' => 'boda',
    ]);

    $this->actingAs($this->admin)->getJson(route('admin.events.show', $evento))
        ->assertOk()
        ->assertJsonPath('data.guest_count', 120)
        ->assertJsonPath('data.duration_hours', 4)
        ->assertJsonPath('data.event_type', 'boda')
        ->assertJsonPath('data.customer.email', 'marta@fefuart.test');
});

/** SEC-009 — el email del cliente no sale por las rutas de cliente. */
it('no filtra los datos del cliente en las rutas de cliente', function () {
    $evento = Event::factory()->for($this->cliente)->create();

    $respuesta = $this->actingAs($this->cliente)
        ->getJson(route('events.show', $evento))
        ->assertOk();

    expect($respuesta->json('data'))->not->toHaveKey('customer');
});

describe('el cambio de estado', function () {
    it('deja rechazar una solicitud', function () {
        $evento = Event::factory()->for($this->cliente)->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.events.status', $evento), ['status' => 'rejected'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        expect($evento->fresh()->status)->toBe(EventStatus::Rejected);
    });

    it('deja completar un evento confirmado', function () {
        $evento = Event::factory()->for($this->cliente)->status(EventStatus::Confirmed)->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.events.status', $evento), ['status' => 'completed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');
    });

    it('rechaza un salto que el enum no permite', function () {
        $evento = Event::factory()->for($this->cliente)->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.events.status', $evento), ['status' => 'completed'])
            ->assertStatus(422);

        expect($evento->fresh()->status)->toBe(EventStatus::Requested);
    });

    /**
     * D27 — presupuestar y confirmar necesitan importe y señal, y esas
     * columnas son de la Fase 5. Un evento en `quoted` sin importe no es un
     * presupuesto: es un estado a medias que ademas dejaria al cliente sin
     * poder aceptar nada.
     */
    it('no deja presupuestar todavia, que eso necesita importe', function () {
        $evento = Event::factory()->for($this->cliente)->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.events.status', $evento), ['status' => 'quoted'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        expect($evento->fresh()->status)->toBe(EventStatus::Requested);
    });

    it('no deja confirmar a mano sin pasar por la señal', function () {
        $evento = Event::factory()->for($this->cliente)->status(EventStatus::Accepted)->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.events.status', $evento), ['status' => 'confirmed'])
            ->assertStatus(422);
    });
});

/** PERF-004 — v1 devolvia todos los eventos sin paginar y con el user cargado. */
it('no hace mas consultas por listar mas eventos', function () {
    $this->actingAs($this->admin);

    $crear = function (int $cuantos) {
        Event::factory()->count($cuantos)->for($this->cliente)->create();
    };

    $medir = function (): int {
        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->getJson(route('admin.events.index'))
            ->assertOk()
            ->assertJsonPath('data.0.customer.email', 'marta@fefuart.test');

        return count(DB::getQueryLog());
    };

    $crear(3);
    $medir(); // Calentamiento: la sesion vive en base de datos.

    $conTres = $medir();
    $crear(12);

    expect($medir())->toBe($conTres);
});
