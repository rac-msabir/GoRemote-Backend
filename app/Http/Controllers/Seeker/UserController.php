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
use Illuminate\Support\Facades\Http;
use Smalot\PdfParser\Parser as PdfParser;
use PhpOffice\PhpWord\IOFactory;
use Illuminate\Http\UploadedFile;

class UserController extends Controller
{
    public function findSeeker(Request $request)
    {
        try {
            $perPage = max(1, (int) $request->query('per_page', 100));

            $seekers = User::query()
                ->where('role', 'seeker')
                ->select(['id','uuid', 'name', 'email','slug']) // keep it light
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
                    'uuid'  => $user->uuid,
                    'name'  => $user->name ?? null,
                    'email' => $user->email ?? null,
                    'slug' => $user->slug ?? null,
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

    public function candidateDetail(string $value)
    {
        $user = User::with(['profile', 'experiences', 'educations', 'projects'])
            ->where('uuid', $value)
            ->orWhere('slug', $value)
            ->first();

        if (!$user) {
            return response()->api(null, false, 'User not found', 404);
        }

        return response()->api($user, true, 'User fetched successfully', 200);
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

   public function profileCompletionView(Request $request)
    {
        $userId = Auth::guard('sanctum')->id();

        if (!$userId) {
            return response()->api(null, false, 'Unauthenticated', 401);
        }

        $user = User::with(['profile', 'experiences', 'educations', 'projects'])
            ->find($userId);

        if (!$user) {
            return response()->api(null, false, 'User not found', 404);
        }

        $profile = $user->profile;

        $checks = [
            'name'  => !empty($user->name),
            'email' => !empty($user->email),

            'phone'        => !empty($profile?->phone),
            'country'      => !empty($profile?->country),
            'province'     => !empty($profile?->province),
            'city'         => !empty($profile?->city),
            'zip'          => !empty($profile?->zip),
            'address'      => !empty($profile?->address),
            'linkedin_url' => !empty($profile?->linkedin_url),
            'cover_letter' => !empty($profile?->cover_letter),
            'resume_path'  => !empty($profile?->resume_path),

            'skills' => !empty($profile?->skills) && (
                (is_array($profile->skills) && count($profile->skills) > 0) ||
                (is_string($profile->skills) && trim($profile->skills) !== '')
            ),

            'years_of_experience' => !is_null($profile?->years_of_experience),

            'has_experience' => ($user->experiences?->count() ?? 0) > 0,
            'has_education'  => ($user->educations?->count() ?? 0) > 0,
            'has_projects'   => ($user->projects?->count() ?? 0) > 0,
        ];

        $total = count($checks);
        $done  = collect($checks)->filter(fn ($v) => $v)->count();

        $percentage = $total > 0 ? (int) round(($done / $total) * 100) : 0;

        $result = [
            'percentage' => $percentage,
            'done'       => $done,
            'total'      => $total,
            'breakdown'  => $checks,
        ];

        return response()->api($result, true, 'Profile completion fetched', 200);
    }


    // testing
    // public function profileUpdate(Request $request)
    // {
    //     $userId = Auth::guard('sanctum')->id();
    //     if (!$userId) {
    //         return response()->api(null, false, 'Unauthenticated', 401);
    //     }

    //     /** @var \App\Models\User $user */
    //     $user = User::findOrFail($userId);
    //     $parsedCv = null;

    //     if ($request->hasFile('resume')) {
    //         $file = $request->file('resume');

    //         // ✅ Parse CV first (so you can use name/email in validation/update)
    //         $parsedCv = $this->CvParsing($file);
    //     }
        
    //     $validated = $request->validate([
    //         'name'  => ['nullable', 'string', 'max:255'],
    //         'email' => ['nullable', 'email', 'max:255'],
    //         'phone' => ['nullable', 'string', 'max:50'],

    //         'country'  => ['nullable', 'string', 'max:255'],
    //         'province' => ['nullable', 'string', 'max:255'],
    //         'city'     => ['nullable', 'string', 'max:255'],
    //         'zip'      => ['nullable', 'string', 'max:50'],
    //         'address'  => ['nullable', 'string'],

    //         'linkedin_url' => ['nullable', 'url', 'max:255'],
    //         'website'      => ['nullable', 'url', 'max:255'],

    //         'github_profile' => ['nullable', 'url', 'max:255'],
    //         'x_url'          => ['nullable', 'url', 'max:255'],

    //         'cover_letter' => ['nullable', 'string'],
    //         'resume'       => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],

    //         'title'    => ['nullable', 'string', 'max:255'],
    //         'about_me' => ['nullable', 'string'],

    //         // ✅ profile picture (users.image)
    //         'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],

    //         'availability'        => ['nullable', 'string', 'max:50'],
    //         'years_of_experience' => ['nullable', 'integer', 'min:0', 'max:60'],

    //         'skills'   => ['nullable'],
    //         'skills.*' => ['nullable', 'string', 'max:50'],

    //         'experiences'                => ['array'],
    //         'experiences.*.company_name' => ['nullable', 'string', 'max:255'],
    //         'experiences.*.job_title'    => ['nullable', 'string', 'max:255'],
    //         'experiences.*.is_current'   => ['nullable'],
    //         'experiences.*.start_date'   => ['nullable', 'date'],
    //         'experiences.*.end_date'     => ['nullable', 'date'],
    //         'experiences.*.description'  => ['nullable', 'string'],
    //         'experiences.*.location'     => ['nullable', 'string', 'max:255'],

    //         'educations'                => ['array'],
    //         'educations.*.degree_title' => ['nullable', 'string', 'max:255'],
    //         'educations.*.institution'  => ['nullable', 'string', 'max:255'],
    //         'educations.*.is_current'   => ['nullable'],
    //         'educations.*.start_date'   => ['nullable', 'date'],
    //         'educations.*.end_date'     => ['nullable', 'date'],
    //         'educations.*.description'  => ['nullable', 'string'],
    //         'educations.*.gpa'          => ['nullable', 'string', 'max:50'],

    //         'projects'               => ['array'],
    //         'projects.*.title'       => ['nullable', 'string', 'max:255'],
    //         'projects.*.description' => ['nullable', 'string'],
    //         'projects.*.live_url'    => ['nullable', 'url', 'max:255'],
    //         'projects.*.github_url'  => ['nullable', 'url', 'max:255'],
    //     ]);

    //     DB::beginTransaction();

    //     try {

    //         // ✅ Upload profile image (users.image)
    //         if ($request->hasFile('image')) {

    //             // Delete old image if exists (from public folder)
    //             if (!empty($user->image)) {
    //                 $oldImagePath = public_path($user->image);
    //                 if (file_exists($oldImagePath)) {
    //                     unlink($oldImagePath);
    //                 }
    //             }

    //             $imageFile = $request->file('image');
    //             $imageName = Str::uuid() . '.' . $imageFile->getClientOriginalExtension();

    //             $year  = now()->format('Y');
    //             $month = now()->format('m');

    //             $destinationPath = public_path("profile_images/{$year}/{$month}");

    //             if (!file_exists($destinationPath)) {
    //                 mkdir($destinationPath, 0755, true);
    //             }

    //             $imageFile->move($destinationPath, $imageName);

    //             $user->image = "profile_images/{$year}/{$month}/{$imageName}";
    //         }

    //         // 1) Update user
    //         $nameFromCv  = is_array($parsedCv) ? ($parsedCv['name'] ?? null) : null;
    //         $emailFromCv = is_array($parsedCv) ? ($parsedCv['email'] ?? null) : null;

    //         $user->name  = $validated['name']  ?? $nameFromCv  ?? $user->name;
    //         $user->email = $validated['email'] ?? $emailFromCv ?? $user->email;
    //         $user->save();

    //         // ✅ Normalize skills (from request)
    //         $skillsInput = $request->input('skills');
    //         $skills = null;

    //         if (is_array($skillsInput)) {
    //             $skills = collect($skillsInput)
    //                 ->map(fn ($s) => is_string($s) ? trim($s) : '')
    //                 ->filter(fn ($s) => $s !== '')
    //                 ->unique()
    //                 ->values()
    //                 ->all();
    //         } elseif (is_string($skillsInput)) {
    //             $skills = collect(explode(',', $skillsInput))
    //                 ->map(fn ($s) => trim($s))
    //                 ->filter(fn ($s) => $s !== '')
    //                 ->unique()
    //                 ->values()
    //                 ->all();
    //         }

    //         if (is_array($skills) && count($skills) === 0) {
    //             $skills = null;
    //         }

    //         // 2) Profile data
    //         $profileData = [
    //             'phone'        => $validated['phone'] ?? null,
    //             'country'      => $validated['country'] ?? null,
    //             'province'     => $validated['province'] ?? null,
    //             'city'         => $validated['city'] ?? null,
    //             'zip'          => $validated['zip'] ?? null,
    //             'address'      => $validated['address'] ?? null,

    //             'linkedin_url' => $validated['linkedin_url'] ?? null,
    //             'website'      => $validated['website'] ?? null,

    //             'github_profile' => $validated['github_profile'] ?? null,
    //             'x_url'          => $validated['x_url'] ?? null,

    //             'cover_letter' => $validated['cover_letter'] ?? null,

    //             'availability'        => $validated['availability'] ?? null,
    //             'years_of_experience' => $validated['years_of_experience'] ?? null,

    //             'title'    => $validated['title'] ?? null,
    //             'about_me' => $validated['about_me'] ?? null,

    //             'skills' => $skills,
    //         ];

    //         // ✅ Resume upload + CV parsing
    //         if ($request->hasFile('resume')) {
    //             $file     = $request->file('resume');
    //             $fileName = Str::uuid()->toString() . '.' . $file->getClientOriginalExtension();

    //             $year  = now()->format('Y');
    //             $month = now()->format('m');

    //             $filePath = $file->storeAs("resumes/{$year}/{$month}", $fileName, 'public');
    //             $profileData['resume_path'] = $filePath;
    //         }

    //         // ✅ Merge parsed CV fields ONLY if current value is null/empty
    //         if (is_array($parsedCv) && !empty($parsedCv)) {

    //             if (empty($profileData['phone']) && !empty($parsedCv['phone'])) {
    //                 $profileData['phone'] = $parsedCv['phone'];
    //             }

    //             if (empty($profileData['linkedin_url']) && !empty($parsedCv['linkedin'])) {
    //                 $profileData['linkedin_url'] = $this->normalizeUrl($parsedCv['linkedin']);
    //             }

    //             if (empty($profileData['github_profile']) && !empty($parsedCv['github'])) {
    //                 $profileData['github_profile'] = $this->normalizeUrl($parsedCv['github']);
    //             }

    //             if (empty($profileData['website']) && !empty($parsedCv['website'])) {
    //                 $profileData['website'] = $this->normalizeUrl($parsedCv['website']);
    //             }

    //             // skills (only if request skills is null/empty)
    //             if (empty($skills) && !empty($parsedCv['skills']) && is_array($parsedCv['skills'])) {
    //                 $skills = collect($parsedCv['skills'])
    //                     ->map(fn ($s) => is_string($s) ? trim($s) : '')
    //                     ->filter(fn ($s) => $s !== '')
    //                     ->unique()
    //                     ->values()
    //                     ->all();

    //                 $profileData['skills'] = count($skills) ? $skills : null;
    //             }
    //         }

    //         $user->profile()->updateOrCreate(
    //             ['user_id' => $user->id],
    //             $profileData
    //         );

    //         /**
    //          * IMPORTANT:
    //          * - If request has experiences/educations/projects => use request data (delete + insert)
    //          * - Else => ONLY fill from parsed CV if user has NO existing records (don’t overwrite)
    //          */

    //         // 3) Experiences
    //         if ($request->has('experiences')) {
    //             UserExperience::where('user_id', $user->id)->delete();

    //             foreach ($request->input('experiences', []) as $experience) {
    //                 if (
    //                     empty($experience['company_name']) &&
    //                     empty($experience['job_title']) &&
    //                     empty($experience['description'])
    //                 ) {
    //                     continue;
    //                 }

    //                 UserExperience::create([
    //                     'user_id'      => $user->id,
    //                     'company_name' => $experience['company_name'] ?? null,
    //                     'job_title'    => $experience['job_title'] ?? null,
    //                     'is_current'   => isset($experience['is_current']) ? (int) $experience['is_current'] : 0,
    //                     'start_date'   => $experience['start_date'] ?? null,
    //                     'end_date'     => !empty($experience['is_current']) ? null : ($experience['end_date'] ?? null),
    //                     'description'  => $experience['description'] ?? null,
    //                     'location'     => $experience['location'] ?? null,
    //                 ]);
    //             }
    //         } else {
    //             $hasExisting = UserExperience::where('user_id', $user->id)->exists();

    //             if (
    //                 !$hasExisting &&
    //                 is_array($parsedCv) &&
    //                 !empty($parsedCv['experience']) &&
    //                 is_array($parsedCv['experience'])
    //             ) {
    //                 foreach ($parsedCv['experience'] as $exp) {
    //                     $company = $exp['company'] ?? null;
    //                     $title   = $exp['title'] ?? null;
    //                     $desc    = $exp['description'] ?? null;

    //                     if (empty($company) && empty($title) && empty($desc)) {
    //                         continue;
    //                     }

    //                     $endRaw = $exp['end_date'] ?? null;
    //                     $isCurrent = is_string($endRaw) && str_contains(strtolower($endRaw), 'present');

    //                     UserExperience::create([
    //                         'user_id'      => $user->id,
    //                         'company_name' => $company,
    //                         'job_title'    => $title,
    //                         'is_current'   => $isCurrent ? 1 : 0,
    //                         'start_date'   => $this->parseMonthYearToDate($exp['start_date'] ?? null),
    //                         'end_date'     => $isCurrent ? null : $this->parseMonthYearToDate($endRaw),
    //                         'description'  => $desc,
    //                         'location'     => null,
    //                     ]);
    //                 }
    //             }
    //         }

    //         // 4) Educations
    //         if ($request->has('educations')) {
    //             UserEducation::where('user_id', $user->id)->delete();

    //             foreach ($request->input('educations', []) as $education) {
    //                 if (empty($education['degree_title']) && empty($education['institution'])) {
    //                     continue;
    //                 }

    //                 UserEducation::create([
    //                     'user_id'      => $user->id,
    //                     'degree_title' => $education['degree_title'] ?? null,
    //                     'institution'  => $education['institution'] ?? null,
    //                     'is_current'   => isset($education['is_current']) ? (int) $education['is_current'] : 0,
    //                     'start_date'   => $education['start_date'] ?? null,
    //                     'end_date'     => !empty($education['is_current']) ? null : ($education['end_date'] ?? null),
    //                     'description'  => $education['description'] ?? null,
    //                     'gpa'          => $education['gpa'] ?? null,
    //                 ]);
    //             }
    //         } else {
    //             $hasExisting = UserEducation::where('user_id', $user->id)->exists();

    //             if (
    //                 !$hasExisting &&
    //                 is_array($parsedCv) &&
    //                 !empty($parsedCv['education']) &&
    //                 is_array($parsedCv['education'])
    //             ) {
    //                 foreach ($parsedCv['education'] as $edu) {
    //                     $degree = $edu['degree'] ?? null;
    //                     $inst   = $edu['institution'] ?? null;

    //                     if (empty($degree) && empty($inst)) {
    //                         continue;
    //                     }

    //                     $endRaw = $edu['end_date'] ?? null;
    //                     $isCurrent = is_string($endRaw) && str_contains(strtolower($endRaw), 'present');

    //                     UserEducation::create([
    //                         'user_id'      => $user->id,
    //                         'degree_title' => $degree,
    //                         'institution'  => $inst,
    //                         'is_current'   => $isCurrent ? 1 : 0,
    //                         'start_date'   => $this->parseMonthYearToDate($edu['start_date'] ?? null),
    //                         'end_date'     => $isCurrent ? null : $this->parseMonthYearToDate($endRaw),
    //                         'description'  => null,
    //                         'gpa'          => null,
    //                     ]);
    //                 }
    //             }
    //         }

    //         // 5) Projects
    //         if ($request->has('projects')) {
    //             UserProject::where('user_id', $user->id)->delete();

    //             foreach ($request->input('projects', []) as $project) {
    //                 if (
    //                     empty($project['title']) &&
    //                     empty($project['description']) &&
    //                     empty($project['live_url']) &&
    //                     empty($project['github_url'])
    //                 ) {
    //                     continue;
    //                 }

    //                 UserProject::create([
    //                     'user_id'     => $user->id,
    //                     'title'       => $project['title'] ?? null,
    //                     'description' => $project['description'] ?? null,
    //                     'live_url'    => $project['live_url'] ?? null,
    //                     'github_url'  => $project['github_url'] ?? null,
    //                 ]);
    //             }
    //         } else {
    //             $hasExisting = UserProject::where('user_id', $user->id)->exists();

    //             if (
    //                 !$hasExisting &&
    //                 is_array($parsedCv) &&
    //                 !empty($parsedCv['projects']) &&
    //                 is_array($parsedCv['projects'])
    //             ) {
    //                 foreach ($parsedCv['projects'] as $proj) {
    //                     $title = $proj['title'] ?? null;
    //                     $desc  = $proj['description'] ?? null;
    //                     $live  = $proj['live_url'] ?? null;
    //                     $git   = $proj['github_url'] ?? null;

    //                     if (empty($title) && empty($desc) && empty($live) && empty($git)) {
    //                         continue;
    //                     }

    //                     UserProject::create([
    //                         'user_id'     => $user->id,
    //                         'title'       => $title,
    //                         'description' => $desc,
    //                         'live_url'    => $this->normalizeUrl($live),
    //                         'github_url'  => $this->normalizeUrl($git),
    //                     ]);
    //                 }
    //             }
    //         }

    //         DB::commit();

    //         $user->load(['profile', 'experiences', 'educations', 'projects']);

    //         return response()->api($user, true, 'Profile updated successfully', 200);
    //     } catch (\Throwable $exception) {
    //         DB::rollBack();
    //         report($exception);
    //         dd($exception->getMessage());
    //         return response()->api(null, false, 'Failed to update profile', 500);
    //     }
    // }


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
            'is_public' => ['nullable', 'boolean'],
            'country'  => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'city'     => ['nullable', 'string', 'max:255'],
            'zip'      => ['nullable', 'string', 'max:50'],
            'address'  => ['nullable', 'string'],

            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'website'      => ['nullable', 'url', 'max:255'],

            'github_profile' => ['nullable', 'url', 'max:255'],
            'x_url'          => ['nullable', 'url', 'max:255'],

            'cover_letter' => ['nullable', 'string'],
            'resume'       => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],

            'title'    => ['nullable', 'string', 'max:255'],
            'about_me' => ['nullable', 'string'],
            // ✅ NEW: profile picture (users.image)
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'], // 5MB

            'availability'        => ['nullable', 'string', 'max:50'],
            'years_of_experience' => ['nullable', 'integer', 'min:0', 'max:60'],

            'skills'   => ['nullable'],
            'skills.*' => ['nullable', 'string', 'max:50'],

            'experiences'                => ['array'],
            'experiences.*.company_name' => ['nullable', 'string', 'max:255'],
            'experiences.*.job_title'    => ['nullable', 'string', 'max:255'],
            'experiences.*.is_current'   => ['nullable'],
            'experiences.*.start_date'   => ['nullable'],
            'experiences.*.end_date'     => ['nullable'],
            'experiences.*.description'  => ['nullable', 'string'],
            'experiences.*.location'     => ['nullable', 'string', 'max:255'],

            'educations'                => ['array'],
            'educations.*.degree_title' => ['nullable', 'string', 'max:255'],
            'educations.*.institution'  => ['nullable', 'string', 'max:255'],
            'educations.*.is_current'   => ['nullable'],
            'educations.*.start_date'   => ['nullable'],
            'educations.*.end_date'     => ['nullable'],
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
            // ✅ Upload profile image (users.image)
            
            if ($request->hasFile('image')) {

                // Delete old image if exists (from public folder)
                if (!empty($user->image)) {
                    $oldImagePath = public_path($user->image);

                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }

                $imageFile = $request->file('image');
                $imageName = Str::uuid() . '.' . $imageFile->getClientOriginalExtension();

                $year  = now()->format('Y');
                $month = now()->format('m');

                $destinationPath = public_path("profile_images/{$year}/{$month}");

                // Create directory if it doesn't exist
                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0755, true);
                }

                // Move image to public directory
                $imageFile->move($destinationPath, $imageName);

                // Save relative public path in DB
                $user->image = "profile_images/{$year}/{$month}/{$imageName}";
            }

