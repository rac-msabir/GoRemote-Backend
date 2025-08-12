<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Company;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $countries = ['US','GB','IN','DE','CA'];

        for ($i = 0; $i < 10; $i++) {
            $company = Company::create([
                'name' => fake()->unique()->company(),
                'website' => fake()->optional()->url(),
                'country_code' => fake()->randomElement($countries),
            ]);

            // salaries
            for ($s = 0; $s < rand(3, 6); $s++) {
                DB::table('company_salaries')->insert([
                    'company_id' => $company->id,
                    'job_title' => fake()->jobTitle(),
                    'city' => fake()->optional()->city(),
                    'country_code' => $company->country_code,
                    'pay_min' => fake()->randomFloat(2, 30000, 90000),
                    'pay_max' => fake()->randomFloat(2, 90000, 220000),
                    'pay_period' => fake()->randomElement(['year','month']),
                    'data_source' => fake()->randomElement(['user_reported','aggregated','employer_provided']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
