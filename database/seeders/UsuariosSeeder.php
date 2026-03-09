<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsuariosSeeder extends Seeder
{
    public function run(): void
    {

        
        DB::table('usuarios')->insert([
            [
                'nombre' => 'José Alan Hernández Méndez',
                'usuario' => 'superadmin1',
                'password' => Hash::make('12345678'),
                'telefono' => '0000000001',
                'rol' => 'superadministrador',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'nombre' => 'Jose Ricardo Becerra Cobarrubias',
                'usuario' => 'superadmin2',
                'password' => Hash::make('12345678'),
                'telefono' => '0000000002',
                'rol' => 'superadministrador',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);

        DB::table('usuarios')->insert([
            'nombre' => 'Administrador',
            'usuario' => 'admin',
            'password' => Hash::make('12345678'),
            'telefono' => '0000000003',
            'rol' => 'administrador',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        for ($i = 1; $i <= 6; $i++) {
            DB::table('usuarios')->insert([
                'nombre' => 'Vendedor '.$i,
                'usuario' => 'vendedor'.$i,
                'password' => Hash::make('12345678'),
                'telefono' => '200000000'.$i,
                'rol' => 'vendedor',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        for ($i = 1; $i <= 6; $i++) {
            DB::table('usuarios')->insert([
                'nombre' => 'Checador '.$i,
                'usuario' => 'checador'.$i,
                'password' => Hash::make('12345678'),
                'telefono' => '300000000'.$i,
                'rol' => 'checador',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        DB::table('usuarios')->insert([
            'nombre' => 'Promotor',
            'usuario' => 'promotor1',
            'password' => Hash::make('12345678'),
            'telefono' => '4000000001',
            'rol' => 'promotor',
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}