<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Brand;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $brands = [
        ];

        foreach ($brands as $brand) {
            Brand::create($brand);
        }

        $this->command->info('Pharmacy brands seeded successfully!');
    }
}
