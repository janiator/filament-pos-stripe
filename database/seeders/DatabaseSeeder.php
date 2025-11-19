<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create or get the super_admin role
        $superAdminRole = Role::firstOrCreate(
            ['name' => 'super_admin'],
            ['guard_name' => 'web']
        );

        // Create admin store (tenant)
        $store = Store::firstOrCreate(
            ['slug' => 'visivo-admin'],
            [
                'name' => 'Visivo Admin',
                'email' => 'admin@pos.visivo.no',
            ]
        );

        // Create admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@pos.visivo.no'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('admin'),
                'email_verified_at' => now(),
            ]
        );

        // Assign super_admin role to admin user
        if (!$admin->hasRole('super_admin')) {
            $admin->assignRole('super_admin');
        }

        // Assign store to admin user
        if (!$admin->stores->contains($store)) {
            $admin->stores()->attach($store);
        }

        $this->command->info('Admin user created: admin@pos.visivo.no / admin');
        $this->command->info('Admin store created: ' . $store->name);
    }
}
