<?php

namespace App\Http\Controllers;

use App\Mail\JobApplicationReceived;
use App\Models\Job;
use App\Models\User;
use App\Models\JobApplication;
use App\Models\Application;
use App\Models\JobSeeker;
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
             if ($employerContact && !empty($employerContact->email)) {
                try {
                    Mail::to('bilalhsn226@gmail.com')
                        ->send(new JobApplicationReceived($job, $application));
                } catch (\Throwable $mailEx) {
                    //dd($mailEx>getMessage());
                    \Log::warning('Failed to send employer application email', [
                        'job_id'     => $job->uuid ?? $job->id,
                        'to'         => $employerContact->email,
                        'error'      => $mailEx->getMessage(),
                    ]);
                    // Don’t fail the API if email can’t be sent.
                }
             }

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

            \Log::error('Job application failed', [
                'job_id' => $job->uuid ?? $job->id,
                'error'  => $e->getMessage(),
            ]);
            return response()->api(null, true, 'Failed to submit application. Please try again.', 500);
        }
    }

    
    public function getApplications(Request $request)
    {
        $perPage = max(1, (int) $request->query('per_page', 20));

        // 1) Auth user
        $userId = Auth::guard('sanctum')->id();
        if (!$userId) {
            return response()->api(null, true, 'Unauthorized', 401);
        }

        // 2) Resolve job seeker (like getSavedJobs)
        $seekerId = \App\Models\JobSeeker::where('user_id', $userId)->value('id');
        if (!$seekerId) {
            return response()->api([
                'applications' => [],
                'pagination'   => [
                    'current_page' => 1,
                    'per_page'     => $perPage,
                    'total_pages'  => 0,
                    'total_items'  => 0,
                ],
            ], false, null, 200);
        }

        // 3) Load applications + related job + job.descriptions
        $paginator = JobApplication::with([
                'job.descriptions',
            ])
            ->where('job_seeker_id', $seekerId)
            ->latest()
            ->paginate($perPage);

        // 4) Shape response items
        $applications = collect($paginator->items())
            ->map(function (JobApplication $application) {
                $job = $application->job;

                // application might reference a deleted/unpublished job
                if (!$job) {
                    return null;
                }

                $postedAt  = $job->posted_at ?: $job->created_at;
                $closedAt  = $job->closed_at;

                $postedCarbon = $postedAt ? \Carbon\Carbon::parse($postedAt) : null;
                $closedCarbon = $closedAt ? \Carbon\Carbon::parse($closedAt) : null;

                // flags
                $isNew = $postedCarbon
                    ? $postedCarbon->greaterThanOrEqualTo(now()->subDays(7))
                    : false;

                $isFeat = ($job->pay_max && $job->pay_max >= 150000)
                    || ($isNew && $job->job_type === 'full_time');

                $tags = [];
                if ($isFeat) {
                    $tags[] = 'Featured';
                }
                if ($job->job_type) {
                    $tags[] = self::humanizeJobType($job->job_type);
                }
                if ($job->location_type === 'remote') {
                    $tags[] = 'Remote';
                }

                // salary range (same style as getSavedJobs)
                $salaryRange = null;
                if ($job->pay_min || $job->pay_max) {
                    $fmt = fn ($v) => is_null($v)
                        ? null
                        : ('$' . number_format((float) $v / 1000, 0) . 'k');

                    $min = $fmt($job->pay_min);
                    $max = $fmt($job->pay_max);

                    $salaryRange = $min && $max ? "$min - $max" : ($min ?: $max);
                }

                // basic company info (using job columns)
                $company = [
                    'name'     => $job->company_name ?? 'Unknown Company',
                    'location' => $job->location_type === 'remote'
                        ? 'Remote'
                        : (trim(implode(', ', array_filter([
                            $job->city,
                            $job->state_province,
                            $job->country_code,
                        ]))) ?: $job->country_code),
                    'website'  => $job->employer_website ?? null,
                ];

                // ✅ grouped descriptions (overview, requirements, responsibilities, etc.)
                $descriptions = collect($job->descriptions ?? [])
                    ->groupBy('type')
                    ->map(fn($items) => $items->pluck('content')->values()->all())
                    ->all();

                return [
                    // application-level info
                    'id'         => $application->id,
                    'status'     => $application->status ?? null,
                    'applied_at' => optional($application->created_at)->toISOString(),

                    // job-level info
                    'job' => [
                        'id'               => $job->uuid,
                        'title'            => $job->title,
                        'company'          => $company,
                        'vacancies'        => $job->vacancies,
                        'location_type'    => $job->location_type,
                        'job_type'         => self::humanizeJobType($job->job_type),
                        'salary_range'     => $salaryRange,
                        'tags'             => $tags,
                        'is_featured'      => (bool) $isFeat,
                        'is_new'           => (bool) $isNew,
                        'posted_at'        => $postedCarbon?->toISOString(),
                        'closed_at'        => $closedCarbon?->toISOString(),
                        'description'      => (string) $job->description,
                        'descriptions'     => $descriptions,  // 👈 same structure as getSavedJobs
                        'application_link' => $company['website'] ?: null,
                    ],
                ];
            })
            ->filter()  // drop nulls where job was missing
            ->values()
            ->all();

        // 5) Final API response
        return response()->api([
            'applications' => $applications,
            'pagination'   => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total_pages'  => $paginator->lastPage(),
                'total_items'  => $paginator->total(),
            ],
        ], false, null, 200);
    }


    private function generateOverviewFromDescription(?string $description): string
    {
        if (!$description) { return ''; }
        // Take first 2-3 sentences as overview
        $sentences = preg_split('/\.(\s+|$)/', $description, -1, PREG_SPLIT_NO_EMPTY);
        $overviewSentences = array_slice($sentences, 0, 3);
        return implode('. ', $overviewSentences) . '.';
    }

    private function generateRequirementsFromDescription(?string $description): array
    {
        if (!$description) { return []; }
        // Heuristic: split into bullet-like sentences
        $sentences = preg_split('/\.(\s+|$)/', $description, -1, PREG_SPLIT_NO_EMPTY);
        return array_map(fn ($s) => trim($s), array_slice($sentences, 0, 5));
    }

    private function generateResponsibilitiesFromDescription(?string $description): array
    {
        if (!$description) { return []; }
        $sentences = preg_split('/\.(\s+|$)/', $description, -1, PREG_SPLIT_NO_EMPTY);
        return array_map(fn ($s) => trim($s), array_slice($sentences, 5, 5));
    }

    private static function humanizeJobType(?string $jobType): string
    {
        if (!$jobType) { return ''; }
        return match ($jobType) {
            'full_time' => 'Full-Time',
            'part_time' => 'Part-Time',
            'temporary' => 'Temporary',
            'contract' => 'Contract',
            'internship' => 'Internship',
            'fresher' => 'Fresher',
            default => ucfirst(str_replace('_',' ', $jobType)),
        };
    }


}
