<?php

use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderStatusChanged;
use App\Notifications\QuoteReady;
use Illuminate\Support\Facades\Notification;

/**
 * D10 — los avisos que recibe el cliente, y en que punto exacto salen.
 *
 * El punto importa mas que el contenido. Cada aviso se encola **dentro del
 * mismo `if` que hace el cambio de estado**, no al principio del manejador:
 * es lo unico que garantiza que un reintento no mande un segundo correo. Los
 * tests de la idempotencia del webhook estan en AvisosDelWebhookTest.
 */
beforeEach(function () {
    Notification::fake();

    $this->withHeader('Referer', config('app.url'));

    $this->cliente = User::factory()->create();
    $this->admin = User::factory()->admin()->create();
    $this->evento = Event::factory()->for($this->cliente)->create([
        'event_date' => now()->addMonths(6)->toDateString(),
        'schedule' => 'evening',
    ]);
});

it('avisa al cliente cuando la artista emite el presupuesto', function () {
    $this->actingAs($this->admin)
        ->postJson("/api/admin/events/{$this->evento->id}/quote", ['quoted_amount' => '1200.00'])
        ->assertOk();

    Notification::assertSentTo($this->cliente, QuoteReady::class);
});

/**
 * El aviso es del dueño del evento y de nadie mas. Parece obvio y es justo
 * la familia de fallo de SEC-008: en v1 la pertenencia se comprobaba —o no—
 * a mano en cada sitio.
 */
it('no avisa a nadie mas del presupuesto', function () {
    $otro = User::factory()->create();

    $this->actingAs($this->admin)
        ->postJson("/api/admin/events/{$this->evento->id}/quote", ['quoted_amount' => '1200.00'])
        ->assertOk();

    Notification::assertNotSentTo($otro, QuoteReady::class);
    Notification::assertNotSentTo($this->admin, QuoteReady::class);
});

/**
 * Un presupuesto que la maquina de estados rechaza no avisa de nada.
 *
 * `status` se asigna a mano y no con `update()`: esta fuera de `$fillable`
 * desde SEC-010, asi que un `update(['status' => …])` no haria nada y el
 * test pasaria por el camino equivocado.
 */
it('no avisa si el presupuesto no llega a emitirse', function () {
    $this->evento->status = EventStatus::Cancelled;
    $this->evento->save();

    $this->actingAs($this->admin)
        ->postJson("/api/admin/events/{$this->evento->id}/quote", ['quoted_amount' => '1200.00'])
        ->assertStatus(500);

    Notification::assertNothingSent();
});

describe('el estado del pedido', function () {
    it('avisa al cliente cuando la artista lo mueve', function () {
        $pedido = Order::factory()->for($this->cliente)->status(OrderStatus::Paid)->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.orders.status', $pedido), ['status' => 'in_progress'])
            ->assertOk();

        Notification::assertSentTo($this->cliente, OrderStatusChanged::class);
    });

    /**
     * El aviso sale del cambio, no de la peticion: un salto que el enum no
     * permite deja el pedido donde estaba y no tiene nada que contar.
     */
    it('no avisa de un salto que el enum rechaza', function () {
        $pedido = Order::factory()->for($this->cliente)->status(OrderStatus::PendingPayment)->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.orders.status', $pedido), ['status' => 'shipped'])
            ->assertStatus(422);

        Notification::assertNothingSent();
    });

    /** El correo dice a que estado ha pasado, que es lo unico que se pregunta. */
    it('cuenta en que estado queda el pedido', function () {
        $pedido = Order::factory()->for($this->cliente)->status(OrderStatus::InProgress)->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.orders.status', $pedido), ['status' => 'shipped'])
            ->assertOk();

        Notification::assertSentTo(
            $this->cliente,
            OrderStatusChanged::class,
            function (OrderStatusChanged $aviso) use ($pedido) {
                $datos = $aviso->toArray($this->cliente);

                return str_contains($datos['cuerpo'], 'camino')
                    && $datos['enlace'] === "/pedidos/{$pedido->id}";
            },
        );
    });
});

/**
 * El importe se escribe como en castellano, y eso incluye **no** separar los
 * millares en numeros de cuatro cifras: se escribe «1200,00 €», no
 * «1.200,00 €». Es la regla que aplica ICU con `es-ES`, que es la que usa la
 * SPA (`shared/lib/dinero.ts`).
 *
 * Lo encontro el E2E: el correo decia 1.200,00 € y la pantalla 1200,00 €
 * para el mismo presupuesto.
 */
it('escribe los importes como la pantalla', function () {
    $this->actingAs($this->admin)
        ->postJson("/api/admin/events/{$this->evento->id}/quote", ['quoted_amount' => '1200.00'])
        ->assertOk();

    Notification::assertSentTo($this->cliente, QuoteReady::class, function (QuoteReady $aviso) {
        $cuerpo = $aviso->toArray($this->cliente)['cuerpo'];

        return str_contains($cuerpo, '1200,00 €') && ! str_contains($cuerpo, '1.200,00');
    });
});

/** A partir de cinco cifras si se separa: «12.345,00 €». */
it('separa los millares a partir de cinco cifras', function () {
    $this->actingAs($this->admin)
        ->postJson("/api/admin/events/{$this->evento->id}/quote", ['quoted_amount' => '12345.00'])
        ->assertOk();

    Notification::assertSentTo($this->cliente, QuoteReady::class, function (QuoteReady $aviso) {
        return str_contains($aviso->toArray($this->cliente)['cuerpo'], '12.345,00 €');
    });
});
