<?php

namespace Database\Seeders;

use App\Enums\DeliveryType;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use Illuminate\Database\Seeder;

/**
 * El catalogo real del negocio (N1), extraido de los formularios de v1 donde
 * vivia dentro de un `<script>`.
 *
 * Todo lo que hay aqui es semilla, no configuracion: D5 exige que la
 * administradora pueda cambiar precios y variantes desde el backoffice sin
 * tocar codigo. Los importes de P4 quedaron fijados asi y son editables.
 *
 * Los nombres van sin tildes, como el resto del arbol. Son los primeros
 * candidatos a corregirse desde el backoffice.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $fisico = ShippingMethod::query()->updateOrCreate(
            ['code' => DeliveryType::Physical],
            ['name' => 'Envío a domicilio', 'price' => '5.00', 'is_active' => true, 'sort_order' => 0],
        );

        $digital = ShippingMethod::query()->updateOrCreate(
            ['code' => DeliveryType::Digital],
            ['name' => 'Descarga digital', 'price' => '0.00', 'is_active' => true, 'sort_order' => 1],
        );

        // N9 — dibujo y ramos se dibujan a partir de la foto del cliente; las
        // letras, no. N7 — solo el estilo «Digital» admite entrega digital,
        // que en v1 era un `if` dentro del formulario.
        $this->product(
            slug: 'dibujo-por-encargo',
            name: 'Dibujo por encargo',
            description: 'Retrato dibujado a partir de tu fotografía.',
            category: 'dibujo',
            sortOrder: 0,
            requiresReferenceImage: true,
            requiresNotes: true,
            variants: [
                ['Diseño de moda', '30.00', [$fisico]],
                ['Acuarela', '40.00', [$fisico]],
                ['Digital', '20.00', [$fisico, $digital]],
            ],
        );

        $this->product(
            slug: 'letras-infantiles',
            name: 'Letras infantiles',
            description: 'Letras ilustradas para la habitación de los peques.',
            category: 'letras',
            sortOrder: 1,
            requiresReferenceImage: false,
            requiresNotes: false,
            variants: [
                ['Lámina ilustrada', '40.00', [$fisico]],
            ],
        );

        $this->product(
            slug: 'ramos-dibujados',
            name: 'Ramos dibujados',
            description: 'Tu ramo de novia convertido en lámina.',
            category: 'ramos',
            sortOrder: 2,
            requiresReferenceImage: true,
            requiresNotes: true,
            variants: [
                ['Lámina del ramo', '40.00', [$fisico]],
            ],
        );
    }

    /**
     * @param  list<array{0: string, 1: string, 2: list<ShippingMethod>}>  $variants
     */
    private function product(
        string $slug,
        string $name,
        string $description,
        string $category,
        int $sortOrder,
        bool $requiresReferenceImage,
        bool $requiresNotes,
        array $variants,
    ): void {
        $product = Product::query()->updateOrCreate(['slug' => $slug], [
            'name' => $name,
            'description' => $description,
            'category' => $category,
            'is_active' => true,
            'sort_order' => $sortOrder,
            'requires_reference_image' => $requiresReferenceImage,
            'requires_notes' => $requiresNotes,
            'max_quantity' => 10,
            'delivery_days' => 15,
        ]);

        foreach ($variants as $index => [$variantName, $price, $methods]) {
            /** @var ProductVariant $variant */
            $variant = $product->variants()->updateOrCreate(['name' => $variantName], [
                // D26 — la primera copia paga el trabajo artistico; las
                // siguientes, solo la impresion. Tambien en «Digital»: esa
                // variante se puede imprimir, y entonces la copia se cobra.
                // Lo que no admite copias es la *entrega* digital, porque es
                // el mismo fichero, y eso lo corta CartService.
                'price' => $price,
                'additional_copy_price' => '10.00',
                'is_active' => true,
                'sort_order' => $index,
            ]);

            $variant->shippingMethods()->sync(collect($methods)->pluck('id'));
        }
    }
}
