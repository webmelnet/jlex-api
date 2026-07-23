<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Customer;
use Faker\Factory as Faker;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('en_PH');

        $cities = [
            
        ];

        $notes = [
           
        ];

        for ($i = 0; $i < 40; $i++) {
            $location = $faker->randomElement($cities);
            Customer::create([
                'name'           => $faker->name,
                'email'          => $faker->boolean(60) ? $faker->unique()->safeEmail : null,
                'phone'          => $faker->mobileNumber,
                'address'        => $faker->streetAddress,
                'city'           => $location['city'],
                'state'          => $location['state'],
                'zip_code'       => $faker->postcode,
                'country'        => 'Philippines',
                'loyalty_points' => $faker->numberBetween(0, 2500),
                'notes'          => $faker->randomElement($notes),
                'is_active'      => $faker->boolean(95),
            ]);
        }

        $notableCustomers = [
            
        ];

        foreach ($notableCustomers as $customer) {
            Customer::create($customer);
        }

        $this->command->info('Pharmacy customers seeded successfully!');
    }
}
