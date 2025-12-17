<?php

namespace App\Http\Controllers\Seeker;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\JobSeeker;
use App\Models\User;
use App\Models\UserProfile;     
use App\Models\UserProject;
use DB;
use Illuminate\Support\Str;
use App\Models\UserExperience;
use App\Models\UserEducation;
use App\Models\SeekerDesiredTitle;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function findSeeker(Request $request)
    {
        try {
            $perPage = max(1, (int) $request->query('per_page', 100));

            $seekers = User::query()
                ->where('role', 'seeker')
                ->select(['id', 'name', 'email']) // keep it light
                ->with([
                    'profile',
                    // Experiences: current ones first, then by start_date desc
                    'experiences' => function ($q) {
                        $q->orderByDesc('is_current')
                        ->orderByDesc('start_date');
                    },

                    // Educations: current ones first, then by start_date desc
                    'educations' => function ($q) {
                        $q->orderByDesc('is_current')
                        ->orderByDesc('start_date');
                    },

                    // Desired titles on JobSeeker (already ordered by priority)
                    'jobSeeker.desiredTitles' => function ($q) {
                        $q->orderBy('priority', 'asc');
                    },
                ])
                ->paginate($perPage);

            // If absolutely no seekers in DB
            if ($seekers->total() === 0) {
                return response()->api([
                    'seekers'    => [],
                    'pagination' => [
                        'current_page' => 1,
                        'last_page'    => 1,
                        'per_page'     => $perPage,
                        'total'        => 0,
                    ],
                ], false, null, 200);
            }

            // Transform seekers for frontend
            $seekersTransformed = $seekers->getCollection()->map(function (User $user) {
                return [
                    'id'    => $user->id,
                    'name'  => $user->name ?? null,
                    'email' => $user->email ?? null,

                    'profile' => [
                        'phone'   => optional($user->profile)->phone,
                        'city'    => optional($user->profile)->city,
                        'country' => optional($user->profile)->country,
                        'dob'     => optional($user->profile)->dob,
                        'gender'  => optional($user->profile)->gender,
                    ],

                    // Desired titles from JobSeeker relation
                    'desired_titles' => optional(optional($user->jobSeeker)->desiredTitles)
                        ->map(function ($title) {
                            return [
                                'id'       => $title->id,
                                'title'    => $title->title ?? null,
                                'priority' => $title->priority ?? null,
                            ];
                        })
                        ->values()
                        ->all() ?? [],

                    // Experiences array (from user_experiences table)
                    'experiences' => $user->experiences
                        ->map(function ($exp) {
                            return [
                                'id'          => $exp->id,
                                'company_name'=> $exp->company_name ?? null,
                                'job_title'   => $exp->job_title ?? null,
                                'is_current'  => (bool) $exp->is_current,
                                'start_date'  => $exp->start_date ? (string) $exp->start_date : null,
                                'end_date'    => $exp->end_date ? (string) $exp->end_date : null,
                                'description' => $exp->description ?? null,
                                'location'    => $exp->location ?? null,
                            ];
                        })
                        ->values()
                        ->all(),

                    // Educations array (from user_educations table)
                    'educations' => $user->educations
                        ->map(function ($edu) {
                            return [
                                'id'          => $edu->id,
                                'degree_title'=> $edu->degree_title ?? null,
                                'institution' => $edu->institution ?? null,
                                'is_current'  => (bool) $edu->is_current,
                                'start_date'  => $edu->start_date ? (string) $edu->start_date : null,
                                'end_date'    => $edu->end_date ? (string) $edu->end_date : null,
                                'description' => $edu->description ?? null,
                                'gpa'         => $edu->gpa ?? null,
                            ];
                        })
                        ->values()
                        ->all(),
                ];
            })->values();

            $data = [
                'seekers' => $seekersTransformed,
                'pagination' => [
                    'current_page' => $seekers->currentPage(),
                    'last_page'    => $seekers->lastPage(),
                    'per_page'     => $seekers->perPage(),
                    'total'        => $seekers->total(),
                ],
            ];

            return response()->api($data, false, null, 200);
        } catch (\Throwable $e) {
            return response()->api(null, true, $e->getMessage(), 500);
        }
    }

    public function profileView()
    {
        // Get the authenticated user's ID via Sanctum
        $userId = Auth::guard('sanctum')->id();

        if (!$userId) {
            return response()->api(null, false, 'Unauthenticated', 401);
        }

        // Load user with related data
        $user = User::with(['profile', 'experiences', 'educations', 'projects'])
            ->find($userId);

        if (!$user) {
            return response()->api(null, false, 'User not found', 404);
        }

        return response()->api($user, true, 'Profile fetched successfully', 200);
    }

    public function profileUpdate(Request $request)
    {
        $userId = Auth::guard('sanctum')->id();
        if (!$userId) {
            return response()->api(null, false, 'Unauthenticated', 401);
        }

        /** @var \App\Models\User $user */
        $user = User::findOrFail($userId);

        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],

            'country'  => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'city'     => ['nullable', 'string', 'max:255'],
            'zip'      => ['nullable', 'string', 'max:50'],
            'address'  => ['nullable', 'string'],

            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'website'      => ['nullable', 'url', 'max:255'], // ✅ website

            // ✅ NEW
            'github_profile' => ['nullable', 'url', 'max:255'],
            'x_url'          => ['nullable', 'url', 'max:255'],

            'cover_letter' => ['nullable', 'string'],
            'resume'       => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],

            // ✅ availability + years_of_experience
            'availability'        => ['nullable', 'string', 'max:50'],
            'years_of_experience' => ['nullable', 'integer', 'min:0', 'max:60'],

            // ✅ Skills (array OR comma string)
            'skills'   => ['nullable'],
            'skills.*' => ['nullable', 'string', 'max:50'],

            'experiences'                => ['array'],
            'experiences.*.company_name' => ['nullable', 'string', 'max:255'],
            'experiences.*.job_title'    => ['nullable', 'string', 'max:255'],
            'experiences.*.is_current'   => ['nullable'],
            'experiences.*.start_date'   => ['nullable', 'date'],
            'experiences.*.end_date'     => ['nullable', 'date'],
            'experiences.*.description'  => ['nullable', 'string'],
            'experiences.*.location'     => ['nullable', 'string', 'max:255'],

            'educations'                => ['array'],
            'educations.*.degree_title' => ['nullable', 'string', 'max:255'],
            'educations.*.institution'  => ['nullable', 'string', 'max:255'],
            'educations.*.is_current'   => ['nullable'],
            'educations.*.start_date'   => ['nullable', 'date'],
            'educations.*.end_date'     => ['nullable', 'date'],
            'educations.*.description'  => ['nullable', 'string'],
            'educations.*.gpa'          => ['nullable', 'string', 'max:50'],

            'projects'               => ['array'],
            'projects.*.title'       => ['nullable', 'string', 'max:255'],
            'projects.*.description' => ['nullable', 'string'],
            'projects.*.live_url'    => ['nullable', 'url', 'max:255'],
            'projects.*.github_url'  => ['nullable', 'url', 'max:255'],
        ]);

        DB::beginTransaction();

        try {
            // 1) Update user
            $user->name  = $validated['name'];
            $user->email = $validated['email'];
            $user->save();

            // ✅ Normalize skills
            $skillsInput = $request->input('skills');
            $skills = null;

            if (is_array($skillsInput)) {
                $skills = collect($skillsInput)
                    ->map(fn ($s) => is_string($s) ? trim($s) : '')
                    ->filter(fn ($s) => $s !== '')
                    ->unique()
                    ->values()
                    ->all();
            } elseif (is_string($skillsInput)) {
                $skills = collect(explode(',', $skillsInput))
                    ->map(fn ($s) => trim($s))
                    ->filter(fn ($s) => $s !== '')
                    ->unique()
                    ->values()
                    ->all();
            }

            if (is_array($skills) && count($skills) === 0) {
                $skills = null;
            }

            // 2) Profile data
            $profileData = [
                'phone'        => $validated['phone'] ?? null,
                'country'      => $validated['country'] ?? null,
                'province'     => $validated['province'] ?? null,
                'city'         => $validated['city'] ?? null,
                'zip'          => $validated['zip'] ?? null,
                'address'      => $validated['address'] ?? null,

                'linkedin_url' => $validated['linkedin_url'] ?? null,
                'website'      => $validated['website'] ?? null,

                // ✅ HERE (added properly)
                'github_profile' => $validated['github_profile'] ?? null,
                'x_url'          => $validated['x_url'] ?? null,

                'cover_letter' => $validated['cover_letter'] ?? null,

                'availability'        => $validated['availability'] ?? null,
                'years_of_experience' => $validated['years_of_experience'] ?? null,

                'skills' => $skills,
            ];

            // Resume upload
            if ($request->hasFile('resume')) {
                $file     = $request->file('resume');
                $fileName = Str::uuid()->toString() . '.' . $file->getClientOriginalExtension();

                $year  = now()->format('Y');
                $month = now()->format('m');

                $filePath = $file->storeAs("resumes/{$year}/{$month}", $fileName, 'public');
                $profileData['resume_path'] = $filePath;
            }

            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                $profileData
            );

            // 3) Experiences
            UserExperience::where('user_id', $user->id)->delete();

            foreach ($request->input('experiences', []) as $experience) {
                if (
                    empty($experience['company_name']) &&
                    empty($experience['job_title']) &&
                    empty($experience['description'])
                ) {
                    continue;
                }

                UserExperience::create([
                    'user_id'      => $user->id,
                    'company_name' => $experience['company_name'] ?? null,
                    'job_title'    => $experience['job_title'] ?? null,
                    'is_current'   => isset($experience['is_current']) ? (int) $experience['is_current'] : 0,
                    'start_date'   => $experience['start_date'] ?? null,
                    'end_date'     => !empty($experience['is_current']) ? null : ($experience['end_date'] ?? null),
                    'description'  => $experience['description'] ?? null,
                    'location'     => $experience['location'] ?? null,
                ]);
            }

            // 4) Educations
            UserEducation::where('user_id', $user->id)->delete();

            foreach ($request->input('educations', []) as $education) {
                if (empty($education['degree_title']) && empty($education['institution'])) {
                    continue;
                }

                UserEducation::create([
                    'user_id'      => $user->id,
                    'degree_title' => $education['degree_title'] ?? null,
                    'institution'  => $education['institution'] ?? null,
                    'is_current'   => isset($education['is_current']) ? (int) $education['is_current'] : 0,
                    'start_date'   => $education['start_date'] ?? null,
                    'end_date'     => !empty($education['is_current']) ? null : ($education['end_date'] ?? null),
                    'description'  => $education['description'] ?? null,
                    'gpa'          => $education['gpa'] ?? null,
                ]);
            }

            // 5) Projects
            UserProject::where('user_id', $user->id)->delete();

            foreach ($request->input('projects', []) as $project) {
                if (
                    empty($project['title']) &&
                    empty($project['description']) &&
                    empty($project['live_url']) &&
                    empty($project['github_url'])
                ) {
                    continue;
                }

                UserProject::create([
                    'user_id'     => $user->id,
                    'title'       => $project['title'] ?? null,
                    'description' => $project['description'] ?? null,
                    'live_url'    => $project['live_url'] ?? null,
                    'github_url'  => $project['github_url'] ?? null,
                ]);
            }

            DB::commit();

            $user->load(['profile', 'experiences', 'educations', 'projects']);

            return response()->api($user, true, 'Profile updated successfully', 200);
        } catch (\Throwable $exception) {
            DB::rollBack();
            report($exception);

            return response()->api(null, false, 'Failed to update profile', 500);
        }
    }



    public function profileCompletionView(\App\Models\User $user): array
    {
        $profile = $user->profile;

        $checks = [
            'name'  => !empty($user->name),
            'email' => !empty($user->email),

            'phone'               => !empty($profile?->phone),
            'country'             => !empty($profile?->country),
            'province'            => !empty($profile?->province),
            'city'                => !empty($profile?->city),
            'zip'                 => !empty($profile?->zip),
            'address'             => !empty($profile?->address),
            'linkedin_url'        => !empty($profile?->linkedin_url),
            'cover_letter'        => !empty($profile?->cover_letter),
            'resume_path'         => !empty($profile?->resume_path),

            'skills' => !empty($profile?->skills) && (
                (is_array($profile->skills) && count($profile->skills) > 0) ||
                (is_string($profile->skills) && trim($profile->skills) !== '')
            ),

            'years_of_experience' => !is_null($profile?->years_of_experience),

            'has_experience' => $user->experiences?->count() > 0,
            'has_education'  => $user->educations?->count() > 0,
            'has_projects'   => $user->projects?->count() > 0,
        ];

        $total = count($checks);
        $done  = collect($checks)->filter(fn ($v) => $v)->count();

        $percentage = $total > 0 ? (int) round(($done / $total) * 100) : 0;

        return [
            'percentage' => $percentage,
            'done'       => $done,
            'total'      => $total,
            'breakdown'  => $checks,
        ];
    }

}
