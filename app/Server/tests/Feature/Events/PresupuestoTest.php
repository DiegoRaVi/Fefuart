<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Payment;
use App\Models\User;
use App\Services\SettingsService;
use Tests\Support\StripeFalso;

/**
 * D6, N13, N15 — el presupuesto de la artista y la señal que reserva.
 *
 * Cada boda es distinta y una tarifa publicada no encaja, asi que entre la
 * solicitud y la reserva hay una negociacion con estados propios. v1 no la
 * tenia: su enum era `pending / confirmed / rejected / done` y el propietario
 * podia moverse a `confirmed` el solo (SEC-010).
 */
beforeEach(function () {
    $this->withHeader('Referer', config('app.url'));

    $this->cliente = User::factory()->create();
    $this->admin = User::factory()->admin()->create();
    $this->evento = Event::factory()->for($this->cliente)->create([
        'event_date' => now()->addMonths(6)->toDateString(),
        'schedule' => 'evening',
    ]);
});

describe('emitir el presupuesto', function () {
    it('fija el importe, calcula la señal y arranca el plazo', function () {
        $this->actingAs($this->admin)
            ->postJson("/api/admin/events/{$this->evento->id}/quote", ['quoted_amount' => '1200.00'])
            ->assertOk()
            ->assertJsonPath('data.status', 'quoted')
            ->assertJsonPath('data.quoted_amount', '1200.00')
            // N15 — 30 % por defecto.
            ->assertJsonPath('data.deposit_amount', '360.00');

        $evento = $this->evento->fresh();

        expect($evento->status)->toBe(EventStatus::Quoted)
            ->and($evento->quoted_at)->not->toBeNull()
            // P1 — 14 dias por defecto.
            ->and(now()->diffInDays($evento->quote_expires_at))->toBeGreaterThan(13);
    });

    /**
     * SEC-006 llevado al presupuesto: la señal no llega del cuerpo ni
     * siquiera cuando quien escribe es la administradora. La calcula
     * PricingService con el porcentaje configurado.
     */
    it('ignora la señal que llegue en el cuerpo', function () {
        $this->actingAs($this->admin)
            ->postJson("/api/admin/events/{$this->evento->id}/quote", [
                'quoted_amount' => '1000.00',
                'deposit_amount' => '1.00',
            ])
            ->assertOk()
            ->assertJsonPath('data.deposit_amount', '300.00');
    });

    it('usa el porcentaje configurado', function () {
        app(SettingsService::class)->guardar(['deposit_percentage' => 50]);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/events/{$this->evento->id}/quote", ['quoted_amount' => '1000.00'])
            ->assertOk()
            ->assertJsonPath('data.deposit_amount', '500.00');
    });

    it('admite un plazo de validez propio', function () {
        $this->actingAs($this->admin)
            ->postJson("/api/admin/events/{$this->evento->id}/quote", [
                'quoted_amount' => '1000.00',
                'validez_dias' => 3,
            ])
            ->assertOk();

        expect(now()->diffInDays($this->evento->fresh()->quote_expires_at))
            ->toBeGreaterThan(2.9)
            ->toBeLessThan(3.1);
    });

    it('exige un importe con sentido', function () {
        $ruta = "/api/admin/events/{$this->evento->id}/quote";

        $this->actingAs($this->admin)->postJson($ruta, [])
            ->assertJsonValidationErrors('quoted_amount');

        $this->actingAs($this->admin)->postJson($ruta, ['quoted_amount' => '-5'])
            ->assertJsonValidationErrors('quoted_amount');

        $this->actingAs($this->admin)->postJson($ruta, ['quoted_amount' => '10.005'])
            ->assertJsonValidationErrors('quoted_amount');
    });

    /** N13 — el presupuesto lo emite la artista, nadie mas. */
    it('no deja al cliente presupuestarse su propio evento', function () {
        $this->actingAs($this->cliente)
            ->postJson("/api/admin/events/{$this->evento->id}/quote", ['quoted_amount' => '1.00'])
            ->assertForbidden();

        expect($this->evento->fresh()->status)->toBe(EventStatus::Requested);
    });

    it('exige sesion', function () {
        $this->postJson("/api/admin/events/{$this->evento->id}/quote", ['quoted_amount' => '1.00'])
            ->assertUnauthorized();
    });

    /**
     * N16 — presupuestar una fecha ya comprometida seria ofrecerle al cliente
     * algo que no se le puede dar.
     */
    it('no presupuesta una franja ya confirmada', function () {
        Event::factory()->create([
            'event_date' => $this->evento->event_date->toDateString(),
            'schedule' => 'evening',
            'status' => EventStatus::Confirmed,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/events/{$this->evento->id}/quote", ['quoted_amount' => '1000.00'])
            ->assertJsonValidationErrors('event_date');
    });
});

describe('aceptar el presupuesto', function () {
    beforeEach(function () {
        $this->evento = Event::factory()->for($this->cliente)->quoted()->create([
            'event_date' => now()->addMonths(6)->toDateString(),
            'schedule' => 'evening',
        ]);
    });

    it('deja el evento aceptado y devuelve la URL de pago', function () {
        $this->stripe->responde(StripeFalso::sesion([
            'id' => 'cs_test_senal',
            'amount_total' => 36000,
            'url' => 'https://checkout.stripe.com/c/pay/cs_test_senal',
        ]));

        $this->actingAs($this->cliente)
            ->postJson("/api/events/{$this->evento->id}/accept-quote")
            ->assertOk()
            ->assertJsonPath('url', 'https://checkout.stripe.com/c/pay/cs_test_senal')
            ->assertJsonPath('event.status', 'accepted');

        $pago = Payment::query()->sole();

        // N15 — se cobra la señal, no el presupuesto entero.
        expect((string) $pago->amount)->toBe('360.00')
            ->and($pago->kind->value)->toBe('deposit')
            ->and($pago->payable->is($this->evento))->toBeTrue()
            // Aceptar no confirma: eso lo hara el webhook.
            ->and($this->evento->fresh()->status)->toBe(EventStatus::Accepted);
    });

    it('cobra la señal guardada y no la que salga del porcentaje de hoy', function () {
        // La artista sube el porcentaje despues de presupuestar.
        app(SettingsService::class)->guardar(['deposit_percentage' => 90]);

        $this->stripe->responde(StripeFalso::sesion(['amount_total' => 36000]));

        $this->actingAs($this->cliente)
            ->postJson("/api/events/{$this->evento->id}/accept-quote")
            ->assertOk();

        expect((string) Payment::query()->sole()->amount)->toBe('360.00')
            ->and($this->stripe->ultima()['params']['line_items'][0]['price_data']['unit_amount'])
            ->toBe(36000);
    });

    /** SEC-008 — el evento de cada cual. */
    it('no deja aceptar el presupuesto de otro', function () {
        $this->actingAs(User::factory()->create())
            ->postJson("/api/events/{$this->evento->id}/accept-quote")
            ->assertForbidden();

        expect($this->evento->fresh()->status)->toBe(EventStatus::Quoted)
            ->and($this->stripe->peticiones)->toBeEmpty();
    });

    it('no deja a la administradora aceptar en nombre del cliente', function () {
        $this->actingAs($this->admin)
            ->postJson("/api/events/{$this->evento->id}/accept-quote")
            ->assertForbidden();
    });

    /** P1 — un presupuesto de hace meses no se acepta hoy a aquel precio. */
    it('no deja aceptar un presupuesto caducado', function () {
        $evento = Event::factory()->for($this->cliente)->quoteCaducado()->create();

        $this->actingAs($this->cliente)
            ->postJson("/api/events/{$evento->id}/accept-quote")
            ->assertJsonValidationErrors('quote');

        expect($evento->fresh()->status)->toBe(EventStatus::Quoted)
            ->and($this->stripe->peticiones)->toBeEmpty();
    });

    /**
     * N16 — otra reserva se confirmo entre el presupuesto y la aceptacion.
     * Enterarse aqui es enterarse antes de cobrar.
     */
    it('no deja aceptar si la franja se ocupo entre medias', function () {
        Event::factory()->create([
            'event_date' => $this->evento->event_date->toDateString(),
            'schedule' => 'evening',
            'status' => EventStatus::Confirmed,
        ]);

        $this->actingAs($this->cliente)
            ->postJson("/api/events/{$this->evento->id}/accept-quote")
            ->assertJsonValidationErrors('event_date');

        expect($this->stripe->peticiones)->toBeEmpty();
    });

    /**
     * Aceptar y abandonar la pagina de pago tiene que poder reintentarse: el
     * evento se queda en `accepted` y la sesion sigue abierta.
     */
    it('reutiliza la sesion si el cliente vuelve a intentarlo', function () {
        $this->stripe
            ->responde(StripeFalso::sesion(['id' => 'cs_test_senal', 'amount_total' => 36000]))
            ->responde(StripeFalso::sesion([
                'id' => 'cs_test_senal',
                'status' => 'open',
                'amount_total' => 36000,
            ]));

        $this->actingAs($this->cliente)->postJson("/api/events/{$this->evento->id}/accept-quote")->assertOk();
        $this->actingAs($this->cliente)->postJson("/api/events/{$this->evento->id}/accept-quote")->assertOk();

        expect(Payment::query()->count())->toBe(1);
    });

    it('no deja aceptar un evento que aun no esta presupuestado', function () {
        $evento = Event::factory()->for($this->cliente)->create();

        $this->actingAs($this->cliente)
            ->postJson("/api/events/{$evento->id}/accept-quote")
            ->assertForbidden();
    });
});
