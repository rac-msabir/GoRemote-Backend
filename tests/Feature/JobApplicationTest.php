<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\Employer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class JobApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_apply_for_job()
    {
        Storage::fake('public');

        // Create a job
        $employer = Employer::factory()->create();
        $job = Job::factory()->create([
            'employer_id' => $employer->id,
            'status' => 'published'
        ]);

        $applicationData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+1-555-0100',
            'country' => 'Pakistan',
            'province' => 'Punjab',
            'city' => 'Lahore',
            'zip' => '54000',
            'address' => '123 Gulberg III',
            'linkedin_url' => 'https://www.linkedin.com/in/john',
            'cover_letter' => 'I am excited to apply for this position...',
            'experiences' => [
                [
                    'company_name' => 'ABC Ltd',
                    'is_current' => true,
                    'start_date' => '2021-05-01',
                    'end_date' => null,
                    'description' => 'Backend developer working on Laravel applications'
                ]
            ],
            'educations' => [
                [
                    'degree_title' => 'BS Computer Science',
                    'institution' => 'UET',
                    'is_current' => false,
                    'start_date' => '2015-09-01',
                    'end_date' => '2019-06-30'
                ]
            ]
        ];

        $response = $this->postJson("/api/jobs/{$job->id}/apply", $applicationData);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'success' => true,
                'message' => 'Job application submitted successfully.'
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'job_id',
                    'job_seeker_id',
                    'resume_url'
                ]
            ]);

        $this->assertDatabaseHas('job_applications', [
            'job_id' => $job->id,
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);

        $this->assertDatabaseHas('job_application_experiences', [
            'company_name' => 'ABC Ltd',
            'is_current' => true
        ]);

        $this->assertDatabaseHas('job_application_educations', [
            'degree_title' => 'BS Computer Science',
            'institution' => 'UET'
        ]);
    }

    public function test_authenticated_user_can_apply_for_job()
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => 'seeker']);
        $employer = Employer::factory()->create();
        $job = Job::factory()->create([
            'employer_id' => $employer->id,
            'status' => 'published'
        ]);

        $applicationData = [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+1-555-0101',
            'country' => 'Pakistan',
            'province' => 'Sindh',
            'city' => 'Karachi',
            'zip' => '75000',
            'address' => '456 Clifton',
            'experiences' => [],
            'educations' => []
        ];

        $response = $this->actingAs($user)
            ->postJson("/api/jobs/{$job->id}/apply", $applicationData);

        $response->assertStatus(201);

        $this->assertDatabaseHas('job_applications', [
            'job_id' => $job->id,
            'job_seeker_id' => $user->id,
            'name' => 'Jane Doe'
        ]);
    }

    public function test_cannot_apply_to_closed_job()
    {
        $employer = Employer::factory()->create();
        $job = Job::factory()->create([
            'employer_id' => $employer->id,
            'status' => 'closed'
        ]);

        $applicationData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+1-555-0100',
            'country' => 'Pakistan',
            'province' => 'Punjab',
            'city' => 'Lahore',
            'zip' => '54000',
            'address' => '123 Gulberg III',
            'experiences' => [],
            'educations' => []
        ];

        $response = $this->postJson("/api/jobs/{$job->id}/apply", $applicationData);

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
                'success' => false,
                'message' => 'This job is not currently accepting applications.'
            ]);
    }

    public function test_validation_errors_are_returned()
    {
        $employer = Employer::factory()->create();
        $job = Job::factory()->create([
            'employer_id' => $employer->id,
            'status' => 'published'
        ]);

        $response = $this->postJson("/api/jobs/{$job->id}/apply", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'name', 'email', 'phone', 'country', 'province', 'city', 'zip', 'address'
            ]);
    }
}

