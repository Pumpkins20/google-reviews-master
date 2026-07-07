<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        \App\Models\User::firstOrCreate(
            ['email' => 'admin@company.com'],
            [
                'name' => 'Admin GRMS',
                'password' => \Illuminate\Support\Facades\Hash::make('password123'),
            ]
        );
    }
}
