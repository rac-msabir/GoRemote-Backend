<?php

namespace App\Http\Controllers;

use App\Mail\JobApplicationReceived;
use App\Models\Job;
use App\Models\User;
use App\Models\JobApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class JobApplicationController extends Controller
{
    public function apply(Request $request, Job $job)
    {
        // Only accept applications for published jobs
        if ($job->status !== 'published') {
            return response()->api(null, true, 'This job is not currently accepting applications.', 404);
        }

        // Optional auth: prevent duplicates from the same seeker
        $user = Auth::user();
        $jobSeekerId = $user && $user->role === 'seeker' ? $user->id : null;

        if ($jobSeekerId) {
            $existing = JobApplication::where('job_id', $job->id)
                ->where('job_seeker_id', $jobSeekerId)
                ->first();

            if ($existing) {
                return response()->api(null, true, 'You have already applied for this job.', 422);
            }
        }

        // Find employer's primary contact (simple joins, prefer 'owner', then 'admin', else any)
        $employerContact = DB::table('employer_users')
            ->join('users', 'employer_users.user_id', '=', 'users.id')
            ->where('employer_users.employer_id', $job->employer_id)
            ->orderByRaw("CASE WHEN employer_users.role = 'owner' THEN 0 WHEN employer_users.role = 'admin' THEN 1 ELSE 2 END")
            ->orderBy('users.id')
            ->select('users.id as user_id', 'users.email', 'users.name', 'employer_users.role')
            ->first();
            

        // Validate main fields
        $validated = $request->validate([
            'name'          => 'required|string|max:191',
            'email'         => 'required|email|max:191',
            'phone'         => 'required|string|max:50',
            'country'       => 'required|string|max:100',
            'province'      => 'required|string|max:100',
            'city'          => 'required|string|max:100',
            'zip'           => 'required|string|max:20',
            'address'       => 'required|string',
            'linkedin_url'  => 'nullable|url|max:500',
            'cover_letter'  => 'nullable|string|max:10000',
            'resume'        => 'nullable|file|mimes:pdf,doc,docx|max:10240', // 10MB

            'experiences'                       => 'nullable|array',
            'experiences.*.company_name'        => 'required_with:experiences|string|max:191',
            'experiences.*.is_current'          => 'required_with:experiences|boolean',
            'experiences.*.start_date'          => 'required_with:experiences|date|before_or_equal:today',
            'experiences.*.end_date'            => 'nullable|date',
            'experiences.*.description'         => 'nullable|string|max:2000',

            'educations'                        => 'nullable|array',
            'educations.*.degree_title'         => 'required_with:educations|string|max:191',
            'educations.*.institution'          => 'required_with:educations|string|max:191',
            'educations.*.is_current'           => 'required_with:educations|boolean',
            'educations.*.start_date'           => 'required_with:educations|date|before_or_equal:today',
            'educations.*.end_date'             => 'nullable|date',
        ]);

        // Manual date ordering checks (optional safety)
        if (!empty($validated['experiences'])) {
            foreach ($validated['experiences'] as $i => $exp) {
                if (!empty($exp['end_date']) && !empty($exp['start_date']) &&
                    strtotime($exp['end_date']) < strtotime($exp['start_date'])) {
                    return response()->api(null, true, 'Experience #'.($i+1).': end_date must be after or equal to start_date.', 422);
                }
            }
        }
        if (!empty($validated['educations'])) {
            foreach ($validated['educations'] as $i => $edu) {
                if (!empty($edu['end_date']) && !empty($edu['start_date']) &&
                    strtotime($edu['end_date']) < strtotime($edu['start_date'])) {
                    return response()->api(null, true, 'Education #'.($i+1).': end_date must be after or equal to start_date.', 422);
                }
            }
        }

        $resumePath = null;

        try {
            DB::beginTransaction();

            // Store resume to "public" disk so Storage::url() works
            if ($request->hasFile('resume')) {
                $file      = $request->file('resume');
                $filename  = Str::uuid().'.'.$file->getClientOriginalExtension();
                $year      = now()->format('Y');
                $month     = now()->format('m');

                $file->storeAs("resumes/{$year}/{$month}", $filename, 'public');
                $resumePath = "resumes/{$year}/{$month}/{$filename}";
            }

            // Create application
            $application = JobApplication::create([
                'job_id'        => $job->id,
                'job_seeker_id' => $jobSeekerId,
                'name'          => $validated['name'],
                'email'         => $validated['email'],
                'phone'         => $validated['phone'],
                'country'       => $validated['country'],
                'province'      => $validated['province'],
                'city'          => $validated['city'],
                'zip'           => $validated['zip'],
                'address'       => $validated['address'],
                'linkedin_url'  => $validated['linkedin_url'] ?? null,
                'cover_letter'  => $validated['cover_letter'] ?? null,
                'resume_path'   => $resumePath,
            ]);

            // Experiences
            if (!empty($validated['experiences'])) {
                foreach ($validated['experiences'] as $exp) {
                    $application->experiences()->create([
                        'company_name' => $exp['company_name'],
                        'is_current'   => (bool) $exp['is_current'],
                        'start_date'   => $exp['start_date'],
                        'end_date'     => !empty($exp['is_current']) ? null : ($exp['end_date'] ?? null),
                        'description'  => $exp['description'] ?? null,
                    ]);
                }
            }

            // Educations
            if (!empty($validated['educations'])) {
                foreach ($validated['educations'] as $edu) {
                    $application->educations()->create([
                        'degree_title' => $edu['degree_title'],
                        'institution'  => $edu['institution'],
                        'is_current'   => (bool) $edu['is_current'],
                        'start_date'   => $edu['start_date'],
                        'end_date'     => !empty($edu['is_current']) ? null : ($edu['end_date'] ?? null),
                    ]);
                }
            }

            DB::commit();

            // Send email to employer contact if we have an email
            // if ($employerContact && !empty($employerContact->email)) {
                try {
                    Mail::to('bilalhsn226@gmail.com')
                        ->send(new JobApplicationReceived($job, $application));
                } catch (\Throwable $mailEx) {
                    dd($mailEx>getMessage());
                    // \Log::warning('Failed to send employer application email', [
                    //     'job_id'     => $job->uuid ?? $job->id,
                    //     'to'         => $employerContact->email,
                    //     'error'      => $mailEx->getMessage(),
                    // ]);
                    // Don’t fail the API if email can’t be sent.
                }
            // }

            // Response in your standard shape
            return response()->api([
                'id'               => $application->uuid ?? $application->id,
                'job_id'           => $job->uuid ?? $job->id,
                'job_seeker_id'    => $jobSeekerId,
                'employer_id'      => $job->employer_id,
                'employer_user_id' => $employerContact->user_id ?? null,
                'employer_email'   => $employerContact->email ?? null,
                'resume_url'       => $resumePath ? Storage::url($resumePath) : null,
            ], false, null, 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            if ($resumePath && Storage::disk('public')->exists($resumePath)) {
                Storage::disk('public')->delete($resumePath);
            }

            // \Log::error('Job application failed', [
            //     'job_id' => $job->uuid ?? $job->id,
            //     'error'  => $e->getMessage(),
            // ]);
  dd($e>getMessage());
            return response()->api(null, true, 'Failed to submit application. Please try again.', 500);
        }
    }
}
