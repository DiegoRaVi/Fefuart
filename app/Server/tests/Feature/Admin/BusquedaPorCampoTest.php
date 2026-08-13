<?php

use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\User;

beforeEach(function () {
    $this->withHeader('Referer', config('app.url'));

    $this->admin = User::factory()->admin()->create();

    $this->marta = User::factory()->create([
        'name' => 'Marta Ruiz',
        'email' => 'marta@fefuart.test',
    ]);

    $this->luis = User::factory()->create([
        'name' => 'Luis Gomez',
        'email' => 'luis@otrositio.com',
    ]);
});

function porCampo(string $campo, string $q, array $extra = []): array
{
    return test()->actingAs(test()->admin)
        ->getJson(route('admin.orders.index', array_merge(
            ['buscar_por' => $campo, 'q' => $q],
            $extra,
        )))
        ->assertOk()
        ->json('data');
}

/**
 * El motivo del modal: la caja rapida es comoda pero imprecisa. Buscar «600»
 * en todo mezcla telefonos, numeros de pedido y cualquier nombre con esas
 * cifras. Acotando el campo, lo que sale es exactamente lo que se pidio.
 */
describe('la busqueda acotada a un campo', function () {
    it('por numero no mira el telefono', function () {
        $primero = Order::factory()->for($this->marta)->placed()->create([
            'shipping_phone' => '600123456',
        ]);
        Order::factory()->for($this->luis)->placed()->create([
            // Contiene el numero del otro pedido, pero no es su numero.
            'shipping_phone' => "6001{$primero->id}9999",
        ]);

        $resultado = porCampo('numero', (string) $primero->id);

        expect($resultado)->toHaveCount(1)
            ->and($resultado[0]['id'])->toBe($primero->id);
    });

    it('por telefono no mira el numero de pedido', function () {
        Order::factory()->for($this->marta)->placed()->create(['shipping_phone' => '600999888']);
        Order::factory()->for($this->luis)->placed()->create(['shipping_phone' => '611111111']);

        expect(porCampo('telefono', '600999'))->toHaveCount(1);
    });

    /**
     * «Nombre» mira en los dos sitios: quien busca no sabe si le han dado el
     * nombre de la cuenta o el del envio, y separarlos le trasladaria un
     * problema que no puede resolver.
     */
    it('por nombre mira el de la cuenta y el del envio', function () {
        Order::factory()->for($this->marta)->placed()->create(['shipping_name' => 'Abuela Carmen']);
        Order::factory()->for($this->luis)->placed()->create(['shipping_name' => 'Luis Gomez']);

        expect(porCampo('nombre', 'Carmen'))->toHaveCount(1);
        expect(porCampo('nombre', 'Marta'))->toHaveCount(1);
        expect(porCampo('nombre', 'Luis'))->toHaveCount(1);
    });

    it('por email no mira el nombre', function () {
        Order::factory()->for($this->marta)->placed()->create();
        Order::factory()->for($this->luis)->placed()->create();

        expect(porCampo('email', 'otrositio'))->toHaveCount(1);
        // «Marta» esta en el nombre, no en el correo de Luis.
        expect(porCampo('email', 'Marta'))->toHaveCount(1);
        expect(porCampo('email', 'Gomez'))->toBe([]);
    });

    /** El campo acotado se cruza con los demas filtros, no los sustituye. */
    it('se combina con el estado y las fechas', function () {
        Order::factory()->for($this->marta)->status(OrderStatus::Paid)->create();
        Order::factory()->for($this->marta)->status(OrderStatus::Shipped)->create();

        $resultado = porCampo('nombre', 'Marta', ['status' => 'paid']);

        expect($resultado)->toHaveCount(1)
            ->and($resultado[0]['status'])->toBe('paid');
    });

    it('rechaza un campo que no existe', function () {
        $this->actingAs($this->admin)
            ->getJson(route('admin.orders.index', ['buscar_por' => 'horoscopo', 'q' => 'x']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('buscar_por');
    });

    /** Un pedido no tiene titulo ni lugar; ofrecerlos seria mentir. */
    it('rechaza en pedidos los campos que solo tienen los eventos', function () {
        $this->actingAs($this->admin)
            ->getJson(route('admin.orders.index', ['buscar_por' => 'titulo', 'q' => 'x']))
            ->assertStatus(422);
    });

    it('exige el termino cuando se elige campo', function () {
        $this->actingAs($this->admin)
            ->getJson(route('admin.orders.index', ['buscar_por' => 'nombre']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('q');
    });

    /** Sin `buscar_por` sigue siendo la caja rapida de siempre. */
    it('sin campo sigue mirando en todo', function () {
        Order::factory()->for($this->marta)->placed()->create(['shipping_phone' => '600999888']);

        $resultado = $this->actingAs($this->admin)
            ->getJson(route('admin.orders.index', ['q' => '600999']))
            ->assertOk()->json('data');

        expect($resultado)->toHaveCount(1);
    });
});

describe('la busqueda acotada en eventos', function () {
    it('distingue el titulo del lugar', function () {
        Event::factory()->for($this->marta)->create([
            'title' => 'Boda en Toledo',
            'location' => 'Finca El Olivar, Cadiz',
        ]);
        Event::factory()->for($this->luis)->create([
            'title' => 'Comunion de Sara',
            'location' => 'Hotel Toledo, Madrid',
        ]);

        $porTitulo = $this->actingAs($this->admin)
            ->getJson(route('admin.events.index', ['buscar_por' => 'titulo', 'q' => 'Toledo']))
            ->assertOk()->json('data');

        $porLugar = $this->actingAs($this->admin)
            ->getJson(route('admin.events.index', ['buscar_por' => 'lugar', 'q' => 'Toledo']))
            ->assertOk()->json('data');

        expect($porTitulo)->toHaveCount(1)
            ->and($porTitulo[0]['title'])->toBe('Boda en Toledo')
            ->and($porLugar)->toHaveCount(1)
            ->and($porLugar[0]['title'])->toBe('Comunion de Sara');
    });

    it('no ofrece buscar por numero', function () {
        $this->actingAs($this->admin)
            ->getJson(route('admin.events.index', ['buscar_por' => 'numero', 'q' => '1']))
            ->assertStatus(422);
    });
});
