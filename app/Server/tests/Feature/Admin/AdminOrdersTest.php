<?php

use App\Enums\OrderStatus;
use App\Models\MediaAsset;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->withHeader('Referer', config('app.url'));

    $this->admin = User::factory()->admin()->create();
    $this->cliente = User::factory()->create(['email' => 'marta@fefuart.test']);
    $this->otroCliente = User::factory()->create(['email' => 'luis@fefuart.test']);
});

it('exige sesion', function () {
    $this->getJson(route('admin.orders.index'))->assertUnauthorized();
});

it('cierra el backoffice a un cliente', function () {
    $order = Order::factory()->for($this->cliente)->placed()->create();

    $this->actingAs($this->cliente)->getJson(route('admin.orders.index'))->assertForbidden();
    $this->getJson(route('admin.orders.show', $order))->assertForbidden();
    $this->postJson(route('admin.orders.status', $order), ['status' => 'paid'])->assertForbidden();
});

it('lista los pedidos de todos los clientes', function () {
    Order::factory()->for($this->cliente)->placed()->create();
    Order::factory()->for($this->otroCliente)->placed()->create();

    $this->actingAs($this->admin)->getJson(route('admin.orders.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 2);
});

/** Un carrito abierto no es un pedido y no tiene por que salir en la lista. */
it('no lista los carritos', function () {
    Order::factory()->for($this->cliente)->create(['status' => OrderStatus::Cart]);
    Order::factory()->for($this->cliente)->placed()->create();

    $this->actingAs($this->admin)->getJson(route('admin.orders.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('filtra por estado', function () {
    Order::factory()->for($this->cliente)->status(OrderStatus::Paid)->create();
    Order::factory()->for($this->cliente)->status(OrderStatus::Shipped)->create();

    $this->actingAs($this->admin)
        ->getJson(route('admin.orders.index', ['status' => 'paid']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'paid');
});

it('rechaza un estado inventado en el filtro', function () {
    $this->actingAs($this->admin)
        ->getJson(route('admin.orders.index', ['status' => 'inventado']))
        ->assertStatus(422);
});

/**
 * v1 tenia busqueda de pedidos por email de cliente y es lo que Felicitas
 * usa para localizar un encargo cuando alguien escribe preguntando. En v2 es
 * una sola caja que mira tambien el numero, el nombre y el telefono; los
 * casos concretos estan en AdminOrdersBusquedaTest.
 */
it('busca pedidos por email del cliente', function () {
    Order::factory()->for($this->cliente)->placed()->create();
    Order::factory()->for($this->otroCliente)->placed()->create();

    $this->actingAs($this->admin)
        ->getJson(route('admin.orders.index', ['q' => 'marta@']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.customer.email', 'marta@fefuart.test');
});

it('devuelve lista vacia si la busqueda no encuentra nada', function () {
    Order::factory()->for($this->cliente)->placed()->create();

    $this->actingAs($this->admin)
        ->getJson(route('admin.orders.index', ['q' => 'nadie@']))
        ->assertOk()
        ->assertJsonPath('data', []);
});

/**
 * D14/N9 — la administradora necesita ver la foto de partida de cada linea
 * para dibujar el encargo.
 */
it('ensena la foto de referencia de cada linea', function () {
    $order = Order::factory()->for($this->cliente)->placed()->create();
    $foto = MediaAsset::factory()->for($this->cliente)->create();
    OrderItem::factory()->for($order)->create([
        'reference_media_id' => $foto->id,
        'customer_notes' => 'En blanco y negro.',
    ]);

    $this->actingAs($this->admin)->getJson(route('admin.orders.show', $order))
        ->assertOk()
        ->assertJsonPath('data.items.0.reference_media.id', $foto->id)
        ->assertJsonPath('data.items.0.customer_notes', 'En blanco y negro.')
        ->assertJsonPath('data.customer.email', 'marta@fefuart.test');
});

/**
 * SEC-009 — el email del cliente sale para la administradora y para nadie
 * mas. En v1 `GET /api/user/{id}` lo devolvia a cualquier cuenta
 * autenticada.
 */
it('no filtra los datos del cliente en las rutas de cliente', function () {
    $order = Order::factory()->for($this->cliente)->placed()->create();

    $respuesta = $this->actingAs($this->cliente)->getJson(route('orders.show', $order))
        ->assertOk();

    expect($respuesta->json('data'))->not->toHaveKey('customer');
});

describe('el cambio de estado', function () {
    it('avanza por una transicion valida', function () {
        $order = Order::factory()->for($this->cliente)->status(OrderStatus::Paid)->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.orders.status', $order), ['status' => 'in_progress'])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');

        expect($order->fresh()->status)->toBe(OrderStatus::InProgress);
    });

    /**
     * SEC-003 — en v1 cualquiera podia mandar `{"status":"paid"}` a
     * `PATCH /orders/{id}`. Aqui ni siquiera la administradora puede saltarse
     * la maquina de estados.
     */
    it('rechaza un salto que el enum no permite', function () {
        $order = Order::factory()->for($this->cliente)->status(OrderStatus::PendingPayment)->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.orders.status', $order), ['status' => 'shipped'])
            ->assertStatus(422);

        expect($order->fresh()->status)->toBe(OrderStatus::PendingPayment);
    });

    it('rechaza un estado que no existe', function () {
        $order = Order::factory()->for($this->cliente)->status(OrderStatus::Paid)->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.orders.status', $order), ['status' => 'regalado'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    });

    /** SEC-006 — el importe no se toca desde ningun sitio, tampoco desde aqui. */
    it('ignora cualquier importe que venga en el cuerpo', function () {
        $order = Order::factory()->for($this->cliente)->status(OrderStatus::Paid)->create([
            'total' => '45.00',
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('admin.orders.status', $order), [
                'status' => 'in_progress',
                'total' => '0.00',
                'subtotal' => '0.00',
            ])
            ->assertOk()
            ->assertJsonPath('data.total', '45.00');
    });
});

/**
 * PERF-001 — regresion.
 *
 * `admin.js:92,276` hacia una peticion `GET /user/{id}` por cada fila que
 * renderizaba.
 *
 * El test no fija un numero de consultas, sino que **el numero no crezca con
 * el de pedidos**: es lo unico que distingue de verdad un eager load de un
 * N+1, y no hay que ajustarlo cada vez que se anade una relacion.
 *
 * Comprueba ademas que los datos del cliente salen en la respuesta. Sin eso
 * el test seria vacuo: `OrderResource` solo toca la relacion si esta
 * cargada, asi que quitar el eager load no produce N+1 — produce un listado
 * sin cliente, que es igual de roto y mas silencioso.
 */
it('no hace mas consultas por listar mas pedidos', function () {
    $this->actingAs($this->admin);

    $crear = function (int $cuantos) {
        foreach (range(1, $cuantos) as $i) {
            $order = Order::factory()->for($this->cliente)->placed()->create();
            OrderItem::factory()->count(2)->for($order)->create();
        }
    };

    $medir = function (): int {
        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->getJson(route('admin.orders.index'))
            ->assertOk()
            // Si esto falta, el listado no sirve aunque cueste una consulta.
            ->assertJsonPath('data.0.customer.email', 'marta@fefuart.test')
            // El listado cuenta las lineas en vez de traerlas, pero contarlas
            // tambien puede degenerar en una consulta por fila.
            ->assertJsonPath('data.0.items_count', 2);

        return count(DB::getQueryLog());
    };

    $crear(3);

    // Peticion de calentamiento. La sesion vive en base de datos, y la
    // primera peticion la inserta mientras las siguientes solo la leen y la
    // actualizan: sin esto se estaria midiendo esa diferencia.
    $medir();

    $conTres = $medir();
    $crear(12);
    $conQuince = $medir();

    expect($conQuince)->toBe($conTres);
});
