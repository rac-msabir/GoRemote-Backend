<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Job;
use App\Models\Application;

class JobSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Link seekers with applications to jobs
        $seekerIds = DB::table('job_seekers')->pluck('id')->all();
        $resumeIdsBySeeker = DB::table('resumes')->select('id','job_seeker_id')->get()->groupBy('job_seeker_id');
        $jobIds = DB::table('jobs')->pluck('id')->all();

        foreach ($jobIds as $jobId) {
            $applyCount = rand(3, 8);
            shuffle($seekerIds);
            foreach (array_slice($seekerIds, 0, $applyCount) as $seekerId) {
                $resumeId = optional($resumeIdsBySeeker->get($seekerId))->random()->id ?? null;
                DB::table('applications')->insertOrIgnore([
                    'job_seeker_id' => $seekerId,
                    'job_id' => $jobId,
                    'resume_id' => $resumeId,
                    'status' => fake()->randomElement(['applied','reviewed','interviewing','rejected']),
                    'applied_at' => now()->subDays(rand(0, 30)),
                    'updated_at' => now()->subDays(rand(0, 10)),
                    'external_redirect' => fake()->boolean(15),
                    'notes_internal' => fake()->optional()->sentence(),
                ]);
            }
        }
    }
}
