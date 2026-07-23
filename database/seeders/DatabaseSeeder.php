<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
        ]);

        $this->command->info('Seeding Categories...');
        $this->call(CategorySeeder::class);
        $this->command->newLine();

        $this->command->info('Seeding Brands...');
        $this->call(BrandSeeder::class);
        $this->command->newLine();

        $this->command->info('Seeding Suppliers...');
        $this->call(SupplierSeeder::class);
        $this->command->newLine();

        // $this->command->info('Seeding Customers...');
        // $this->call(CustomerSeeder::class);
        // $this->command->newLine();

        $this->command->info('Seeding Products...');
        $this->call(ProductSeeder::class);
        $this->command->newLine();
    }
}
