<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CatalogSeeder::class);

        // DB-008 — la base local no tenia datos reales, solo fixtures de los
        // E2E de la rama descartada. Estas dos cuentas son para probar a mano
        // en local; la promocion a admin nunca ocurre por peticion (N20), asi
        // que un seeder es una de sus dos unicas vias.
        if (! app()->environment('production')) {
            User::factory()->admin()->create([
                'name' => 'Felicitas Varela',
                'email' => 'admin@fefuart.test',
            ]);

            User::factory()->create([
                'name' => 'Cliente de prueba',
                'email' => 'cliente@fefuart.test',
            ]);
        }
    }
}
