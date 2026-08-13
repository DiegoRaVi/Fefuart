<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
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

function buscar(string $q, array $extra = []): array
{
    return test()->actingAs(test()->admin)
        ->getJson(route('admin.orders.index', array_merge(['q' => $q], $extra)))
        ->assertOk()
        ->json('data');
}

/**
 * Una sola caja de busqueda, porque lo que Felicitas tiene delante cuando
 * alguien pregunta por su encargo cambia segun el caso: a veces el numero de
 * pedido que le llego por correo, a veces solo un nombre, a veces un
 * telefono desde el que la han llamado.
 */
describe('el buscador', function () {
    it('encuentra por el numero de pedido', function () {
        $suyo = Order::factory()->for($this->marta)->placed()->create();
        Order::factory()->for($this->luis)->placed()->create();

        expect(buscar((string) $suyo->id))->toHaveCount(1)
            ->and(buscar((string) $suyo->id)[0]['id'])->toBe($suyo->id);
    });

    it('encuentra por el nombre de la cuenta', function () {
        Order::factory()->for($this->marta)->placed()->create();
        Order::factory()->for($this->luis)->placed()->create();

        expect(buscar('marta'))->toHaveCount(1);
    });

    it('encuentra por el email', function () {
        Order::factory()->for($this->marta)->placed()->create();
        Order::factory()->for($this->luis)->placed()->create();

        expect(buscar('otrositio'))->toHaveCount(1);
    });

    /**
     * El nombre del envio puede no ser el de la cuenta: un regalo, o alguien
     * que pide para otra persona. Buscar solo por el de la cuenta dejaria
     * fuera justo los casos en los que hace falta buscar.
     */
    it('encuentra por el nombre del envio aunque no sea el de la cuenta', function () {
        Order::factory()->for($this->marta)->placed()->create([
            'shipping_name' => 'Abuela Carmen',
        ]);

        expect(buscar('Carmen'))->toHaveCount(1);
    });

    it('encuentra por el telefono del envio', function () {
        Order::factory()->for($this->marta)->placed()->create([
            'shipping_phone' => '600999888',
        ]);
        Order::factory()->for($this->luis)->placed()->create([
            'shipping_phone' => '611111111',
        ]);

        expect(buscar('600999'))->toHaveCount(1);
    });

    it('no distingue mayusculas', function () {
        Order::factory()->for($this->marta)->placed()->create();

        expect(buscar('MARTA'))->toHaveCount(1);
    });

    it('devuelve lista vacia si no encuentra nada', function () {
        Order::factory()->for($this->marta)->placed()->create();

        expect(buscar('no-existe-nadie'))->toBe([]);
    });

    /**
     * El fallo clasico de mezclar OR con otros filtros: sin agrupar los OR
     * del buscador, la condicion se lee como
     * `status = paid OR nombre LIKE …`, y salen pedidos de cualquier estado.
     */
    it('se combina con el filtro de estado en vez de sumarse', function () {
        Order::factory()->for($this->marta)->status(OrderStatus::Paid)->create();
        Order::factory()->for($this->marta)->status(OrderStatus::Shipped)->create();
        Order::factory()->for($this->luis)->status(OrderStatus::Paid)->create();

        $resultado = buscar('marta', ['status' => 'paid']);

        expect($resultado)->toHaveCount(1)
            ->and($resultado[0]['status'])->toBe('paid');
    });
});

describe('el rango de fechas', function () {
    beforeEach(function () {
        $this->viejo = Order::factory()->for($this->marta)->placed()->create([
            'placed_at' => now()->subMonths(3),
        ]);
        $this->reciente = Order::factory()->for($this->marta)->placed()->create([
            'placed_at' => now()->subDays(2),
        ]);
    });

    it('filtra desde una fecha', function () {
        $desde = now()->subMonth()->toDateString();

        $resultado = $this->actingAs($this->admin)
            ->getJson(route('admin.orders.index', ['desde' => $desde]))
            ->assertOk()->json('data');

        expect($resultado)->toHaveCount(1)
            ->and($resultado[0]['id'])->toBe($this->reciente->id);
    });

    it('filtra hasta una fecha', function () {
        $hasta = now()->subMonth()->toDateString();

        $resultado = $this->actingAs($this->admin)
            ->getJson(route('admin.orders.index', ['hasta' => $hasta]))
            ->assertOk()->json('data');

        expect($resultado)->toHaveCount(1)
            ->and($resultado[0]['id'])->toBe($this->viejo->id);
    });

    /**
     * `hasta` es inclusivo: quien escribe «hasta el 13 de agosto» espera que
     * salga lo del 13, no lo anterior. Con una comparacion sobre un timestamp
     * sin cuidado, un pedido de las 10:00 de ese dia se queda fuera.
     */
    it('incluye el dia entero del limite superior', function () {
        $hoy = Order::factory()->for($this->marta)->placed()->create([
            'placed_at' => now()->setTime(23, 30),
        ]);

        $resultado = $this->actingAs($this->admin)
            ->getJson(route('admin.orders.index', ['hasta' => now()->toDateString()]))
            ->assertOk()->json('data.*.id');

        expect($resultado)->toContain($hoy->id);
    });

    it('rechaza un rango al reves', function () {
        $this->actingAs($this->admin)
            ->getJson(route('admin.orders.index', [
                'desde' => now()->toDateString(),
                'hasta' => now()->subMonth()->toDateString(),
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('hasta');
    });

    it('rechaza una fecha que no lo es', function () {
        $this->actingAs($this->admin)
            ->getJson(route('admin.orders.index', ['desde' => 'ayer']))
            ->assertStatus(422);
    });
});

/**
 * El listado enseña el numero de lineas y el total; no las lineas. Cargarlas
 * —con su foto de referencia— para veinte pedidos por pagina es traer datos
 * y hacer joins que despues se tiran.
 */
describe('lo que trae el listado', function () {
    it('cuenta las lineas sin traerlas', function () {
        $order = Order::factory()->for($this->marta)->placed()->create();
        OrderItem::factory()->count(3)->for($order)->create();

        $fila = $this->actingAs($this->admin)
            ->getJson(route('admin.orders.index'))
            ->assertOk()->json('data.0');

        expect($fila['items_count'])->toBe(3)
            ->and($fila)->not->toHaveKey('items');
    });

    it('si las trae en el detalle', function () {
        $order = Order::factory()->for($this->marta)->placed()->create();
        OrderItem::factory()->count(3)->for($order)->create();

        $this->actingAs($this->admin)
            ->getJson(route('admin.orders.show', $order))
            ->assertOk()
            ->assertJsonCount(3, 'data.items');
    });
});