            // 1) Update user
            $user->name  = $validated['name'];
            $user->email = $validated['email'];
            $user->is_public = isset($validated['is_public']) ? (bool) $validated['is_public'] : $user->is_public;
            // ✅ slug logic
            if ($user->is_public) {
                // if already has slug keep it, otherwise generate
                if (empty($user->slug)) {
                    $user->slug = Str::slug($validated['name']) . '-' . substr((string) Str::uuid(), 0, 8);
                }
            } else {
                // if private => remove slug
                $user->slug = null;
            }
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

                'github_profile' => $validated['github_profile'] ?? null,
                'x_url'          => $validated['x_url'] ?? null,

                'cover_letter' => $validated['cover_letter'] ?? null,

                'availability'        => $validated['availability'] ?? null,
                'years_of_experience' => $validated['years_of_experience'] ?? null,
                'title' => $validated['title'] ?? null,
                'about_me' => $validated['about_me'] ?? null,

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

    /**
     * ✅ UPDATED: accept UploadedFile and return parsed array (or null)
     */
    // public function CvParsing(Request $request)
    // {
    //     $userId = Auth::guard('sanctum')->id();
    //     if (!$userId) {
    //         return response()->api(null, false, 'Unauthenticated', 401);
    //     }

    //          $validated = $request->validate([
    //         'resume'       => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240']
    //     ]);

