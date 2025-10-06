<?php

namespace App\Http\Controllers\Seeker;

use App\Http\Controllers\Controller;
use Auth;
use DB;
use Carbon\Carbon;
use App\Models\Job;
use App\Models\JobBenefit;
use App\Models\SavedJob;
use App\Models\JobSeeker;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class SavedJobController extends Controller
{
    public function store(Request $request, Job $job)
    {
        try {
            $seeker = JobSeeker::firstOrCreate(['user_id' => $request->user()->id]);

            $seeker->savedJobs()->syncWithoutDetaching([
                $job->id => ['created_at' => now()]
            ]);

            $data = [
                'message' => 'Job saved successfully',
                'saved'   => true,
                'job_id'  => $job->id,
            ];

            return response()->api($data); // ✅ success response

        } catch (\Throwable $e) {
            
            return response()->api(null, true, $e->getMessage(), 500); // ✅ error response
        }
    }


    public function destroy(Request $request, Job $job)
    {
        try {
            $seeker = JobSeeker::firstOrCreate(['user_id' => $request->user()->id]);

            $seeker->savedJobs()->detach($job->id);

            $data = [
                'message' => 'Job unsaved successfully',
                'saved'   => false,
                'job_id'  => $job->id,
            ];

            return response()->api($data); // ✅ success response
        } catch (\Throwable $e) {
            return response()->api(null, true, $e->getMessage(), 500); // ✅ error response
        }
    }

    public function postJobs(Request $request)
    {
        try {
            // $user = $request->user();
            // if (!$user || $user->role !== 'employer') {
            //     return response()->api(null, true, 'Only employers can post jobs.', 403);
            // }
            $employerId = auth()->id() ?? 1; // replace with $user->id if you enable the auth check

            // ---------------------------
            // Normalize arrays (skills, benefits) and enums (job_type, location_type)
            // ---------------------------
            $normalizeIdArray = function ($input) {
                if (is_array($input)) {
                    $arr = $input;
                } elseif (is_string($input)) {
                    $trimmed = trim($input);
                    $json = json_decode($trimmed, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                        $arr = $json;
                    } else {
                        $arr = strpos($trimmed, ',') !== false ? explode(',', $trimmed) : ($trimmed !== '' ? [$trimmed] : []);
                    }
                } elseif (is_numeric($input)) {
                    $arr = [$input];
                } else {
                    $arr = [];
                }
                // cast to ints, dedupe, drop empties/non-numerics
                return array_values(array_unique(array_filter(array_map(function ($v) {
                    return is_numeric($v) ? (int) $v : null;
                }, (array) $arr))));
            };

            // Normalize skills & benefits to array<int>
            $skills   = $normalizeIdArray($request->input('skills'));
            $benefits = $normalizeIdArray($request->input('benefits'));

            // Normalize enums: front-end uses hyphens; DB uses underscores
            $normalizeEnum = function (?string $v) {
                if ($v === null) return null;
                return str_replace('-', '_', strtolower($v));
            };
            $jobTypeInput      = $normalizeEnum($request->input('job_type'));
            $locationTypeInput = $normalizeEnum($request->input('location_type'));

            // Merge normalized values back into request before validation
            $request->merge([
                'skills'        => $skills,
                'benefits'      => $benefits,
                'job_type'      => $jobTypeInput,
                'location_type' => $locationTypeInput,
            ]);

            // ---------------------------
            // Validate
            // ---------------------------
            $validated = $request->validate([
                'title'           => 'required|string|max:191',
                'category_id'     => 'required|integer|exists:categories,id',
                'description'     => 'required|string',
                'job_type'        => 'required|in:full_time,part_time,temporary,contract,internship,fresher',
                'location_type'   => 'required|in:on_site,hybrid,remote',
                'city'            => 'nullable|string|max:120',
                'state_province'  => 'nullable|string|max:120', // you can also accept "state" and map below
                'vacancies'       => 'nullable|integer|min:1',
                'country_name'    => 'nullable|string|max:120',
                'country_code'    => 'nullable|string|size:2',

                'apply_url'       => 'nullable|url',
                'apply_email'     => 'nullable|email',

                'skills'          => 'nullable|array',
                'skills.*'        => 'integer|exists:skills,id',

                'benefits'        => 'nullable|array',
                'benefits.*'      => 'integer|exists:job_benefits,id', // change table if yours is different

                // Optional arrays of strings that go to job_descriptions
                'responsibilities'=> 'nullable|array',
                'responsibilities.*' => 'nullable|string',
                'requirements'    => 'nullable|array',
                'requirements.*'  => 'nullable|string',
            ]);

            // Accept "state" alias from FE and map into state_province if provided
            $stateFromAlias = $request->input('state');
            if (!empty($stateFromAlias) && empty($validated['state_province'])) {
                $validated['state_province'] = $stateFromAlias;
            }

            // Accept "countries" (old field) as country_name if you don’t have separate code/name
            $countriesLegacy = $request->input('countries');
            if (!empty($countriesLegacy) && empty($validated['country_name'])) {
                $validated['country_name'] = $countriesLegacy;
            }

            // Vacancies default (table default = 1)
            if (!isset($validated['vacancies']) || $validated['vacancies'] === null) {
                $validated['vacancies'] = 1;
            }

            // ---------------------------
            // Create records in a transaction
            // ---------------------------
            return DB::transaction(function () use ($validated, $employerId, $skills, $benefits, $request) {
                $job = Job::create([
                    'uuid'           => (string) \Str::uuid(),
                    'employer_id'    => $employerId,
                    'company_id'     => $request->input('company_id'), // optional if you track companies
                    'title'          => $validated['title'],
                    'slug'           => \Str::slug($validated['title']) . '-' . substr((string) \Str::uuid(), 0, 8),

                    'category_id'    => $validated['category_id'],
                    'description'    => $validated['description'],

                    'job_type'       => $validated['job_type'],        // DB enum underscores
                    'location_type'  => $validated['location_type'],   // DB enum underscores
                    'location'       => $request->input('location'),   // optional free-text location, if you use it

                    'city'           => $validated['city'] ?? null,
                    'state_province' => $validated['state_province'] ?? null,
                    'country_name'   => $validated['country_name'] ?? null,
                    'country_code'   => $validated['country_code'] ?? null,

                    'vacancies'      => $validated['vacancies'],

                   // Salary fields if you plan to split later:
                    'pay_min'      => $request->input('pay_min'),
                    'pay_max'      => $request->input('pay_max'),
                    'currency'     => $request->input('currency'),
                    'pay_period'   => $request->input('pay_period'),   // hour/day/week/month/year
                    'pay_visibility'=> $request->input('pay_visibility'), // range/exact/starting_at

                    //Status fields
                    'status'       => 'published', // default from schema
                    'posted_at'    => now(),
                ]);

                // Pivot: skills
                if (!empty($skills)) {
                    $job->skills()->sync($skills);
                }

                // Pivot: benefits
                if (!empty($benefits)) {
                    $job->benefits()->sync($benefits);
                }

                // job_descriptions: responsibilities & requirements as separate rows
                $makeDescriptions = function (array $items = null, string $type) use ($job) {
                    if (empty($items)) return;
                    foreach ($items as $content) {
                        $content = trim((string) $content);
                        if ($content === '') continue;
                        $job->descriptions()->create([
                            'type'    => $type,      // 'responsibility' or 'requirement'
                            'content' => $content,
                        ]);
                    }
                };

                $makeDescriptions($request->input('responsibilities', []), 'responsibility');
                $makeDescriptions($request->input('requirements', []), 'requirement');

                // If you also want to capture a separate "why choose us" list into job_descriptions:
                // $makeDescriptions($request->input('benefits_text', []), 'benefit'); // if you had textual benefits too

                return response()->api(
                    ['job' => $job->load('skills', 'benefits', 'descriptions')],
                    false,
                    null,
                    201
                );
            });

        } catch (\Throwable $e) {
            return response()->api(null, true, $e->getMessage(), 500);
        }
    }

}


