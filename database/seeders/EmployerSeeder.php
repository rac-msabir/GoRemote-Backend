<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Employer;
use App\Models\EmployerUser;
use App\Models\JobDescription;
use App\Models\Job;

class EmployerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ------------------------------
        // Seed baseline job benefits
        // ------------------------------
        $benefits = [
            'Health insurance', 'Dental insurance', 'Vision insurance', '401(k)',
            'Paid time off', 'Parental leave', 'Remote work stipend', 'Gym membership',
            'Learning budget', 'Commuter benefits',
        ];

        foreach ($benefits as $benefitName) {
            DB::table('job_benefits')->updateOrInsert(
                ['name' => $benefitName],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }

        // ------------------------------
        // Static options
        // ------------------------------
        $jobTypes = ['full_time','part_time','temporary','contract','internship','fresher'];
        $locationTypes = ['on_site','hybrid','remote'];
        $payPeriods = ['hour','day','week','month','year'];

        // ------------------------------
        // Create Employers + Users + Jobs
        // ------------------------------
        for ($i = 0; $i < 5; $i++) {
            $employer = Employer::create([
                'company_name' => fake()->company(),
                'website' => fake()->optional()->url(),
                'country_code' => fake()->randomElement(['US','GB','IN','DE','CA']),
            ]);

            // Owner user for employer
            $ownerUser = \App\Models\User::factory()->create([
                'role' => 'employer',
                'provider' => 'local',
            ]);

            EmployerUser::create([
                'employer_id' => $employer->id,
                'user_id' => $ownerUser->id,
                'role' => 'owner',
            ]);

            // Additional employer users
            foreach (['manager','recruiter'] as $role) {
                $user = \App\Models\User::factory()->create([
                    'role' => 'employer',
                    'provider' => 'local',
                ]);
                EmployerUser::create([
                    'employer_id' => $employer->id,
                    'user_id' => $user->id,
                    'role' => $role,
                ]);
            }

            // ------------------------------
            // Jobs for employer
            // ------------------------------
            $numJobs = rand(3, 7);
            for ($j = 0; $j < $numJobs; $j++) {
                $title = fake()->jobTitle();

                $job = Job::create([
                    'employer_id' => $employer->id,
                    'title' => $title,
                    'slug' => Str::slug($title.'-'.uniqid()),
                    'description' => null, // moved to job_descriptions
                    'location_type' => fake()->randomElement($locationTypes),
                    'city' => fake()->optional()->city(),
                    'state_province' => fake()->optional()->state(),
                    'country_code' => $employer->country_code,
                    'country_name' => $employer->country_code,
                    'location' => null,
                    'job_type' => fake()->randomElement($jobTypes),
                    'pay_visibility' => fake()->randomElement(['range','exact','starting_at']),
                    'pay_min' => fake()->optional()->randomFloat(2, 30000, 120000),
                    'pay_max' => fake()->optional()->randomFloat(2, 80000, 200000),
                    'currency' => 'USD',
                    'pay_period' => fake()->optional()->randomElement($payPeriods),
                    'status' => fake()->randomElement(['draft','published']),
                    'is_featured' => fake()->boolean(20),
                    'is_pinned' => fake()->boolean(10),
                    'posted_at' => now()->subDays(rand(0, 60)),
                ]);

                // ------------------------------
                // Job Preferences (1:1)
                // ------------------------------
                DB::table('job_preferences')->insert([
                    'job_id' => $job->id,
                    'daily_updates_email' => fake()->optional()->safeEmail(),
                    'notify_each_application' => fake()->boolean(60),
                    'resume_required' => fake()->boolean(80),
                    'allow_candidate_email' => fake()->boolean(40),
                    'hiring_timeline' => fake()->randomElement(['asap','1_2_weeks','2_4_weeks','1_3_months','flexible']),
                    'hires_planned_30d' => rand(1, 5),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // ------------------------------
                // Attach random job benefits
                // ------------------------------
                $benefitIds = DB::table('job_benefits')->pluck('id')->all();
                shuffle($benefitIds);
                foreach (array_slice($benefitIds, 0, rand(2, 5)) as $benefitId) {
                    DB::table('job_benefit_job')->insertOrIgnore([
                        'job_id' => $job->id,
                        'job_benefit_id' => $benefitId,
                    ]);
                }

                // ------------------------------
                // Screening questions
                // ------------------------------
                foreach (range(1, rand(2, 4)) as $q) {
                    DB::table('job_screening_questions')->insert([
                        'job_id' => $job->id,
                        'question' => fake()->sentence(rand(6, 10)),
                        'type' => fake()->randomElement(['text','boolean','number']),
                        'is_required' => fake()->boolean(70),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // ------------------------------
                // Attach category
                // ------------------------------
                $categoryId = DB::table('categories')->inRandomOrder()->value('id');
                DB::table('jobs')->where('id', $job->id)->update(['category_id' => $categoryId]);

                // ------------------------------
                // Attach skills
                // ------------------------------
                $skillIds = DB::table('skills')->pluck('id')->all();
                shuffle($skillIds);
                foreach (array_slice($skillIds, 0, rand(2, 6)) as $sid) {
                    DB::table('job_skill')->insertOrIgnore([
                        'job_id' => $job->id,
                        'skill_id' => $sid,
                    ]);
                }

                // ------------------------------
                // Job Descriptions (NEW TABLE)
                // ------------------------------
                $overview = fake()->paragraph();
                $requirements = fake()->sentences(rand(3, 5));
                $responsibilities = fake()->sentences(rand(3, 5));

                JobDescription::insert([
                    [
                        'job_id' => $job->id,
                        'type' => 'overview',
                        'content' => $overview,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    ...collect($requirements)->map(fn($req) => [
                        'job_id' => $job->id,
                        'type' => 'requirement',
                        'content' => $req,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])->toArray(),
                    ...collect($responsibilities)->map(fn($resp) => [
                        'job_id' => $job->id,
                        'type' => 'responsibility',
                        'content' => $resp,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])->toArray(),
                ]);
            }
        }
    }
}
