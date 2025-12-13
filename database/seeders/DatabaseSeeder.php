<?php

namespace Database\Seeders;

use App\Actions\SyncEverythingFromStripe;
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
            ['email' => 'admin@visivo.no'],
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

        $this->command->info('Admin user created: admin@visivo.no / admin');
        $this->command->info('Admin store created: ' . $store->name);

        // Run Stripe sync after seeding (only if not in testing environment)
        if (!app()->environment('testing')) {
            $this->command->newLine();
            $this->command->info('Syncing everything from Stripe...');

            try {
                $syncAction = new SyncEverythingFromStripe();
                $result = $syncAction(false); // Don't send notifications from seeder

                $this->command->info("✓ Sync completed!");
                $this->command->line("  Found: {$result['total']} items");
                $this->command->line("  Created: {$result['created']} items");
                $this->command->line("  Updated: {$result['updated']} items");

                if (!empty($result['errors'])) {
                    $errorCount = count($result['errors']);
                    $this->command->warn("  Errors: {$errorCount} error(s) occurred");
                    if ($errorCount <= 5) {
                        foreach ($result['errors'] as $error) {
                            $this->command->error("    - {$error}");
                        }
                    } else {
                        foreach (array_slice($result['errors'], 0, 5) as $error) {
                            $this->command->error("    - {$error}");
                        }
                        $this->command->warn("    ... and " . ($errorCount - 5) . " more error(s)");
                    }
                }
            } catch (\Throwable $e) {
                $this->command->error("✗ Sync failed: {$e->getMessage()}");
                // Don't throw - we don't want to fail the migration if sync fails
            }
        }
    }
}