    //      if ($request->hasFile('resume')) {
    //         $file = $request->file('resume');
    //         $text = $this->extractText($file);

    //     }

    //     $response = Http::withHeaders([
    //         'Authorization' => 'Bearer ' . config('services.groq.key'),
    //         'Content-Type'  => 'application/json',
    //     ])->post('https://api.groq.com/openai/v1/chat/completions', [
    //         'model'       => 'llama-3.3-70b-versatile',
    //         'temperature' => 0,
    //         'messages'    => [
    //             [
    //                 'role'    => 'system',
    //                 'content' => 'You are a resume parser. Always return VALID JSON ONLY. If a field is missing, return an empty string "" or empty array []. No explanations.'
    //             ],
    //             [
    //                 'role'    => 'user',
    //                 'content' => $this->prompt($text)
    //             ]
    //         ]
    //     ]);

    //     if (!$response->successful()) {
    //         return null;
    //     }

    //     $content = $response->json('choices.0.message.content');
    //     if (!is_string($content) || $content === '') {
    //         return null;
    //     }

    //     $parsedJson = json_decode($content, true);

    //     if (json_last_error() !== JSON_ERROR_NONE || !is_array($parsedJson)) {
    //         return null;
    //     }

    //     return $parsedJson;
    // }

    // private function extractText($file)
    // {
    //     $extension = $file->getClientOriginalExtension();

