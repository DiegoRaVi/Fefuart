<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->duenno = User::factory()->create();
    $this->intruso = User::factory()->create();
    $this->admin = User::factory()->admin()->create();

    $this->evento = Event::factory()->for($this->duenno)->create();
});

/**
 * Nota de alcance: los endpoints de eventos son de la Fase 4 (D27), asi que
 * estos tests ejercitan la Policy directamente. El IDOR a nivel HTTP se
 * anade alli, junto con `POST /api/events`.
 */
it('deja al duenno ver su evento', function () {
    expect($this->duenno->can('view', $this->evento))->toBeTrue();
});

it('no deja ver el evento de otro', function () {
    expect($this->intruso->can('view', $this->evento))->toBeFalse();
});

it('deja a la administradora ver cualquier evento', function () {
    expect($this->admin->can('view', $this->evento))->toBeTrue();
});

/**
 * SEC-010 — regresion.
 *
 * En v1 `EventController::updateEvent` hacia
 * `$event->update($request->only([... 'status']))`, de modo que el
 * propietario podia pasar su propio evento de `pending` a `confirmed`. Era
 * latente solo porque ese metodo no existia y la ruta respondia 500
 * (BUG-002): se habria activado al arreglar el bug.
 */
it('no deja al cliente confirmar su propio evento', function () {
    expect($this->duenno->can('confirm', $this->evento))->toBeFalse()
        ->and($this->admin->can('confirm', $this->evento))->toBeTrue();
});

it('no deja al cliente presupuestar su propio evento', function () {
    expect($this->duenno->can('quote', $this->evento))->toBeFalse()
        ->and($this->admin->can('quote', $this->evento))->toBeTrue();
});

/**
 * El cliente si puede corregir los datos de su solicitud mientras la artista
 * no la haya presupuestado.
 */
it('deja al duenno editar su solicitud antes de que la presupuesten', function () {
    expect($this->duenno->can('update', $this->evento))->toBeTrue();

    $this->evento->status = EventStatus::Quoted;
    $this->evento->save();

    expect($this->duenno->can('update', $this->evento->fresh()))->toBeFalse();
});

it('no deja editar la solicitud de otro', function () {
    expect($this->intruso->can('update', $this->evento))->toBeFalse();
});

/**
 * SEC-010 por la via del modelo: aunque alguien encuentre un endpoint que
 * haga `fill()`, `status` no es asignable en masa.
 */
it('no admite el estado por asignacion masiva', function () {
    $evento = Event::factory()->for($this->duenno)->create();

    $evento->fill(['status' => EventStatus::Confirmed, 'title' => 'Renombrada']);

    expect($evento->status)->toBe(EventStatus::Requested)
        ->and($evento->title)->toBe('Renombrada');
});

/**
 * N14 — para presupuestar hacen falta invitados, duracion y tipo de evento,
 * que v1 no pedia.
 */
it('guarda lo que hace falta para presupuestar', function () {
    $evento = Event::factory()->for($this->duenno)->create([
        'guest_count' => 120,
        'duration_hours' => 4,
        'event_type' => 'boda',
    ]);

    expect($evento->fresh()->guest_count)->toBe(120)
        ->and($evento->fresh()->duration_hours)->toBe(4)
        ->and($evento->fresh()->event_type)->toBe('boda');
});

/**
 * El presupuesto y la señal siguen siendo de la Fase 5: sin importe, un
 * evento en `quoted` es un estado a medias que ademas deja al cliente sin
 * poder aceptar nada.
 *
 * El `confirmed_slot` de N16 si esta ya —ver AgendaTest—: la portabilidad
 * que D27 aplazaba resulto ser una sola diferencia entre motores, y no
 * justificaba dejar la agenda sin proteger mientras tanto.
 */
it('deja el presupuesto para la fase que lo usa', function () {
    expect(Schema::hasColumn('events', 'quoted_amount'))->toBeFalse()
        ->and(Schema::hasColumn('events', 'deposit_amount'))->toBeFalse()
        ->and(Schema::hasColumn('events', 'confirmed_slot'))->toBeTrue();
});

/**
 * DB-003 — el backoffice lista eventos por estado y fecha.
 */
it('indexa los eventos por estado y fecha', function () {
    $columnas = collect(Schema::getIndexes('events'))->pluck('columns');

    expect($columnas)->toContain(['status', 'event_date']);
});
