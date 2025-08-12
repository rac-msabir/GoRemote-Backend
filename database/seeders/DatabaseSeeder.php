<?php

namespace Database\Seeders;

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
        // Core entities
        $this->call([
            TaxonomySeeder::class,
            EmployerSeeder::class,
            JobSeekerSeeder::class,
            CompanySeeder::class,
            JobSeeder::class,
            CompanyReviewSeeder::class,
            ApplicationSeeder::class,
        ]);
    }
}
