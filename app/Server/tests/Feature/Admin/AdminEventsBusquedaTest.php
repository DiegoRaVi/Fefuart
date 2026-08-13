<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;

beforeEach(function () {
    $this->withHeader('Referer', config('app.url'));

    $this->admin = User::factory()->admin()->create();
    $this->marta = User::factory()->create(['name' => 'Marta Ruiz', 'email' => 'marta@fefuart.test']);
    $this->luis = User::factory()->create(['name' => 'Luis Gomez', 'email' => 'luis@otrositio.com']);
});

function buscarEventos(string $q, array $extra = []): array
{
    return test()->actingAs(test()->admin)
        ->getJson(route('admin.events.index', array_merge(['q' => $q], $extra)))
        ->assertOk()
        ->json('data');
}

describe('el buscador de eventos', function () {
    it('encuentra por el titulo', function () {
        Event::factory()->for($this->marta)->create(['title' => 'Boda de Marta y Luis']);
        Event::factory()->for($this->luis)->create(['title' => 'Comunion de Sara']);

        expect(buscarEventos('boda'))->toHaveCount(1);
    });

    /** Un evento se recuerda por donde era tanto como por quien lo pidio. */
    it('encuentra por el lugar', function () {
        Event::factory()->for($this->marta)->create(['location' => 'Finca El Olivar, Toledo']);
        Event::factory()->for($this->luis)->create(['location' => 'Hotel Miramar, Cadiz']);

        expect(buscarEventos('olivar'))->toHaveCount(1);
    });

    it('encuentra por quien lo pidio', function () {
        Event::factory()->for($this->marta)->create();
        Event::factory()->for($this->luis)->create();

        expect(buscarEventos('marta@'))->toHaveCount(1);
    });

    it('encuentra por el telefono', function () {
        Event::factory()->for($this->marta)->create(['phone' => '600999888']);
        Event::factory()->for($this->luis)->create(['phone' => '611111111']);

        expect(buscarEventos('600999'))->toHaveCount(1);
    });

    it('se combina con el filtro de estado en vez de sumarse', function () {
        Event::factory()->for($this->marta)->create(['title' => 'Boda de Marta']);
        Event::factory()->for($this->marta)->status(EventStatus::Rejected)->create([
            'title' => 'Boda de Marta que no salio',
        ]);

        $resultado = buscarEventos('boda', ['status' => 'requested']);

        expect($resultado)->toHaveCount(1)
            ->and($resultado[0]['status'])->toBe('requested');
    });
});

describe('el rango de fechas de eventos', function () {
    it('acota por la fecha del evento, no por la de la solicitud', function () {
        Event::factory()->for($this->marta)->create([
            'event_date' => now()->addMonths(2)->toDateString(),
            'title' => 'Dentro de dos meses',
        ]);
        Event::factory()->for($this->marta)->create([
            'event_date' => now()->addYear()->toDateString(),
            'title' => 'Dentro de un ano',
        ]);

        $resultado = $this->actingAs($this->admin)
            ->getJson(route('admin.events.index', [
                'desde' => now()->toDateString(),
                'hasta' => now()->addMonths(6)->toDateString(),
            ]))
            ->assertOk()->json('data');

        expect($resultado)->toHaveCount(1)
            ->and($resultado[0]['title'])->toBe('Dentro de dos meses');
    });

    it('rechaza un rango al reves', function () {
        $this->actingAs($this->admin)
            ->getJson(route('admin.events.index', [
                'desde' => now()->addMonth()->toDateString(),
                'hasta' => now()->toDateString(),
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('hasta');
    });
});
