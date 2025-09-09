<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Models\JobApplication;
use App\Models\JobApplicationExperience;
use App\Models\JobApplicationEducation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class JobApplicationController extends Controller
{
    public function apply(Request $request, Job $job)
    {
        // Check if job is open for applications
        if ($job->status !== 'published') {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'This job is not currently accepting applications.',
                'data' => []
            ], 404);
        }

        // Get authenticated user if available
        $user = Auth::user();
        $jobSeekerId = $user && $user->role === 'seeker' ? $user->id : null;

        // Check for duplicate application if authenticated
        if ($jobSeekerId) {
            $existingApplication = JobApplication::where('job_id', $job->id)
                ->where('job_seeker_id', $jobSeekerId)
                ->first();

            if ($existingApplication) {
                return response()->json([
                    'status' => 'error',
                    'success' => false,
                    'message' => 'You have already applied for this job.',
                    'data' => []
                ], 422);
            }
        }

        // Validate request
        $validated = $request->validate([
            'name' => 'required|string|max:191',
            'email' => 'required|email|max:191',
            'phone' => 'required|string|max:50',
            'country' => 'required|string|max:100',
            'province' => 'required|string|max:100',
            'city' => 'required|string|max:100',
            'zip' => 'required|string|max:20',
            'address' => 'required|string',
            'linkedin_url' => 'nullable|url|max:500',
            'cover_letter' => 'nullable|string|max:10000',
            'resume' => 'nullable|file|mimes:pdf,doc,docx|max:10240', // 10MB max
            'experiences' => 'array',
            'experiences.*.company_name' => 'required|string|max:191',
            'experiences.*.is_current' => 'required|boolean',
            'experiences.*.start_date' => 'required|date|before_or_equal:today',
            'experiences.*.end_date' => 'nullable|date|after_or_equal:experiences.*.start_date',
            'experiences.*.description' => 'nullable|string|max:2000',
            'educations' => 'array',
            'educations.*.degree_title' => 'required|string|max:191',
            'educations.*.institution' => 'required|string|max:191',
            'educations.*.is_current' => 'required|boolean',
            'educations.*.start_date' => 'required|date|before_or_equal:today',
            'educations.*.end_date' => 'nullable|date|after_or_equal:educations.*.start_date',
        ]);

        try {
            DB::beginTransaction();

            // Handle resume file upload
            $resumePath = null;
            if ($request->hasFile('resume')) {
                $file = $request->file('resume');
                $extension = $file->getClientOriginalExtension();
                $filename = Str::uuid() . '.' . $extension;
                $year = now()->format('Y');
                $month = now()->format('m');
                $path = "resumes/{$year}/{$month}/{$filename}";
                
                $file->storeAs("public/resumes/{$year}/{$month}", $filename);
                $resumePath = $path;
            }

            // Create job application
            $application = JobApplication::create([
                'job_id' => $job->id,
                'job_seeker_id' => $jobSeekerId,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'country' => $validated['country'],
                'province' => $validated['province'],
                'city' => $validated['city'],
                'zip' => $validated['zip'],
                'address' => $validated['address'],
                'linkedin_url' => $validated['linkedin_url'] ?? null,
                'cover_letter' => $validated['cover_letter'] ?? null,
                'resume_path' => $resumePath,
            ]);

            // Create experiences
            if (!empty($validated['experiences'])) {
                foreach ($validated['experiences'] as $expData) {
                    $application->experiences()->create([
                        'company_name' => $expData['company_name'],
                        'is_current' => (bool) $expData['is_current'],
                        'start_date' => $expData['start_date'],
                        'end_date' => $expData['is_current'] ? null : ($expData['end_date'] ?? null),
                        'description' => $expData['description'] ?? null,
                    ]);
                }
            }

            // Create educations
            if (!empty($validated['educations'])) {
                foreach ($validated['educations'] as $eduData) {
                    $application->educations()->create([
                        'degree_title' => $eduData['degree_title'],
                        'institution' => $eduData['institution'],
                        'is_current' => (bool) $eduData['is_current'],
                        'start_date' => $eduData['start_date'],
                        'end_date' => $eduData['is_current'] ? null : ($eduData['end_date'] ?? null),
                    ]);
                }
            }

            DB::commit();

            // Log the application
            \Log::info('Job application submitted', [
                'job_id' => $job->uuid,
                'job_application_id' => $application->uuid,
                'job_seeker_id' => $jobSeekerId,
                'email' => $validated['email'],
            ]);

            return response()->json([
                'status' => 'success',
                'success' => true,
                'message' => 'Job application submitted successfully.',
                'data' => [
                    'id' => $application->uuid,
                    'job_id' => $job->uuid,
                    'job_seeker_id' => $jobSeekerId,
                    'resume_url' => $application->resume_url,
                ]
            ], 201);

        } catch (\Exception $e) {
            dd($e->getMessage());
            DB::rollBack();
            
            // Clean up uploaded file if it exists
            if ($resumePath && Storage::disk('public')->exists($resumePath)) {
                Storage::disk('public')->delete($resumePath);
            }

            \Log::error('Job application failed', [
                'job_id' => $job->uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'Failed to submit application. Please try again.',
                'data' => []
            ], 500);
        }
    }
}

