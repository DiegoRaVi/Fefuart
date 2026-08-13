<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * N7 — que entregas admite cada variante. Modela «envio digital solo si el
 * estilo es digital», que en v1 era un `if` dentro de un `<script>`.
 *
 * Que sea una tabla y no una columna importa: la administradora puede
 * habilitar la entrega digital de una variante nueva sin tocar codigo (D5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('variant_shipping_method', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipping_method_id')->constrained()->cascadeOnDelete();

            $table->primary(['product_variant_id', 'shipping_method_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_shipping_method');
    }
};
