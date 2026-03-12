<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LienzosSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('lienzos')->updateOrInsert(
            [
                'nombre' => 'Lienzo Charro La Reata',
                'ciudad' => 'San Luis Potosí',
            ],
            [
                'capacidad_total' => 2000,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
