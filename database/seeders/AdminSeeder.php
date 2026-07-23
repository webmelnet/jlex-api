<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create roles
        $superAdminRole = Role::firstOrCreate(['name' => 'Superadmin']);
        $adminRole = Role::firstOrCreate(['name' => 'Admin']);
        $managerRole = Role::firstOrCreate(['name' => 'Manager']);
        $cachierRole = Role::firstOrCreate(['name' => 'Cashier']);
        $pharmacyAssistantRole = Role::firstOrCreate(['name' => 'Pharmacy Assistant']);

        $superadminAdmin = User::firstOrCreate(
            ['email' => 'superadmin@melcore.com'],
            [
                'name' => 'Superadmin',
                'email_verified_at' => now(),
                'password' => Hash::make('MelCore#3'),
            ]
        );
        $superadminAdmin->assignRole($superAdminRole);
        $superadminAdmin->createToken('auth_token')->plainTextToken;

        $admin = User::firstOrCreate(
            ['email' => 'admin@melcore.com'],
            [
                'name' => 'Admin',
                'email_verified_at' => now(),
                'password' => Hash::make('MelCore#3'),
            ]
        );
        $admin->assignRole($adminRole);
        $admin->createToken('auth_token')->plainTextToken;

        $manager = User::firstOrCreate(
            ['email' => 'manager@melcore.com'],
            [
                'name' => 'Manager',
                'email_verified_at' => now(),
                'password' => Hash::make('MelCore#3'),
            ]
        );
        $manager->assignRole($managerRole);
        $manager->createToken('auth_token')->plainTextToken;

        $cashier = User::firstOrCreate(
            ['email' => 'cashier@melcore.com'],
            [
                'name' => 'Cashier',
                'email_verified_at' => now(),
                'password' => Hash::make('MelCore#3'),
            ]
        );
        $cashier->assignRole($cachierRole);
        $cashier->createToken('auth_token')->plainTextToken;

        $pharmacyAssistant = User::firstOrCreate(
            ['email' => 'pharmacy@melcore.com'],
            [
                'name' => 'Pharmacy Assistant',
                'email_verified_at' => now(),
                'password' => Hash::make('MelCore#3'),
            ]
        );
        $pharmacyAssistant->assignRole($pharmacyAssistantRole);
        $pharmacyAssistant->createToken('auth_token')->plainTextToken;
    }
}
