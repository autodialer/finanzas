<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'jose@finanzas.com'],
            ['name' => 'Jose Carlos', 'password' => Hash::make('Finanzas2024!')]
        );

        User::firstOrCreate(
            ['email' => 'asistente@finanzas.com'],
            ['name' => 'Asistente', 'password' => Hash::make('Finanzas2024!')]
        );
    }
}
