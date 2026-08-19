<?php

use App\Models\GalleryPiece;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

/**
 * D33 — la galeria, gestionada desde el backoffice.
 *
 * El legacy tenia una galeria estatica y la SPA se quedo sin ella. Se
 * reconstruye como contenido que Felicitas gestiona, igual que D5 decidio
 * para el catalogo: en su archivo hay 207 MB de fotos sin curar —de movil, a
 * 3024x4032, alguna de 65 MB— y elegir cuales son «la galeria» es decision
 * suya, no del codigo.
 *
 * **Aqui re-encodificar SI es lo correcto**, al reves que en la entrega
 * digital (D20): esto son imagenes para mirar en pantalla, no para imprimir.
 */
beforeEach(function () {
    Storage::fake('public');

    $this->withHeader('Referer', config('app.url'));

    $this->artista = User::factory()->admin()->create();
    $this->cliente = User::factory()->create();
});

function subirPieza(array $datos = []): TestResponse
{
    return test()->postJson('/api/admin/gallery', array_merge([
        'file' => UploadedFile::fake()->image('obra.jpg', 3024, 4032),
        'title' => 'Retrato en acuarela',
        'category' => 'dibujo',
    ], $datos));
}

describe('el escaparate publico', function () {
    /** Sin sesion: es lo que se enseña a quien todavia no es cliente. */
    it('se ve sin haber entrado', function () {
        GalleryPiece::factory()->publicada()->create(['title' => 'Boda en Toledo']);

        $this->getJson('/api/gallery')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Boda en Toledo');
    });

    it('no enseña lo que no esta publicado', function () {
        GalleryPiece::factory()->create(['is_published' => false]);

        $this->getJson('/api/gallery')->assertOk()->assertJsonCount(0, 'data');
    });

    it('respeta el orden que fijo la artista', function () {
        GalleryPiece::factory()->publicada()->create(['title' => 'Segunda', 'sort_order' => 2]);
        GalleryPiece::factory()->publicada()->create(['title' => 'Primera', 'sort_order' => 1]);

        $this->getJson('/api/gallery')
            ->assertJsonPath('data.0.title', 'Primera')
            ->assertJsonPath('data.1.title', 'Segunda');
    });

    it('filtra por categoria', function () {
        GalleryPiece::factory()->publicada()->create(['category' => 'live-art']);
        GalleryPiece::factory()->publicada()->create(['category' => 'papeleria']);

        $this->getJson('/api/gallery?category=papeleria')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.category', 'papeleria');
    });
});

describe('gestionar la galeria', function () {
    it('sube una pieza y genera las dos derivadas', function () {
        $this->actingAs($this->artista);

        subirPieza()->assertCreated();

        $pieza = GalleryPiece::sole();

        expect($pieza->title)->toBe('Retrato en acuarela')
            // Se publica al subir: quien sube una foto a su galeria quiere
            // enseñarla, no guardarla en un cajon.
            ->and($pieza->is_published)->toBeTrue();

        Storage::disk('public')->assertExists($pieza->media->path);
        Storage::disk('public')->assertExists($pieza->thumbnail->path);
    });

    /**
     * Lo que justifica la miniatura: sin ella, una rejilla de treinta piezas
     * descarga las treinta a tamano completo.
     */
    it('la miniatura pesa mucho menos que la grande', function () {
        $this->actingAs($this->artista);

        subirPieza()->assertCreated();
        $pieza = GalleryPiece::sole();

        expect($pieza->thumbnail->size_bytes)->toBeLessThan($pieza->media->size_bytes);
    });

    it('cierra la galeria a los clientes', function () {
        // Una pieza que existe de verdad: contra un id inventado llegaria
        // antes el 404 del enlace de modelo que el 403 del rol, y el test
        // pasaria sin probar lo que dice probar.
        $pieza = GalleryPiece::factory()->publicada()->create();

        $this->actingAs($this->cliente);

        subirPieza()->assertForbidden();
        $this->patchJson("/api/admin/gallery/{$pieza->id}", ['title' => 'Mia'])->assertForbidden();
        $this->deleteJson("/api/admin/gallery/{$pieza->id}")->assertForbidden();

        expect($pieza->fresh()->title)->not->toBe('Mia');
    });

    it('exige sesion', function () {
        subirPieza()->assertUnauthorized();
    });

    it('despublica sin borrar', function () {
        $pieza = GalleryPiece::factory()->publicada()->create();

        $this->actingAs($this->artista)
            ->patchJson("/api/admin/gallery/{$pieza->id}", ['is_published' => false])
            ->assertOk();

        expect($pieza->fresh()->is_published)->toBeFalse();
        $this->getJson('/api/gallery')->assertJsonCount(0, 'data');
    });

    /** Borrar una pieza no puede dejar sus ficheros tirados en el disco. */
    it('borra tambien las imagenes', function () {
        $this->actingAs($this->artista);
        subirPieza()->assertCreated();

        $pieza = GalleryPiece::sole();
        $grande = $pieza->media->path;
        $mini = $pieza->thumbnail->path;

        $this->deleteJson("/api/admin/gallery/{$pieza->id}")->assertNoContent();

        Storage::disk('public')->assertMissing($grande);
        Storage::disk('public')->assertMissing($mini);
        expect(MediaAsset::count())->toBe(0);
    });

    it('reordena las piezas', function () {
        $primera = GalleryPiece::factory()->publicada()->create(['sort_order' => 1]);
        $segunda = GalleryPiece::factory()->publicada()->create(['sort_order' => 2]);

        $this->actingAs($this->artista)
            ->postJson('/api/admin/gallery/reorder', ['ids' => [$segunda->id, $primera->id]])
            ->assertOk();

        expect($segunda->fresh()->sort_order)->toBeLessThan($primera->fresh()->sort_order);
    });

    it('rechaza un reorden con un id que no es de la galeria', function () {
        $pieza = GalleryPiece::factory()->create();

        $this->actingAs($this->artista)
            ->postJson('/api/admin/gallery/reorder', ['ids' => [$pieza->id, 99999]])
            ->assertStatus(422);
    });

    /** SEC-014 — la foto se re-encodifica, tambien aqui. */
    it('rechaza un fichero que no es una imagen', function () {
        $this->actingAs($this->artista);

        $ruta = tempnam(sys_get_temp_dir(), 'obra').'.jpg';
        file_put_contents($ruta, '<html><script>alert(1)</script></html>');

        subirPieza(['file' => new UploadedFile($ruta, 'obra.jpg', 'image/jpeg', null, test: true)])
            ->assertStatus(422);

        expect(GalleryPiece::count())->toBe(0);

        @unlink($ruta);
    });
});