    //     if ($extension === 'pdf') {
    //         $parser = new PdfParser();
    //         $pdf = $parser->parseFile($file->getPathname());
    //         return trim($pdf->getText());
    //     }

    //     if ($extension === 'docx') {
    //         $phpWord = IOFactory::load($file->getPathname());
    //         $text = '';

    //         foreach ($phpWord->getSections() as $section) {
    //             foreach ($section->getElements() as $element) {
    //                 if (method_exists($element, 'getText')) {
    //                     $text .= $element->getText() . "\n";
    //                 }
    //             }
    //         }

    //         return trim($text);
    //     }

    //     return null;
    // }

    // private function prompt(string $resumeText): string
    // {
    //     $resumeText = mb_convert_encoding(
    //         $resumeText,
    //         'UTF-8',
    //         'UTF-8, ISO-8859-1, ISO-8859-15, Windows-1252'
    //     );

    //     $resumeText = preg_replace('/[^\P{C}\n]+/u', '', $resumeText);

    //     return "Parse the following resume and extract information into this exact JSON structure. Fill in all available information from the resume text. If a field is not found, use an empty string \"\" or empty array [].

    //     RESUME TEXT:
    //     {$resumeText}

    //     Return ONLY valid JSON in this exact structure (no markdown, no code blocks, no explanations):
    //     {
    //         \"name\": \"\",
    //         \"email\": \"\",
    //         \"phone\": \"\",
    //         \"linkedin\": \"\",
    //         \"github\": \"\",
    //         \"website\": \"\",
    //         \"skills\": [],
    //         \"experience\": [
    //             {
    //             \"company\": \"\",
    //             \"title\": \"\",
    //             \"start_date\": \"\",
    //             \"end_date\": \"\",
    //             \"description\": \"\"
    //             }
    //         ],
    //         \"education\": [
    //         {
    //             \"degree\": \"\",
    //             \"institution\": \"\",
    //             \"start_date\": \"\",
    //             \"end_date\": \"\"
    //             }
    //         ],
    //         \"projects\": [
    //         {
    //             \"title\": \"\",
    //             \"description\": \"\",
    //             \"live_url\": \"\",
    //             \"github_url\": \"\"
    //             }
    //         ]
    //         }";
    // }

