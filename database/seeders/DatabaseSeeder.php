<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->updateOrCreate([
            'email' => 'admin@lareata.test',
        ], [
            'name' => 'Administrador LaReata',
            'phone' => '5511111111',
            'role' => 'admin',
            'password' => Hash::make('password123'),
        ]);

        User::query()->updateOrCreate([
            'email' => 'validador@lareata.test',
        ], [
            'name' => 'Validador Demo',
            'phone' => '5522222222',
            'role' => 'validator',
            'password' => Hash::make('password123'),
        ]);

        User::query()->updateOrCreate([
            'email' => 'comprador@lareata.test',
        ], [
            'name' => 'Comprador Demo',
            'phone' => '5533333333',
            'role' => 'buyer',
            'password' => Hash::make('password123'),
        ]);
    }
}
