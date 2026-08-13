<?php

use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->withHeader('Referer', config('app.url'));
});

/**
 * Construye un JPEG de verdad y le pega bytes detras. Es el polyglot de
 * toda la vida: el fichero sigue siendo una imagen valida, asi que
 * `mimes:jpeg` lo acepta, y ademas lleva codigo dentro.
 */
function jpegConPayloadPegado(int $ancho = 800, int $alto = 600, string $payload = '<?php system($_GET[0]); ?>'): UploadedFile
{
    $ruta = tempnam(sys_get_temp_dir(), 'fefuart').'.jpg';

    $imagen = imagecreatetruecolor($ancho, $alto);
    imagefilledrectangle($imagen, 0, 0, $ancho, $alto, imagecolorallocate($imagen, 200, 120, 90));
    imagejpeg($imagen, $ruta, 90);
    imagedestroy($imagen);

    file_put_contents($ruta, $payload, FILE_APPEND);

    return new UploadedFile($ruta, 'foto-de-la-boda.jpg', 'image/jpeg', null, true);
}

it('exige sesion para subir un fichero', function () {
    $this->postJson(route('media.store'), ['file' => UploadedFile::fake()->image('foto.jpg')])
        ->assertUnauthorized();
});

it('sube una imagen de referencia y devuelve su id', function () {
    $user = User::factory()->create();

    $respuesta = $this->actingAs($user)
        ->postJson(route('media.store'), ['file' => UploadedFile::fake()->image('foto.jpg', 800, 600)])
        ->assertCreated();

    $media = MediaAsset::query()->sole();

    expect($respuesta->json('data.id'))->toBe($media->id)
        ->and($media->user_id)->toBe($user->id)
        ->and($media->original_name)->toBe('foto.jpg');

    Storage::disk('public')->assertExists($media->path);
});

/**
 * SEC-014 — regresion.
 *
 * v1 validaba `image|mimes:jpeg,png,jpg,gif|max:2048` y dejaba que Laravel
 * generase el nombre, que es razonable, pero **guardaba el fichero tal
 * cual**. Un JPEG valido con bytes pegados detras pasa esa validacion
 * entera y aterriza intacto en el disco.
 *
 * Re-encodificar es lo que lo corta: se decodifican los pixeles y se
 * escribe una imagen nueva, asi que todo lo que no fueran pixeles —
 * metadatos, EXIF, payloads pegados— desaparece.
 */
it('reencodifica la imagen y tira lo que no son pixeles', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('media.store'), ['file' => jpegConPayloadPegado()])
        ->assertCreated();

    $guardado = Storage::disk('public')->get(MediaAsset::query()->sole()->path);

    expect($guardado)->not->toContain('<?php')
        ->and($guardado)->not->toContain('system($_GET')
        // Y lo que queda sigue siendo una imagen legible.
        ->and(getimagesizefromstring($guardado)[2])->toBe(IMAGETYPE_JPEG);
});

/**
 * SEC-014 — la otra mitad: v1 no limitaba las dimensiones, solo el peso. Un
 * JPEG muy comprimido de 12000x12000 pesa poco y revienta la memoria de PHP
 * al abrirlo.
 */
it('limita el lado mayor de la imagen', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('media.store'), ['file' => jpegConPayloadPegado(4000, 3000)])
        ->assertCreated();

    [$ancho, $alto] = getimagesizefromstring(
        Storage::disk('public')->get(MediaAsset::query()->sole()->path)
    );

    expect($ancho)->toBe(2400)
        // Mantiene la proporcion: 4000x3000 es 4:3.
        ->and($alto)->toBe(1800);
});

it('no toca las imagenes que ya son pequenas', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('media.store'), ['file' => jpegConPayloadPegado(600, 400)])
        ->assertCreated();

    [$ancho, $alto] = getimagesizefromstring(
        Storage::disk('public')->get(MediaAsset::query()->sole()->path)
    );

    expect([$ancho, $alto])->toBe([600, 400]);
});

it('rechaza un fichero que no es una imagen', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('media.store'), [
            'file' => UploadedFile::fake()->createWithContent('camuflado.jpg', '<?php echo 1;'),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('file');

    expect(MediaAsset::query()->count())->toBe(0);
});

/**
 * SEC-004 / SEC-008 — el IDOR de ficheros.
 *
 * En v1 `DELETE /api/products/{id}` estaba bajo `IsUserAuth` sin comprobar
 * propiedad, asi que cualquiera borraba el producto de otro **y su imagen
 * del disco**. Aqui la propiedad la decide una Policy, nunca un `if`.
 */
it('no deja que otro usuario borre mi fichero', function () {
    $mio = MediaAsset::factory()->create();
    $intruso = User::factory()->create();

    $this->actingAs($intruso)
        ->deleteJson(route('media.destroy', $mio))
        ->assertForbidden();

    expect(MediaAsset::query()->find($mio->id))->not->toBeNull();
});

it('deja al duenno borrar su fichero y lo quita del disco', function () {
    $user = User::factory()->create();

    $id = $this->actingAs($user)
        ->postJson(route('media.store'), ['file' => UploadedFile::fake()->image('foto.jpg')])
        ->json('data.id');

    $ruta = MediaAsset::query()->findOrFail($id)->path;

    $this->deleteJson(route('media.destroy', $id))->assertNoContent();

    expect(MediaAsset::query()->find($id))->toBeNull();
    Storage::disk('public')->assertMissing($ruta);
});

it('deja a la administradora borrar el fichero de un cliente', function () {
    $mio = MediaAsset::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->deleteJson(route('media.destroy', $mio))
        ->assertNoContent();

    expect(MediaAsset::query()->find($mio->id))->toBeNull();
});

/**
 * SEC-009 — el listado de ficheros ajenos tampoco existe. Un id de otro
 * usuario no revela nada: la Policy responde 403 antes de tocar el fichero.
 */
it('no filtra la existencia de ficheros ajenos al borrarlos', function () {
    $ajeno = MediaAsset::factory()->create();

    $this->actingAs(User::factory()->create())
        ->deleteJson(route('media.destroy', $ajeno))
        ->assertForbidden();

    $this->deleteJson(route('media.destroy', 999999))->assertNotFound();
});
