<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompanyReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companyIds = DB::table('companies')->pluck('id')->all();
        $seekerIds = DB::table('job_seekers')->pluck('id')->all();

        foreach ($companyIds as $cid) {
            for ($i = 0; $i < rand(2, 6); $i++) {
                DB::table('company_reviews')->insert([
                    'company_id' => $cid,
                    'job_seeker_id' => fake()->randomElement($seekerIds),
                    'rating_overall' => rand(1, 5),
                    'title' => fake()->optional()->sentence(),
                    'review_text' => fake()->paragraphs(rand(1, 3), true),
                    'employment_status' => fake()->randomElement(['current','former']),
                    'job_title_at_time' => fake()->optional()->jobTitle(),
                    'city' => fake()->optional()->city(),
                    'country_code' => fake()->randomElement(['US','GB','IN','DE','CA']),
                    'posted_at' => now()->subDays(rand(0, 90)),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
