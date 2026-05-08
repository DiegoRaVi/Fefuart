<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class CatalogDemoSeeder extends Seeder
{
    /**
     * Seed demo catalog products for local development and QA.
     */
    public function run(): void
    {
        $products = [
            [
                'name' => 'Retrato Digital Clasico',
                'description' => 'Retrato digital personalizado en estilo limpio y color suave.',
                'price' => 49.00,
                'quantity' => 1,
                'category' => 'dibujo-encargo',
                'subcategory' => 'digital',
                'delivery_type' => 'digital',
                'delivery_time' => '5 dias',
                'image_url' => null,
                'stock' => 20,
                'order_id' => null,
            ],
            [
                'name' => 'Retrato Pareja Premium',
                'description' => 'Ilustracion detallada para parejas con fondo personalizado.',
                'price' => 89.00,
                'quantity' => 1,
                'category' => 'dibujo-encargo',
                'subcategory' => 'digital',
                'delivery_type' => 'digital',
                'delivery_time' => '7 dias',
                'image_url' => null,
                'stock' => 12,
                'order_id' => null,
            ],
            [
                'name' => 'Letras Infantiles A3',
                'description' => 'Nombre ilustrado infantil en lamina A3 con tematica personalizada.',
                'price' => 35.00,
                'quantity' => 1,
                'category' => 'letras-infantiles',
                'subcategory' => 'lamina',
                'delivery_type' => 'physical',
                'delivery_time' => '10 dias',
                'image_url' => null,
                'stock' => 15,
                'order_id' => null,
            ],
            [
                'name' => 'Ramo Preservado Mini',
                'description' => 'Ramo pequeno con flores preservadas para regalo.',
                'price' => 29.50,
                'quantity' => 1,
                'category' => 'ramo-flores',
                'subcategory' => 'preservado',
                'delivery_type' => 'physical',
                'delivery_time' => '4 dias',
                'image_url' => null,
                'stock' => 18,
                'order_id' => null,
            ],
            [
                'name' => 'Ramo Preservado Deluxe',
                'description' => 'Ramo grande con composicion premium y colores a eleccion.',
                'price' => 64.90,
                'quantity' => 1,
                'category' => 'ramo-flores',
                'subcategory' => 'preservado',
                'delivery_type' => 'physical',
                'delivery_time' => '6 dias',
                'image_url' => null,
                'stock' => 10,
                'order_id' => null,
            ],
            [
                'name' => 'Pack Papeleria Evento',
                'description' => 'Pack personalizado de papeleria para celebraciones y eventos.',
                'price' => 54.00,
                'quantity' => 1,
                'category' => 'papeleria',
                'subcategory' => 'evento',
                'delivery_type' => 'physical',
                'delivery_time' => '8 dias',
                'image_url' => null,
                'stock' => 14,
                'order_id' => null,
            ],
        ];

        foreach ($products as $product) {
            Product::query()->updateOrCreate(
                [
                    'name' => $product['name'],
                    'category' => $product['category'],
                    'subcategory' => $product['subcategory'],
                    'order_id' => null,
                ],
                $product,
            );
        }
    }
}