    // private function normalizeUrl(?string $url): ?string
    // {
    //     $url = is_string($url) ? trim($url) : null;
    //     if (empty($url)) return null;

    //     if (!preg_match('#^https?://#i', $url)) {
    //         $url = 'https://' . ltrim($url, '/');
    //     }

    //     return $url;
    // }

    // /**
    //  * Converts "12/2022" => "2022-12-01"
    //  * Converts "Present" => null
    //  */
    // private function parseMonthYearToDate($value): ?string
    // {
    //     if (!is_string($value)) return null;

    //     $v = trim($value);
    //     if ($v === '') return null;

    //     $lower = strtolower($v);
    //     if (str_contains($lower, 'present') || str_contains($lower, 'current') || str_contains($lower, 'now')) {
    //         return null;
    //     }

    //     if (preg_match('#^(0?[1-9]|1[0-2])/(19|20)\d{2}$#', $v)) {
    //         [$m, $y] = explode('/', $v);
    //         $m = str_pad($m, 2, '0', STR_PAD_LEFT);
    //         return "{$y}-{$m}-01";
    //     }

    //     if (preg_match('#^\d{4}-\d{2}-\d{2}$#', $v)) {
    //         return $v;
    //     }

    //     return null;
    // }

    //  public function CvParsing(Request $request)
    // {
    //     $userId = Auth::guard('sanctum')->id();
    //     if (!$userId) {
    //         return response()->api(null, false, 'Unauthenticated', 401);
    //     }

