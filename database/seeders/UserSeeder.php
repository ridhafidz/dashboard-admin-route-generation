<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Driver;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Pastikan role dasar udah ada, kalau belum, bikin dulu
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'ops']);
        Role::firstOrCreate(['name' => 'driver']);

        // 1. Admin
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@testing.com',
            'password' => 'adikakenjeran123',
        ]);
        $admin->assignRole('admin');

        // 2. Driver 1
        $driver1 = User::create([
            'name' => 'Budi Santoso',
            'email' => 'driver1@test.com',
            'password' => 'password',
        ]);
        $driver1->assignRole('driver');

        Driver::create([
            'user_id' => $driver1->id,
            'name' => $driver1->name,
            'phone' => '081234567890',
            'status' => 'active',
        ]);

        // 3. Driver 2
        $driver2 = User::create([
            'name' => 'Andi Wijaya',
            'email' => 'driver2@test.com',
            'password' => 'password',
        ]);
        $driver2->assignRole('driver');

        Driver::create([
            'user_id' => $driver2->id,
            'name' => $driver2->name,
            'phone' => '081298765432',
            'status' => 'active',
        ]);
    }
}