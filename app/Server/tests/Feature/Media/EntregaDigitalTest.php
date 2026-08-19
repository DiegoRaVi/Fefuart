<?php

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Models\MediaAsset;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

/**
 * D20, N11 — la artista sube la obra terminada y el cliente la descarga.
 *
 * Cierra un hueco que venia de v1 y que la reconstruccion habia heredado: la
 * variante «Digital» se vende a 20 € con entrega digital, y hasta ahora no
 * habia ninguna forma de entregarla. Se podia cobrar por algo que el sistema
 * no sabia dar.
 *
 * **El fichero no se re-encodifica**, y es la diferencia con SEC-014. Alli el
 * peligro es una foto que sube un desconocido; aqui sube la artista, y pasar
 * su lamina por una recompresion a 2400 px la destruiria — es el fichero que
 * el cliente va a imprimir. Lo que protege es otra cosa: lista blanca por
 * contenido real, disco privado y descarga forzada.
 */
beforeEach(function () {
    Storage::fake('local');

    $this->withHeader('Referer', config('app.url'));

    $this->cliente = User::factory()->create();
    $this->artista = User::factory()->admin()->create();

    $this->pedido = Order::factory()->for($this->cliente)->status(OrderStatus::Paid)->create();

    $this->linea = OrderItem::factory()->for($this->pedido)->create([
        'delivery_type' => DeliveryType::Digital,
    ]);
});

/** Un PNG de verdad, con sus bytes magicos. */
function unPngReal(string $nombre = 'lamina.png'): UploadedFile
{
    return UploadedFile::fake()->image($nombre, 1200, 1600);
}

function subirEntrega(UploadedFile $fichero): TestResponse
{
    $pedido = test()->pedido;
    $linea = test()->linea;

    return test()->postJson(
        "/api/admin/orders/{$pedido->id}/items/{$linea->id}/delivery",
        ['file' => $fichero],
    );
}

describe('subir la entrega', function () {
    it('guarda el archivo y lo cuelga de la linea', function () {
        $this->actingAs($this->artista);

        subirEntrega(unPngReal())->assertOk();

        $linea = $this->linea->fresh();

        expect($linea->delivered_media_id)->not->toBeNull();

        $media = MediaAsset::findOrFail($linea->delivered_media_id);

        expect($media->visibility)->toBe('private');
        Storage::disk('local')->assertExists($media->path);
    });

    /**
     * SEC-014 aplicado donde toca: la lista blanca mira **el contenido**, no
     * la extension. Un HTML renombrado a `.png` servido desde nuestro dominio
     * seria XSS con nuestro origen.
     */
    it('rechaza un fichero que no es lo que dice ser', function () {
        $this->actingAs($this->artista);

        /*
         * Fichero **real** en disco, no `UploadedFile::fake()`.
         *
         * El doble de Laravel deduce el MIME de la extension, asi que miente:
         * con el, este test pasaria en verde sin que nadie mirase un solo
         * byte, y no probaria nada. Con un fichero de verdad, Symfony resuelve
         * el tipo con `finfo` sobre su contenido, que es lo que ocurre en
         * produccion.
         */
        $ruta = tempnam(sys_get_temp_dir(), 'entrega').'.png';
        file_put_contents($ruta, '<html><script>alert(document.domain)</script></html>');

        $falso = new UploadedFile($ruta, 'lamina.png', 'image/png', null, test: true);

        subirEntrega($falso)->assertStatus(422);

        expect($this->linea->fresh()->delivered_media_id)->toBeNull();

        @unlink($ruta);
    });

    /** N11 — solo se entrega por descarga lo que se vendio como digital. */
    it('no entrega una linea fisica', function () {
        $fisica = OrderItem::factory()->for($this->pedido)->create([
            'delivery_type' => DeliveryType::Physical,
        ]);

        $this->actingAs($this->artista)
            ->postJson("/api/admin/orders/{$this->pedido->id}/items/{$fisica->id}/delivery", [
                'file' => unPngReal(),
            ])
            ->assertStatus(422);
    });

    /** Entregar antes de cobrar seria regalar el trabajo. */
    it('no entrega un pedido sin pagar', function () {
        $pedido = Order::factory()->for($this->cliente)->status(OrderStatus::PendingPayment)->create();
        $linea = OrderItem::factory()->for($pedido)->create(['delivery_type' => DeliveryType::Digital]);

        $this->actingAs($this->artista)
            ->postJson("/api/admin/orders/{$pedido->id}/items/{$linea->id}/delivery", [
                'file' => unPngReal(),
            ])
            ->assertStatus(422);
    });

    it('cierra la subida a los clientes', function () {
        $this->actingAs($this->cliente);

        subirEntrega(unPngReal())->assertForbidden();
    });

    /** Sustituir no puede dejar el anterior tirado en el disco. */
    it('borra el archivo anterior al sustituirlo', function () {
        $this->actingAs($this->artista);

        subirEntrega(unPngReal('primera.png'))->assertOk();
        $primera = MediaAsset::findOrFail($this->linea->fresh()->delivered_media_id);

        subirEntrega(unPngReal('segunda.png'))->assertOk();

        Storage::disk('local')->assertMissing($primera->path);
        expect(MediaAsset::find($primera->id))->toBeNull();
    });
});

