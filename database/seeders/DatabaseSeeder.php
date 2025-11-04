<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\Hash;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Admin User',
        //     'email' => 'admin@example.com',
        //     'password' => Hash::make('password123'),
        //     'role' => 'admin',
        // ]);

        // User::factory()->create([
        //     'name' => 'Regular User',
        //     'email' => 'user@example.com',
        //     'password' => Hash::make('password123'),
        //     'gaji_bulanan' => 5000000,
        //     'role' => 'user',
        // ]);
        $this->call(KategoriPengeluaranSeeder::class);
    }

    
}