    //     /** @var \App\Models\User $user */
    //     $user = User::findOrFail($userId);
    //     $validated = $request->validate([
    //         'resume' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
    //     ]);

    //     if (!$request->hasFile('resume')) {
    //         return response()->api(null, false, 'Resume file is required', 422);
    //     }

    //     $file = $request->file('resume');

    //     // 1) Extract resume text
    //     $text = $this->extractText($file);
    //     if (!is_string($text) || trim($text) === '') {
    //         return response()->api(null, false, 'Unable to extract text from resume', 422);
    //     }

    //     // 2) Call Groq for JSON extraction
    //     $response = Http::withHeaders([
    //         'Authorization' => 'Bearer ' . config('services.groq.key'),
    //         'Content-Type'  => 'application/json',
    //     ])->post('https://api.groq.com/openai/v1/chat/completions', [
    //         'model'       => 'llama-3.3-70b-versatile',
    //         'temperature' => 0,
    //         'messages'    => [
    //             [
    //                 'role'    => 'system',
    //                 'content' => 'You are a resume parser. Always return VALID JSON ONLY. If a field is missing, return an empty string "" or empty array []. No explanations.'
    //             ],
    //             [
    //                 'role'    => 'user',
    //                 'content' => $this->prompt($text)
    //             ]
    //         ]
    //     ]);

    //     if (!$response->successful()) {
    //         return response()->api(null, false, 'Resume parsing failed', 500);
    //     }

    //     $content = $response->json('choices.0.message.content');
    //     if (!is_string($content) || trim($content) === '') {
    //         return response()->api(null, false, 'Empty parser response', 500);
    //     }

    //     $parsedJson = json_decode($content, true);
    //     if (json_last_error() !== JSON_ERROR_NONE || !is_array($parsedJson)) {
    //         return response()->api([
    //             'raw' => $content,
    //             'json_error' => json_last_error_msg(),
    //         ], false, 'Invalid JSON from parser', 422);
    //     }

    //     // 3) Save resume file like profileUpdate
    //     $fileName = Str::uuid()->toString() . '.' . $file->getClientOriginalExtension();
    //     $year  = now()->format('Y');
    //     $month = now()->format('m');
    //     $filePath = $file->storeAs("resumes/{$year}/{$month}", $fileName, 'public');

    //     // 4) Update profile + experiences + educations + projects
    //     DB::beginTransaction();

