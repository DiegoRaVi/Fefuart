<?php

use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\MediaAsset;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * D21 y D22 — desactivar y suprimir, que son cosas distintas.
 *
 * Estaban calendarizadas «al final de la Fase 4 y antes de la Fase 5» y no se
 * hicieron: se descubrio repasando el roadmap contra el codigo cuando ya
 * habia pagos reales, direcciones y fotos de clientes. Es la unica deuda del
 * proyecto con una obligacion legal detras — el derecho de supresion del
 * art. 17 del RGPD.
 *
 * **Desactivar es reversible y suprimir no.** Un borrado logico conserva el
 * dato personal y lo mantiene tratable, asi que no cubre el art. 17: por eso
 * hacen falta los dos mecanismos y no uno.
 */
beforeEach(function () {
    Storage::fake('public');

    $this->withHeader('Referer', config('app.url'));

    $this->cliente = User::factory()->create([
        'name' => 'Marta Ruiz',
        'email' => 'marta@fefuart.test',
    ]);

    $this->artista = User::factory()->admin()->create();
});

describe('desactivar la cuenta (D21)', function () {
    it('impide entrar pero conserva los datos', function () {
        $this->actingAs($this->cliente)
            ->postJson('/api/profile/deactivate')
            ->assertNoContent();

        $marta = $this->cliente->fresh();

        expect($marta->deactivated_at)->not->toBeNull()
            // Reversible: nada se ha perdido.
            ->and($marta->name)->toBe('Marta Ruiz')
            ->and($marta->email)->toBe('marta@fefuart.test');

    });

    /**
     * Que no pueda entrar va en su propio test y no pegado al anterior: una
     * peticion autenticada deja `sanctum` como guard por defecto de todo el
     * proceso, y ese guard no sabe hacer `attempt`. En produccion cada
     * peticion arranca limpia; encadenarlas aqui probaria el artefacto.
     */
    it('no deja entrar a una cuenta desactivada', function () {
        $this->cliente->deactivated_at = now();
        $this->cliente->save();

        $this->postJson(route('auth.login'), [
            'email' => 'marta@fefuart.test',
            'password' => 'password',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    });

    /** Y la contrasena correcta tampoco la delata: sigue sin entrar. */
    it('deja entrar en cuanto se reactiva', function () {
        $this->cliente->deactivated_at = now();
        $this->cliente->save();

        $this->postJson(route('auth.login'), [
            'email' => 'marta@fefuart.test',
            'password' => 'password',
        ])->assertStatus(422);

        $this->cliente->deactivated_at = null;
        $this->cliente->save();

        $this->postJson(route('auth.login'), [
            'email' => 'marta@fefuart.test',
            'password' => 'password',
        ])->assertOk();
    });

    it('corta la sesion que ya estuviera abierta', function () {
        $this->actingAs($this->cliente)->postJson('/api/profile/deactivate')->assertNoContent();

        $this->actingAs($this->cliente->fresh())
            ->getJson('/api/orders')
            ->assertUnauthorized();
    });
});

describe('suprimir la cuenta (D22)', function () {
    it('exige la contrasena', function () {
        $this->actingAs($this->cliente)
            ->deleteJson('/api/profile', ['password' => 'la-que-no-es'])
            ->assertStatus(422);

        expect($this->cliente->fresh()->name)->toBe('Marta Ruiz');
    });

    it('deja la identidad irreconocible', function () {
        $this->actingAs($this->cliente)
            ->deleteJson('/api/profile', ['password' => 'password'])
            ->assertNoContent();

        $marta = User::withoutGlobalScopes()->findOrFail($this->cliente->id);

        expect($marta->name)->not->toContain('Marta')
            ->and($marta->email)->not->toContain('marta@fefuart.test')
            ->and($marta->email)->toContain('@fefuart.invalid')
            ->and($marta->deactivated_at)->not->toBeNull();
    });

    /**
     * El nucleo de D22: **el pedido sobrevive y la identidad no**. Lo ampara
     * el art. 17(3)(b) — conservar lo que exige una obligacion legal.
     */
    it('conserva el pedido con su importe y su fecha, sin los datos personales', function () {
        $pedido = Order::factory()->for($this->cliente)->status(OrderStatus::Completed)->create([
            'total' => '45.00',
            'shipping_name' => 'Marta Ruiz',
            'shipping_phone' => '600123456',
            'shipping_line1' => 'Calle Mayor 1',
            'shipping_city' => 'Madrid',
        ]);

        OrderItem::factory()->for($pedido)->create(['customer_notes' => 'Con el perro de la foto']);

        $this->actingAs($this->cliente)
            ->deleteJson('/api/profile', ['password' => 'password'])
            ->assertNoContent();

        $guardado = $pedido->fresh();

        expect($guardado)->not->toBeNull()
            // Lo que se conserva.
            ->and($guardado->total)->toBe('45.00')
            ->and($guardado->placed_at)->not->toBeNull()
            ->and($guardado->status)->toBe(OrderStatus::Completed)
            // Lo que desaparece.
            ->and($guardado->shipping_name)->toBeNull()
            ->and($guardado->shipping_phone)->toBeNull()
            ->and($guardado->shipping_line1)->toBeNull()
            ->and($guardado->shipping_city)->toBeNull()
            ->and($guardado->items()->first()->customer_notes)->toBeNull();
    });

    it('borra del disco las fotos que subio', function () {
        Storage::disk('public')->put('referencias/suya.jpg', 'contenido');

        $media = MediaAsset::factory()->create([
            'user_id' => $this->cliente->id,
            'path' => 'referencias/suya.jpg',
            'visibility' => 'public',
        ]);

        $this->actingAs($this->cliente)
            ->deleteJson('/api/profile', ['password' => 'password'])
            ->assertNoContent();

        Storage::disk('public')->assertMissing('referencias/suya.jpg');
        expect(MediaAsset::find($media->id))->toBeNull();
    });

    it('anonimiza tambien sus eventos', function () {
        $evento = Event::factory()->for($this->cliente)->create([
            'title' => 'Boda de Marta y Luis',
            'phone' => '600123456',
            'location' => 'Finca El Olivar',
        ]);

        $this->actingAs($this->cliente)
            ->deleteJson('/api/profile', ['password' => 'password'])
            ->assertNoContent();

        $guardado = $evento->fresh();

        expect($guardado->title)->not->toContain('Marta')
            ->and($guardado->phone)->toBeNull()
            // `location` es NOT NULL —la agenda no admite un evento sin
            // sitio—, asi que se sustituye en vez de vaciarse.
            ->and($guardado->location)->not->toContain('Olivar')
            // La fecha se conserva: es la agenda, no un dato personal.
            ->and($guardado->event_date)->not->toBeNull();
    });

    /**
     * Ejecucion del contrato (art. 6(1)(b)): no se puede suprimir a alguien a
     * quien todavia hay que entregarle algo que ha pagado. Se explica cual.
     */
    it('se bloquea si hay un encargo sin terminar', function () {
        Order::factory()->for($this->cliente)->status(OrderStatus::Paid)->create();

        $this->actingAs($this->cliente)
            ->deleteJson('/api/profile', ['password' => 'password'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('account');

        expect($this->cliente->fresh()->name)->toBe('Marta Ruiz');
    });

    it('se bloquea si hay un evento confirmado', function () {
        Event::factory()->for($this->cliente)->create(['status' => EventStatus::Confirmed]);

        $this->actingAs($this->cliente)
            ->deleteJson('/api/profile', ['password' => 'password'])
            ->assertStatus(422);
    });

    /** Un pedido cancelado o completado no bloquea nada. */
    it('no se bloquea por un pedido ya cerrado', function () {
        Order::factory()->for($this->cliente)->status(OrderStatus::Cancelled)->create();

        $this->actingAs($this->cliente)
            ->deleteJson('/api/profile', ['password' => 'password'])
            ->assertNoContent();
    });

    /** Dos supresiones no pueden chocar contra el indice unico del email. */
    it('admite mas de una cuenta suprimida', function () {
        $otra = User::factory()->create(['email' => 'otra@fefuart.test']);

        $this->actingAs($this->cliente)
            ->deleteJson('/api/profile', ['password' => 'password'])->assertNoContent();

        $this->actingAs($otra)
            ->deleteJson('/api/profile', ['password' => 'password'])->assertNoContent();

        expect(User::withoutGlobalScopes()->count())->toBe(3);
    });
});

describe('la artista suprime a peticion del cliente', function () {
    it('puede suprimir la cuenta de un cliente', function () {
        $this->actingAs($this->artista)
            ->deleteJson("/api/admin/users/{$this->cliente->id}")
            ->assertNoContent();

        expect(User::withoutGlobalScopes()->findOrFail($this->cliente->id)->email)
            ->toContain('@fefuart.invalid');
    });

    /** Dejaria la tienda sin nadie que la atienda. */
    it('no puede suprimirse a si misma', function () {
        $this->actingAs($this->artista)
            ->deleteJson("/api/admin/users/{$this->artista->id}")
            ->assertStatus(422);

        expect($this->artista->fresh()->email)->not->toContain('invalid');
    });

    it('cierra la supresion ajena a los clientes', function () {
        $otra = User::factory()->create();

        $this->actingAs($this->cliente)
            ->deleteJson("/api/admin/users/{$otra->id}")
            ->assertForbidden();

        expect($otra->fresh()->email)->not->toContain('invalid');
    });
});
