<?php

namespace App\Services;

use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * SEC-014 — guarda una imagen subida **re-encodificandola**, nunca tal cual.
 *
 * v1 validaba `image|mimes:jpeg,png,jpg,gif|max:2048` y dejaba que Laravel
 * generase el nombre, que esta bien, pero despues movia el fichero intacto
 * al disco. Esa validacion la pasa entera un JPEG valido con bytes pegados
 * detras: sigue siendo una imagen, y ademas lleva lo que le hayan metido.
 *
 * Re-encodificar corta eso de raiz. Se decodifican los pixeles y se escribe
 * una imagen nueva desde cero, asi que todo lo que no fueran pixeles —EXIF,
 * perfiles de color, comentarios, payloads pegados— se queda fuera. De paso
 * limita el lado mayor: v1 acotaba el peso pero no las dimensiones, y un
 * JPEG muy comprimido de 12000x12000 pesa poco y revienta la memoria al
 * abrirlo.
 */
class MediaStorageService
{
    /** Lado mayor admitido. Suficiente para dibujar a partir de la foto. */
    private const MAX_SIDE = 2400;

    private const JPEG_QUALITY = 85;

    /**
     * Las referencias van al disco `public`: no son secretas y el nombre lo
     * genera el servidor. Las entregas digitales iran a `private` y se
     * serviran tras pasar por Policy (D20, N11).
     */
    public function storeReferenceImage(UploadedFile $file, User $owner): MediaAsset
    {
        $binario = $this->reencode($file);

        $path = 'referencias/'.Str::random(40).'.jpg';
        Storage::disk('public')->put($path, $binario);

        $media = new MediaAsset([
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            // El tipo lo decide la re-encodificacion, no la cabecera que
            // mando el cliente: aqui ya sabemos que es un JPEG.
            'mime_type' => 'image/jpeg',
            'size_bytes' => strlen($binario),
            'visibility' => 'public',
        ]);

        // `user_id` sale de la sesion, jamas del cuerpo de la peticion.
        $media->user_id = $owner->id;
        $media->save();

        return $media;
    }

    /**
     * D20, N11 — la obra terminada que sube la artista.
     *
     * **No se re-encodifica, y esa es toda la diferencia con SEC-014.** Alli
     * el peligro es una foto que sube un desconocido y la defensa es tirar
     * todo lo que no sean pixeles. Aqui quien sube es la artista y el fichero
     * es el que el cliente va a imprimir: pasarlo por una recompresion a
     * 2400 px lo destruiria, y ademas dejaria fuera el PDF, que es un formato
     * de entrega legitimo.
     *
     * Lo que protege es otra cosa, y son tres capas: la lista blanca por
     * **contenido real** que aplica el Form Request, el disco privado —fuera
     * del alcance de Apache— y que la descarga se sirva siempre como adjunto.
     * Un fichero raro no llega, y si llegara, el navegador no lo ejecutaria.
     */
    public function storeDelivery(UploadedFile $file, User $artist): MediaAsset
    {
        $extension = match ($file->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'application/pdf' => 'pdf',
            default => throw new RuntimeException('Tipo de entrega no admitido.'),
        };

        // Nombre aleatorio, como las referencias: el original puede traer
        // cualquier cosa y ademas dice mas de la cuenta sobre el encargo.
        $path = 'entregas/'.Str::random(40).'.'.$extension;
        Storage::disk('local')->putFileAs('', $file, $path);

        $media = new MediaAsset([
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => (string) $file->getMimeType(),
            'size_bytes' => (int) Storage::disk('local')->size($path),
            'visibility' => 'private',
        ]);

        // El duenno del fichero es quien lo sube, o sea la artista. Quien
        // puede descargarlo es otra pregunta, y la responde OrderPolicy
        // contra el pedido — no la propiedad del media.
        $media->user_id = $artist->id;
        $media->save();

        return $media;
    }

    public function delete(MediaAsset $media): void
    {
        Storage::disk($media->visibility === 'private' ? 'local' : 'public')->delete($media->path);

        $media->delete();
    }

    /**
     * Devuelve los bytes de un JPEG nuevo con solo los pixeles de la imagen
     * original, escalado si hacia falta.
     */
    private function reencode(UploadedFile $file): string
    {
        $origen = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if ($origen === false) {
            // La validacion del Form Request ya deberia haberlo parado; si
            // llega aqui es que GD no puede decodificarlo, y entonces no se
            // guarda nada.
            throw new RuntimeException('El fichero no es una imagen que se pueda decodificar.');
        }

        $destino = $this->resizeToMaxSide($origen);

        ob_start();
        imagejpeg($destino, null, self::JPEG_QUALITY);
        $binario = (string) ob_get_clean();

        imagedestroy($destino);

        if ($destino !== $origen) {
            imagedestroy($origen);
        }

        return $binario;
    }

    /**
     * @param  \GdImage  $origen
     * @return \GdImage
     */
    private function resizeToMaxSide($origen)
    {
        $ancho = imagesx($origen);
        $alto = imagesy($origen);
        $lado = max($ancho, $alto);

        if ($lado <= self::MAX_SIDE) {
            // Aun sin escalar hay que re-encodificar: el objetivo no es el
            // tamano, es tirar todo lo que no sean pixeles.
            return $origen;
        }

        $escala = self::MAX_SIDE / $lado;
        $nuevoAncho = (int) round($ancho * $escala);
        $nuevoAlto = (int) round($alto * $escala);

        $destino = imagecreatetruecolor($nuevoAncho, $nuevoAlto);

        // Fondo blanco: las referencias salen como JPEG, que no tiene alfa.
        imagefilledrectangle($destino, 0, 0, $nuevoAncho, $nuevoAlto, imagecolorallocate($destino, 255, 255, 255));
        imagecopyresampled($destino, $origen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);

        return $destino;
    }
}