    //     try {
    //         // ---- Update User (only if present) ----
    //         if (!empty($parsedJson['name'])) {
    //             $user->name = $parsedJson['name'];
    //         }
    //         // if (!empty($parsedJson['email'])) {
    //         //     $user->email = $parsedJson['email'];
    //         // }
    //         $user->save();

    //         // ---- Normalize links ----
    //         $linkedin = $this->normalizeUrl($parsedJson['linkedin'] ?? null);
    //         $website  = $this->normalizeUrl($parsedJson['website'] ?? null);
    //         $github   = $this->normalizeUrl($parsedJson['github'] ?? null);

    //         // ---- Skills (array) ----
    //         $skills = null;
    //         if (!empty($parsedJson['skills']) && is_array($parsedJson['skills'])) {
    //             $skills = collect($parsedJson['skills'])
    //                 ->map(fn($s) => is_string($s) ? trim($s) : '')
    //                 ->filter(fn($s) => $s !== '')
    //                 ->unique()
    //                 ->values()
    //                 ->all();

    //             if (count($skills) === 0) {
    //                 $skills = null;
    //             }
    //         }

    //         // ---- Profile upsert ----
    //         $profileData = [
    //             'phone'          => $parsedJson['phone'] ?? null,
    //             'linkedin_url'   => $linkedin,
    //             'website'        => $website,
    //             'github_profile' => $github,
    //             'skills'         => $skills,
    //             'resume_path'    => $filePath,
    //         ];

    //         $user->profile()->updateOrCreate(
    //             ['user_id' => $user->id],
    //             $profileData
    //         );

    //         // ---- Experiences: delete + recreate ----
    //         UserExperience::where('user_id', $user->id)->delete();

    //         foreach (($parsedJson['experience'] ?? []) as $exp) {
    //             if (!is_array($exp)) continue;

    //             $company = $exp['company'] ?? null;
    //             $title   = $exp['title'] ?? null;
    //             $desc    = $exp['description'] ?? null;

    //             if (empty($company) && empty($title) && empty($desc)) {
    //                 continue;
    //             }

    //             $startDate = $this->parseMonthYearToDate($exp['start_date'] ?? null);
    //             $endRaw    = $exp['end_date'] ?? null;
    //             $endDate   = $this->parseMonthYearToDate($endRaw);

    //             $isCurrent = false;
    //             if (is_string($endRaw)) {
    //                 $lower = strtolower(trim($endRaw));
    //                 $isCurrent = str_contains($lower, 'present') || str_contains($lower, 'current') || str_contains($lower, 'now');
    //             }

    //             UserExperience::create([
    //                 'user_id'      => $user->id,
    //                 'company_name' => $company,
    //                 'job_title'    => $title,
    //                 'is_current'   => $isCurrent ? 1 : 0,
    //                 'start_date'   => $startDate,
    //                 'end_date'     => $isCurrent ? null : $endDate,
    //                 'description'  => $desc,
    //                 'location'     => null,
    //             ]);
    //         }

    //         // ---- Educations: delete + recreate ----
    //         UserEducation::where('user_id', $user->id)->delete();

    //         foreach (($parsedJson['education'] ?? []) as $edu) {
    //             if (!is_array($edu)) continue;

    //             $degree = $edu['degree'] ?? null;
    //             $inst   = $edu['institution'] ?? null;

    //             if (empty($degree) && empty($inst)) {
    //                 continue;
    //             }

    //             $startDate = $this->parseMonthYearToDate($edu['start_date'] ?? null);
    //             $endRaw    = $edu['end_date'] ?? null;
    //             $endDate   = $this->parseMonthYearToDate($endRaw);

    //             $isCurrent = false;
    //             if (is_string($endRaw)) {
    //                 $lower = strtolower(trim($endRaw));
    //                 $isCurrent = str_contains($lower, 'present') || str_contains($lower, 'current') || str_contains($lower, 'now');
    //             }

    //             UserEducation::create([
    //                 'user_id'      => $user->id,
    //                 'degree_title' => $degree,
    //                 'institution'  => $inst,
    //                 'is_current'   => $isCurrent ? 1 : 0,
    //                 'start_date'   => $startDate,
    //                 'end_date'     => $isCurrent ? null : $endDate,
    //                 'description'  => null,
    //                 'gpa'          => null,
    //             ]);
    //         }

    //         // ---- Projects: delete + recreate ----
    //         UserProject::where('user_id', $user->id)->delete();

    //         foreach (($parsedJson['projects'] ?? []) as $proj) {
    //             if (!is_array($proj)) continue;

    //             $title = $proj['title'] ?? null;
    //             $desc  = $proj['description'] ?? null;
    //             $live  = $this->normalizeUrl($proj['live_url'] ?? null);
    //             $gh    = $this->normalizeUrl($proj['github_url'] ?? null);

    //             if (empty($title) && empty($desc) && empty($live) && empty($gh)) {
    //                 continue;
    //             }

    //             UserProject::create([
    //                 'user_id'     => $user->id,
    //                 'title'       => $title,
    //                 'description' => $desc,
    //                 'live_url'    => $live,
    //                 'github_url'  => $gh,
    //             ]);
    //         }

