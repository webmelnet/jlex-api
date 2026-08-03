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
            ['email' => 'superadmin@jlexpharmacy.com'],
            [
                'name' => 'superadmin',
                'email_verified_at' => now(),
                'password' => Hash::make('MelCore#3'),
            ]
        );
        $superadminAdmin->assignRole($superAdminRole);
        $superadminAdmin->createToken('auth_token')->plainTextToken;

        $staff = [
            ['login' => 'raisa', 'full_name' => 'Raisa de Jesus', 'email' => 'raisa.dejesus@jlexpharmacy.com', 'password' => '482913', 'roles' => [$cachierRole, $adminRole, $managerRole]],
            ['login' => 'easter', 'full_name' => 'Easter Tan', 'email' => 'easter.tan@jlexpharmacy.com', 'password' => '719264', 'roles' => [$cachierRole, $adminRole]],
            ['login' => 'ruthanne', 'full_name' => 'Ruth Anne de Jesus', 'email' => 'ruthanne.dejesus@jlexpharmacy.com', 'password' => '356807', 'roles' => [$adminRole, $managerRole, $cachierRole]],
            ['login' => 'rina', 'full_name' => 'Rina Cabiles', 'email' => 'rina.cabiles@jlexpharmacy.com', 'password' => '594182', 'roles' => [$pharmacyAssistantRole, $cachierRole]],
            ['login' => 'ronald', 'full_name' => 'Ronald Pacheco', 'email' => 'ronald.pacheco@jlexpharmacy.com', 'password' => '827356', 'roles' => [$pharmacyAssistantRole, $cachierRole]],
            ['login' => 'cristelmanlapaz', 'full_name' => 'Cristel Manlapaz', 'email' => 'cristel.manlapaz@jlexpharmacy.com', 'password' => '148592', 'roles' => [$pharmacyAssistantRole, $cachierRole]],
            ['login' => 'cristelviray', 'full_name' => 'Cristel Viray', 'email' => 'cristel.viray@jlexpharmacy.com', 'password' => '673024', 'roles' => [$pharmacyAssistantRole]],
        ];

        foreach ($staff as $person) {
            $user = User::firstOrCreate(
                ['email' => $person['email']],
                [
                    'name' => $person['login'],
                    'full_name' => $person['full_name'],
                    'email_verified_at' => now(),
                    'password' => Hash::make($person['password']),
                    'require_dtr' => true,
                    'daily_hours_required' => 8,
                ]
            );
            $user->assignRole($person['roles']);
            $user->createToken('auth_token')->plainTextToken;
        }
    }
}
