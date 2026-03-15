<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BarraProductosSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['nombre' => 'Cerveza Lata 355ml', 'precio' => 55, 'stock' => 240, 'activo' => true],
            ['nombre' => 'Cerveza Premium 355ml', 'precio' => 70, 'stock' => 120, 'activo' => true],
            ['nombre' => 'Whisky Vaso', 'precio' => 140, 'stock' => 90, 'activo' => true],
            ['nombre' => 'Tequila Caballito', 'precio' => 120, 'stock' => 100, 'activo' => true],
            ['nombre' => 'Vodka Preparado', 'precio' => 110, 'stock' => 80, 'activo' => true],
            ['nombre' => 'Agua Mineral', 'precio' => 35, 'stock' => 200, 'activo' => true],
        ];

        foreach ($products as $product) {
            DB::table('barra_productos')->updateOrInsert(
                ['nombre' => $product['nombre']],
                [
                    'precio' => $product['precio'],
                    'stock' => $product['stock'],
                    'activo' => $product['activo'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
