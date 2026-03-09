<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EventosSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('eventos')->insert([
            [
                'id_lienzo' => 1,
                'nombre' => 'Charreada San Luis',
                'fecha' => '2026-05-15',
                'hora' => '20:00:00',
                'estatus' => 'activo',
                'tipo_codigo' => 'barra',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }
}