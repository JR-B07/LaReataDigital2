<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InfraestructuraEventoSeeder extends Seeder
{
    public function run(): void
    {
        $event = DB::table('eventos')->orderBy('id')->first();

        if (! $event) {
            return;
        }

        $lienzoId = $event->id_lienzo;

        $zones = [
            ['nombre' => 'Gradas', 'precio' => 180.00, 'filas' => 4, 'asientos_por_fila' => 20],
            ['nombre' => 'Preferente', 'precio' => 280.00, 'filas' => 3, 'asientos_por_fila' => 16],
            ['nombre' => 'VIP', 'precio' => 420.00, 'filas' => 2, 'asientos_por_fila' => 12],
        ];

        foreach ($zones as $zone) {
            $zoneId = DB::table('zonas')->where('id_lienzo', $lienzoId)->where('nombre', $zone['nombre'])->value('id');

            if (! $zoneId) {
                DB::table('zonas')->insert([
                    'id_lienzo' => $lienzoId,
                    'nombre' => $zone['nombre'],
                    'precio' => $zone['precio'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $zoneId = DB::table('zonas')->where('id_lienzo', $lienzoId)->where('nombre', $zone['nombre'])->value('id');
            }

            if (! $zoneId) {
                continue;
            }

            for ($row = 1; $row <= $zone['filas']; $row++) {
                $rowName = 'Fila '.chr(64 + $row);
                $filaId = DB::table('filas')->where('id_zona', $zoneId)->where('nombre', $rowName)->value('id');

                if (! $filaId) {
                    DB::table('filas')->insert([
                        'id_zona' => $zoneId,
                        'nombre' => $rowName,
                    ]);

                    $filaId = DB::table('filas')->where('id_zona', $zoneId)->where('nombre', $rowName)->value('id');
                }

                if (! $filaId) {
                    continue;
                }

                for ($seat = 1; $seat <= $zone['asientos_por_fila']; $seat++) {
                    $asientoId = DB::table('asientos')->where('id_fila', $filaId)->where('numero', $seat)->value('id');

                    if (! $asientoId) {
                        DB::table('asientos')->insert([
                            'id_fila' => $filaId,
                            'numero' => $seat,
                        ]);

                        $asientoId = DB::table('asientos')->where('id_fila', $filaId)->where('numero', $seat)->value('id');
                    }

                    if (! $asientoId) {
                        continue;
                    }

                    $qr = sprintf('QR-E%03d-A%05d', $event->id, $asientoId);
                    $bar = sprintf('BAR-E%03d-A%05d', $event->id, $asientoId);

                    DB::table('boletos')->insertOrIgnore([
                        'id_evento' => $event->id,
                        'id_asiento' => $asientoId,
                        'precio' => $zone['precio'],
                        'codigo_barras' => $bar,
                        'codigo_qr' => $qr,
                        'estado' => 'disponible',
                        'escaneado' => false,
                        'id_promotor' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}
