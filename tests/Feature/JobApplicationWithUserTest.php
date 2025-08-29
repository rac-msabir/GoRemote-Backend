<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\Employer;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserEducation;
use App\Models\UserExperience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class JobApplicationWithUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_auto_fills_application_data()
    {
        Storage::fake('public');

        // Create a user with profile data
        $user = User::factory()->create(['role' => 'seeker']);
        $profile = UserProfile::factory()->create([
            'user_id' => $user->id,
            'phone' => '+92-300-1234567',
            'country' => 'Pakistan',
            'province' => 'Punjab',
            'city' => 'Lahore',
            'zip' => '54000',
            'address' => '123 Gulberg III',
            'linkedin_url' => 'https://www.linkedin.com/in/johndoe',
        ]);

        // Create user education
        UserEducation::factory()->create([
            'user_id' => $user->id,
            'degree_title' => 'BS Computer Science',
            'institution' => 'UET',
            'is_current' => false,
            'start_date' => '2015-09-01',
            'end_date' => '2019-06-30',
        ]);

        // Create user experience
        UserExperience::factory()->create([
            'user_id' => $user->id,
            'company_name' => 'ABC Ltd',
            'job_title' => 'Backend Developer',
            'is_current' => true,
            'start_date' => '2021-05-01',
            'end_date' => null,
            'description' => 'Laravel developer',
        ]);

        // Create a job
        $employer = Employer::factory()->create();
        $job = Job::factory()->create([
            'employer_id' => $employer->id,
            'status' => 'published'
        ]);

        // Minimal application data (user profile will auto-fill the rest)
        $applicationData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'cover_letter' => 'I am excited to apply for this role...',
        ];

        $response = $this->actingAs($user)
            ->postJson("/api/jobs/{$job->id}/apply", $applicationData);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'success' => true,
                'message' => 'Job application submitted successfully.'
            ]);

        // Verify that profile data was auto-filled
        $this->assertDatabaseHas('job_applications', [
            'job_id' => $job->id,
            'job_seeker_id' => $user->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+92-300-1234567',
            'country' => 'Pakistan',
            'province' => 'Punjab',
            'city' => 'Lahore',
            'zip' => '54000',
            'address' => '123 Gulberg III',
            'linkedin_url' => 'https://www.linkedin.com/in/johndoe',
        ]);

        // Verify experiences were auto-filled
        $this->assertDatabaseHas('job_application_experiences', [
            'company_name' => 'ABC Ltd',
            'is_current' => true,
        ]);

        // Verify educations were auto-filled
        $this->assertDatabaseHas('job_application_educations', [
            'degree_title' => 'BS Computer Science',
            'institution' => 'UET',
        ]);
    }

    public function test_user_with_custom_headers_can_apply()
    {
        Storage::fake('public');

        // Create a user
        $user = User::factory()->create(['role' => 'seeker']);
        
        // Create a job
        $employer = Employer::factory()->create();
        $job = Job::factory()->create([
            'employer_id' => $employer->id,
            'status' => 'published'
        ]);

        $applicationData = [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+92-300-1234568',
            'country' => 'Pakistan',
            'province' => 'Sindh',
            'city' => 'Karachi',
            'zip' => '75000',
            'address' => '456 Clifton',
            'experiences' => [],
            'educations' => []
        ];

        $response = $this->withHeaders([
            'X-User-ID' => $user->id,
        ])->postJson("/api/jobs/{$job->id}/apply", $applicationData);

        $response->assertStatus(201);

        $this->assertDatabaseHas('job_applications', [
            'job_id' => $job->id,
            'job_seeker_id' => $user->id,
            'name' => 'Jane Doe'
        ]);
    }
}

