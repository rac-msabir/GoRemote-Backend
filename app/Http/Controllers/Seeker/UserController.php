<?php

namespace App\Http\Controllers\Seeker;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\JobSeeker;
use App\Models\User;
use App\Models\UserProfile;     
use DB;
use App\Models\UserExperience;
use App\Models\UserEducation;
use App\Models\SeekerDesiredTitle;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function findSeeker(Request $request)
    {
        try {
            $seekers = User::with([
                'profile',
                'jobSeeker.desiredTitles' => function ($q) {
                    $q->orderBy('priority', 'asc');
                }
            ])
            ->where('role', 'seeker')
            ->paginate(10);

            if ($seekers->isEmpty()) {
                return response()->api(null, true, 'No seekers found', 200);
            }

            // Transform seekers for frontend
            $seekersTransformed = $seekers->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name ?? null,
                    'email' => $user->email ?? null,
                    'profile' => [
                        'phone'   => $user->profile->phone ?? null,
                        'city'    => $user->profile->city ?? null,
                        'country' => $user->profile->country ?? null,
                        'dob'     => $user->profile->dob ?? null,
                        'gender'  => $user->profile->gender ?? null,
                    ],
                    'desired_titles' => $user->jobSeeker->desiredTitles->map(function ($title) {
                        return [
                            'id'       => $title->id,
                            'title'    => $title->title ?? null,
                            'priority' => $title->priority ?? null,
                        ];
                    }),
                ];
            });

            $data = [
                'seekers' => $seekersTransformed,
                'pagination' => [
                    'current_page' => $seekers->currentPage(),
                    'last_page'    => $seekers->lastPage(),
                    'per_page'     => $seekers->perPage(),
                    'total'        => $seekers->total(),
                ],
            ];

            return response()->api($data); // ✅ success response
        } catch (\Throwable $e) {
            return response()->api(null, true, $e->getMessage(), 500); // ✅ error response
        }
    }

     public function profileView()
    {
        // Get the authenticated user's ID via Sanctum
        $userId = Auth::guard('sanctum')->id();

        if (!$userId) {
            return response()->api(null, false, 'Unauthenticated', 401);
        }

        // Load user with experiences and educations
        $user = User::with(['profile','experiences', 'educations','profile'])->find($userId);

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

        // Validate basic fields + nested arrays
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'email'          => ['required', 'email', 'max:255'],
            'phone'          => ['nullable', 'string', 'max:50'],

            'country'        => ['nullable', 'string', 'max:255'],
            'province'       => ['nullable', 'string', 'max:255'],
            'city'           => ['nullable', 'string', 'max:255'],
            'zip'            => ['nullable', 'string', 'max:50'],
            'address'        => ['nullable', 'string'],

            'linkedin_url'   => ['nullable', 'url'],
            'cover_letter'   => ['nullable', 'string'],
            'resume'         => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],

            'experiences'                        => ['array'],
            'experiences.*.company_name'         => ['nullable', 'string', 'max:255'],
            'experiences.*.job_title'            => ['nullable', 'string', 'max:255'],
            'experiences.*.is_current'           => ['nullable'],
            'experiences.*.start_date'           => ['nullable', 'date'],
            'experiences.*.end_date'             => ['nullable', 'date'],
            'experiences.*.description'          => ['nullable', 'string'],
            'experiences.*.location'             => ['nullable', 'string', 'max:255'],

            'educations'                         => ['array'],
            'educations.*.degree_title'          => ['nullable', 'string', 'max:255'],
            'educations.*.institution'           => ['nullable', 'string', 'max:255'],
            'educations.*.is_current'            => ['nullable'],
            'educations.*.start_date'            => ['nullable', 'date'],
            'educations.*.end_date'              => ['nullable', 'date'],
            'educations.*.description'           => ['nullable', 'string'],
            'educations.*.gpa'                   => ['nullable', 'string', 'max:50'],
        ]);

        DB::beginTransaction();

        try {
            // 1) Update user basic info
            $user->name  = $validated['name'];
            $user->email = $validated['email'];
            $user->save();

            // 2) Create / update profile
            $profileData = [
                'phone'          => $validated['phone'] ?? null,
                'country'        => $validated['country'] ?? null,
                'province'       => $validated['province'] ?? null,
                'city'           => $validated['city'] ?? null,
                'zip'            => $validated['zip'] ?? null,
                'address'        => $validated['address'] ?? null,
                'linkedin_url'   => $validated['linkedin_url'] ?? null,
                'cover_letter'   => $validated['cover_letter'] ?? null,
            ];

            // Handle resume upload if provided
            if ($request->hasFile('resume')) {
                $file      = $request->file('resume');
                $fileName  = Str::uuid()->toString() . '.' . $file->getClientOriginalExtension();
                $filePath  = $file->storeAs('resumes', $fileName, 'public');
                $profileData['resume_path'] = $filePath;
            }

            // Assuming relation: $user->profile() (hasOne)
            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                $profileData
            );

            // 3) Experiences: simple approach – delete and recreate
            UserExperience::where('user_id', $user->id)->delete();

            foreach ($request->input('experiences', []) as $experience) {
                // Skip completely empty rows
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
                    'is_current'   => isset($experience['is_current'])
                        ? (int) $experience['is_current']
                        : 0,
                    'start_date'   => $experience['start_date'] ?? null,
                    'end_date'     => !empty($experience['is_current'])
                        ? null
                        : ($experience['end_date'] ?? null),
                    'description'  => $experience['description'] ?? null,
                    'location'     => $experience['location'] ?? null,
                ]);
            }

            // 4) Educations: delete and recreate
            UserEducation::where('user_id', $user->id)->delete();

            foreach ($request->input('educations', []) as $education) {
                // Skip empty rows
                if (
                    empty($education['degree_title']) &&
                    empty($education['institution'])
                ) {
                    continue;
                }

                UserEducation::create([
                    'user_id'      => $user->id,
                    'degree_title' => $education['degree_title'] ?? null,
                    'institution'  => $education['institution'] ?? null,
                    'is_current'   => isset($education['is_current'])
                        ? (int) $education['is_current']
                        : 0,
                    'start_date'   => $education['start_date'] ?? null,
                    'end_date'     => !empty($education['is_current'])
                        ? null
                        : ($education['end_date'] ?? null),
                    'description'  => $education['description'] ?? null,
                    'gpa'          => $education['gpa'] ?? null,
                ]);
            }

            DB::commit();

            // Reload with relations to return to frontend
            $user->load(['profile', 'experiences', 'educations']);

            return response()->api(
                $user,
                true,
                'Profile updated successfully',
                200
            );
        } catch (\Throwable $exception) {
            DB::rollBack();

            report($exception);

            return response()->api(
                null,
                false,
                'Failed to update profile',
                500
            );
        }
    }

}
