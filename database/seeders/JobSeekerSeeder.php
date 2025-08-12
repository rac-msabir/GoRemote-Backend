<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\JobSeeker;
use App\Models\Resume;
use App\Models\SeekerDesiredTitle;

class JobSeekerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create job seekers and their profiles
        for ($i = 0; $i < 15; $i++) {
            $user = User::factory()->create([
                'role' => 'seeker',
                'provider' => 'local',
            ]);

            $seeker = JobSeeker::create([
                'user_id' => $user->id,
                'city' => fake()->optional()->city(),
                'state_province' => fake()->optional()->state(),
                'postal_code' => fake()->postcode(),
                'country_code' => fake()->randomElement(['US','GB','IN','DE','CA']),
                'remote_preference' => fake()->boolean(50),
                'min_base_pay' => fake()->optional()->randomFloat(2, 30000, 120000),
                'min_pay_period' => fake()->optional()->randomElement(['year','month','week','day','hour']),
            ]);

            // Desired titles
            $desiredCount = rand(1, 3);
            for ($t = 0; $t < $desiredCount; $t++) {
                SeekerDesiredTitle::create([
                    'job_seeker_id' => $seeker->id,
                    'title' => fake()->jobTitle(),
                    'priority' => $t,
                ]);
            }

            // Resumes
            $resumeCount = rand(0, 2);
            for ($r = 0; $r < $resumeCount; $r++) {
                Resume::create([
                    'job_seeker_id' => $seeker->id,
                    'file_path' => 'resumes/'.fake()->uuid().'.pdf',
                    'file_name' => fake()->lastName().'_resume.pdf',
                    'mime_type' => 'application/pdf',
                    'size_bytes' => rand(50_000, 500_000),
                    'is_public' => fake()->boolean(30),
                ]);
            }
        }

        // Saved jobs for seekers (requires existing jobs)
        $seekerIds = DB::table('job_seekers')->pluck('id')->all();
        $jobIds = DB::table('jobs')->pluck('id')->all();
        foreach ($seekerIds as $sid) {
            shuffle($jobIds);
            foreach (array_slice($jobIds, 0, rand(2, 6)) as $jid) {
                DB::table('saved_jobs')->insertOrIgnore([
                    'job_seeker_id' => $sid,
                    'job_id' => $jid,
                    'created_at' => now()->subDays(rand(0, 30)),
                ]);
            }
        }
    }
}
