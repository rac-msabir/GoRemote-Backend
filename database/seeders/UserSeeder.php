<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ADMIN USER
        User::updateOrCreate(
            ['email' => 'admin@goremote.com'],
            [
                'uuid'              => Str::uuid(),
                'name'              => 'Admin User',
                'email_verified_at' => now(),
                'password'          => Hash::make('password'),
                'provider'          => 'local',
                'provider_id'       => null,
                'role'              => 'admin',
                'remember_token'    => Str::random(10),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]
        );

        // EDITOR USER
        User::updateOrCreate(
            ['email' => 'editor@goremote.com'],
            [
                'uuid'              => Str::uuid(),
                'name'              => 'Editor User',
                'email_verified_at' => now(),
                'password'          => Hash::make('password'),
                'provider'          => 'local',
                'provider_id'       => null,
                'role'              => 'editor',
                'remember_token'    => Str::random(10),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]
        );
    }
}
