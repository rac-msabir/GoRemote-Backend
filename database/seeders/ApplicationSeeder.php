<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ApplicationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Messages for applications and general notifications
        $applicationIds = DB::table('applications')->pluck('id')->all();
        $userIds = DB::table('users')->pluck('id')->all();

        foreach ($applicationIds as $appId) {
            $msgCount = rand(0, 3);
            for ($m = 0; $m < $msgCount; $m++) {
                DB::table('messages')->insert([
                    'application_id' => $appId,
                    'sender_type' => fake()->randomElement(['employer','seeker']),
                    'sender_user_id' => fake()->randomElement($userIds),
                    'body' => fake()->paragraph(),
                    'sent_at' => now()->subDays(rand(0, 15)),
                    'read_at' => fake()->boolean(50) ? now()->subDays(rand(0, 10)) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // User notifications
        $types = ['application_status','new_message','job_posted'];
        foreach ($userIds as $uid) {
            for ($n = 0; $n < rand(1, 4); $n++) {
                DB::table('notifications')->insert([
                    'user_id' => $uid,
                    'type' => fake()->randomElement($types),
                    'payload' => json_encode(['text' => fake()->sentence()]),
                    'read_at' => fake()->boolean(40) ? now()->subDays(rand(0, 7)) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
