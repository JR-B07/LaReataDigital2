<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EventosSeeder extends Seeder
{
    public function run(): void
    {
        $lienzoId = DB::table('lienzos')
            ->where('nombre', 'Lienzo Charro La Reata')
            ->where('ciudad', 'San Luis Potosí')
            ->value('id');

        if (! $lienzoId) {
            return;
        }

        DB::table('eventos')->updateOrInsert(
            [
                'id_lienzo' => $lienzoId,
                'nombre' => 'Charreada San Luis',
                'fecha' => '2026-05-15',
            ],
            [
                'hora' => '20:00:00',
                'estatus' => 'activo',
                'tipo_codigo' => 'barra',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
