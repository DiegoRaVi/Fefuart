<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D33 — la galeria, gestionada desde el backoffice.
 *
 * **No cuelga de `products`, y es deliberado.** Lo natural seria atar cada
 * pieza a lo que se vende, y dejaria fuera justo lo que trae encargos: en el
 * archivo de Felicitas hay una carpeta «Papeleria» —invitaciones, seating
 * plans— que no esta en el catalogo. La categoria es texto libre acotado en
 * el Form Request, no una clave foranea.
 *
 * Las dos imagenes van en `media_assets` como todo lo demas: la grande para
 * el visor y la miniatura para la rejilla. Sin la segunda, una cuadricula de
 * treinta piezas descarga las treinta a tamano completo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_pieces', function (Blueprint $table) {
            $table->id();

            $table->foreignId('media_asset_id')->constrained('media_assets');
            $table->foreignId('thumbnail_media_id')->constrained('media_assets');

            $table->string('title');
            $table->string('category', 50);
            $table->text('description')->nullable();

            $table->boolean('is_published')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            // Por donde se consulta siempre: el escaparate publico, en orden.
            $table->index(['is_published', 'sort_order']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_pieces');
    }
};
