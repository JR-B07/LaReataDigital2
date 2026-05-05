<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EventosSeeder extends Seeder
{
    public function run(): void
    {
        // Crear evento para el lienzo específico si existe
        $lienzoId = DB::table('lienzos')
            ->where('nombre', 'Lienzo Charro La Reata')
            ->where('ciudad', 'San Luis Potosí')
            ->value('id');

        if ($lienzoId) {
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

        // Crear eventos para cualquier lienzo sin eventos
        $lienzosConEventos = DB::table('eventos')
            ->pluck('id_lienzo')
            ->unique();

        $lienzosSinEventos = DB::table('lienzos')
            ->whereNotIn('id', $lienzosConEventos)
            ->get();

        foreach ($lienzosSinEventos as $lienzo) {
            DB::table('eventos')->insert([
                'id_lienzo' => $lienzo->id,
                'nombre' => 'Evento - ' . $lienzo->nombre,
                'fecha' => now()->addDays(7)->toDateString(),
                'hora' => '20:00:00',
                'estatus' => 'activo',
                'tipo_codigo' => 'barra',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
