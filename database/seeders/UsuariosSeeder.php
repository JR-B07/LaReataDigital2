<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsuariosSeeder extends Seeder
{
    public function run(): void
    {
        $usuarios = [
            [
                'nombre' => 'José Alan Hernández Méndez',
                'usuario' => 'superadmin1',
                'password' => Hash::make('12345678'),
                'telefono' => '0000000001',
                'rol' => 'superadministrador',
            ],
            [
                'nombre' => 'Jose Ricardo Becerra Cobarrubias',
                'usuario' => 'superadmin2',
                'password' => Hash::make('12345678'),
                'telefono' => '0000000002',
                'rol' => 'superadministrador',
            ],
            [
                'nombre' => 'Administrador',
                'usuario' => 'admin',
                'password' => Hash::make('12345678'),
                'telefono' => '0000000003',
                'rol' => 'administrador',
            ],
        ];

        foreach ($usuarios as $row) {
            DB::table('usuarios')->updateOrInsert(
                ['usuario' => $row['usuario']],
                $row + ['activo' => true, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        for ($i = 1; $i <= 6; $i++) {
            DB::table('usuarios')->updateOrInsert(
                ['usuario' => 'vendedor' . $i],
                [
                    'nombre' => 'Vendedor ' . $i,
                    'password' => Hash::make('12345678'),
                    'telefono' => '200000000' . $i,
                    'rol' => 'vendedor',
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        for ($i = 1; $i <= 6; $i++) {
            DB::table('usuarios')->updateOrInsert(
                ['usuario' => 'checador' . $i],
                [
                    'nombre' => 'Checador ' . $i,
                    'password' => Hash::make('12345678'),
                    'telefono' => '300000000' . $i,
                    'rol' => 'checador',
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        DB::table('usuarios')->updateOrInsert(
            ['usuario' => 'promotor1'],
            [
                'nombre' => 'Promotor',
                'password' => Hash::make('12345678'),
                'telefono' => '4000000001',
                'rol' => 'promotor',
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