describe('descargar la entrega', function () {
    beforeEach(function () {
        $this->actingAs($this->artista);
        subirEntrega(unPngReal())->assertOk();
        $this->app['auth']->forgetGuards();
    });

    it('deja descargar al dueño del pedido', function () {
        $respuesta = $this->actingAs($this->cliente)
            ->get("/api/orders/{$this->pedido->id}/items/{$this->linea->id}/download")
            ->assertOk();

        // Siempre adjunto, nunca en linea: es lo que impide que el navegador
        // ejecute nada aunque algo colara la lista blanca.
        expect($respuesta->headers->get('content-disposition'))->toStartWith('attachment');
    });

    /**
     * El IDOR, y el motivo de que la Policy **no** mire la propiedad del
     * fichero: el media es de la artista, que es quien lo subio. Lo que
     * decide es ser dueño del pedido.
     *
     * 403 y no 404, que es la convencion que ya sigue el resto de rutas de
     * pedido (`OrderAccessTest`): el pedido existe y no es tuyo. Distinto de
     * los avisos, donde la propia existencia del recurso es privada.
     */
    it('no deja descargar la entrega de otro', function () {
        $otro = User::factory()->create();

        $this->actingAs($otro)
            ->get("/api/orders/{$this->pedido->id}/items/{$this->linea->id}/download")
            ->assertForbidden();
    });

    /**
     * Y con 401, no con un 500.
     *
     * Se pide **sin** cabecera `Accept: application/json`, que es como lo
     * hace un navegador al abrir el enlace de descarga: es el unico caso de
     * la API que llega asi, y el que destapo que `Authenticate` reventaba
     * buscando una ruta `login` inexistente.
     */
    it('exige sesion, tambien cuando el navegador pide HTML', function () {
        $this->get("/api/orders/{$this->pedido->id}/items/{$this->linea->id}/download")
            ->assertUnauthorized();
    });

    /** La artista descarga para comprobar lo que subio. */
    it('deja descargar a la artista', function () {
        $this->actingAs($this->artista)
            ->get("/api/orders/{$this->pedido->id}/items/{$this->linea->id}/download")
            ->assertOk();
    });

    it('responde 404 si la linea todavia no tiene entrega', function () {
        $sinEntrega = OrderItem::factory()->for($this->pedido)->create([
            'delivery_type' => DeliveryType::Digital,
        ]);

        $this->actingAs($this->cliente)
            ->get("/api/orders/{$this->pedido->id}/items/{$sinEntrega->id}/download")
            ->assertNotFound();
    });
});

/**
 * La SPA necesita saber si una linea ya tiene entrega para decidir si pinta
 * el boton de descarga. Sale como booleano y **no** como el media entero: la
 * ruta del fichero en el disco privado no tiene por que viajar a ningun
 * navegador, ni siquiera al del dueño.
 */
it('dice si la linea tiene entrega, sin exponer la ruta del fichero', function () {
    $this->actingAs($this->artista);
    subirEntrega(unPngReal())->assertOk();

    $respuesta = $this->actingAs($this->cliente)
        ->getJson("/api/orders/{$this->pedido->id}")
        ->assertOk()
        ->assertJsonPath('data.items.0.delivered', true);

    expect(json_encode($respuesta->json()))->not->toContain('entregas/');
});

it('dice que no hay entrega mientras no se haya subido', function () {
    $this->actingAs($this->cliente)
        ->getJson("/api/orders/{$this->pedido->id}")
        ->assertOk()
        ->assertJsonPath('data.items.0.delivered', false);
});
