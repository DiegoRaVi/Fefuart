<?php

namespace App\Console\Commands;

use App\Models\MediaAsset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Borra los ficheros que nadie llego a usar.
 *
 * La foto de referencia se sube antes de anadir la linea al carrito (N9: el
 * formulario enseña la miniatura para que el cliente sepa que ha entrado),
 * asi que quien elige una imagen y se va deja el fichero sin dueno. Tambien
 * lo deja quien borra una linea despues. Sin esto se acumulan para siempre.
 *
 * El margen por defecto es generoso a proposito: alguien puede tener el
 * formulario abierto con la foto ya subida y estar todavia escribiendo las
 * notas del encargo.
 */
class LimpiarMediaHuerfanos extends Command
{
    protected $signature = 'media:limpiar {--horas=24 : Margen antes de considerar huerfano un fichero}';

    protected $description = 'Borra los ficheros subidos que no han acabado en ninguna linea de pedido';

    public function handle(): int
    {
        $limite = now()->subHours((int) $this->option('horas'));

        $huerfanos = MediaAsset::query()
            ->where('created_at', '<', $limite)
            // Ni foto de partida ni entrega digital: nadie lo reclama.
            ->whereNotExists(fn ($q) => $q->selectRaw('1')
                ->from('order_items')
                ->whereColumn('order_items.reference_media_id', 'media_assets.id'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')
                ->from('order_items')
                ->whereColumn('order_items.delivered_media_id', 'media_assets.id'))
            ->get();

        foreach ($huerfanos as $media) {
            // `delete` del disco no falla si el fichero ya no esta, y la fila
            // tiene que irse igualmente: si no, el comando se quedaria
            // atascado en la misma fila cada vez que se ejecute.
            Storage::disk($media->visibility === 'private' ? 'local' : 'public')
                ->delete($media->path);

            $media->delete();
        }

        $this->info("Ficheros huerfanos borrados: {$huerfanos->count()}");

        return self::SUCCESS;
    }
}