    //         DB::commit();

    //         $user->load(['profile', 'experiences', 'educations', 'projects']);

    //         return response()->api([
    //             'parsed' => $parsedJson,
    //             'user'   => $user,
    //         ], true, 'Resume parsed and profile updated successfully', 200);

    //     } catch (\Throwable $e) {
    //         DB::rollBack();
    //         dd($e->getMessage());
    //         return response()->api(null, false, 'Failed to update profile from resume', 500);
    //     }
    // }


     public function CvParsing(Request $request)
    {
        $userId = Auth::guard('sanctum')->id();
        if (!$userId) {
            return response()->api(null, false, 'Unauthenticated', 401);
        }

             $validated = $request->validate([
            'resume'       => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240']
        ]);

         if ($request->hasFile('resume')) {
            $file = $request->file('resume');
            $text = $this->extractText($file);

        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.groq.key'),
            'Content-Type'  => 'application/json',
        ])->post('https://api.groq.com/openai/v1/chat/completions', [
            'model'       => 'llama-3.3-70b-versatile',
            'temperature' => 0,
            'messages'    => [
                [
                    'role'    => 'system',
                    'content' => 'You are a resume parser. Always return VALID JSON ONLY. If a field is missing, return an empty string "" or empty array []. No explanations.'
                ],
                [
                    'role'    => 'user',
                    'content' => $this->prompt($text)
                ]
            ]
        ]);

        if (!$response->successful()) {
            return null;
        }

        $content = $response->json('choices.0.message.content');
        if (!is_string($content) || $content === '') {
            return null;
        }

        $parsedJson = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($parsedJson)) {
            return null;
        }

        return $parsedJson;
    }

    private function extractText($file)
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'pdf') {
            $parser = new PdfParser();
            $pdf = $parser->parseFile($file->getPathname());
            return trim((string) $pdf->getText());
        }

        if ($extension === 'docx') {
            $phpWord = IOFactory::load($file->getPathname());
            $text = '';

            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if (method_exists($element, 'getText')) {
                        $text .= $element->getText() . "\n";
                    }
                }
            }

            return trim($text);
        }

        // NOTE: "doc" is not supported by PhpWord reliably.
        // If you must support .doc, consider converting to .docx server-side.
        return null;
    }

     private function prompt(string $resumeText): string
    {
            $resumeText = mb_convert_encoding(
                $resumeText,
                'UTF-8',
                'UTF-8, ISO-8859-1, ISO-8859-15, Windows-1252'
            );

            $resumeText = preg_replace('/[^\P{C}\n]+/u', '', $resumeText);

            return "Parse the following resume and extract information into this exact JSON structure. Fill in all available information from the resume text. If a field is not found, use an empty string \"\" or empty array [].

        RESUME TEXT:
        {$resumeText}

        Return ONLY valid JSON in this exact structure (no markdown, no code blocks, no explanations):
        {
        \"name\": \"\",
        \"email\": \"\",
        \"phone\": \"\",
        \"linkedin\": \"\",(only if it is url)
        \"github\": \"\",(only if it is url)
        \"website\": \"\",(only if it is url)
        \"skills\": [],
        \"experience\": [
            {
            \"company\": \"\",
            \"title\": \"\",
            \"start_month\": \"\",
            \"start_year\": \"\",
            \"end_month\": \"\",
            \"end_year\": \"\",
            \"description\": \"\",
            \"is_current\": \"\"
            }
        ],
        \"education\": [
            {
            \"degree\": \"\",
            \"institution\": \"\",
            \"start_month\": \"\",
            \"start_year\": \"\",
            \"end_month\": \"\",
            \"end_year\": \"\",
            \"is_current\": \"\"
            }
        ],
        \"projects\": [
            {
            \"title\": \"\",
            \"description\": \"\",
            \"live_url\": \"\",(only if it is url)
            \"github_url\": \"\" (only if it is url)
            }
        ]
        }";
    }

    private function normalizeUrl(?string $url): ?string
    {
        $url = is_string($url) ? trim($url) : null;
        if (empty($url)) return null;

        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    /**
     * Converts "12/2022" => "2022-12-01"
     * Converts "Present" => null
     */
    private function parseMonthYearToDate($value): ?string
    {
        if (!is_string($value)) return null;

        $v = trim($value);
        if ($v === '') return null;

        $lower = strtolower($v);
        if (str_contains($lower, 'present') || str_contains($lower, 'current') || str_contains($lower, 'now')) {
            return null;
        }

        if (preg_match('#^(0?[1-9]|1[0-2])/(19|20)\d{2}$#', $v)) {
            [$m, $y] = explode('/', $v);
            $m = str_pad($m, 2, '0', STR_PAD_LEFT);
            return "{$y}-{$m}-01";
        }

        if (preg_match('#^\d{4}-\d{2}-\d{2}$#', $v)) {
            return $v;
        }

        return null;
    }

}
