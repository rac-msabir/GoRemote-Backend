<?php

namespace App\Http\Controllers\Seeker;

use App\Http\Controllers\Controller;
use Auth;
use DB;
use Carbon\Carbon;
use App\Models\Job;
use Illuminate\Support\Str;
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
            // --- Optional auth/role gate ---
            // $user = $request->user();
            // if (!$user || $user->role !== 'employer') {
            //     return response()->api(null, true, 'Only employers can post jobs.', 403);
            // }
            $employerId = auth()->id() ?? 1; // replace with $user->id when you enable the gate

            // ---------------------------------------------------
            // Helpers
            // ---------------------------------------------------
            $normalizeIdArray = function ($input) {
                if (is_array($input)) {
                    $arr = $input;
                } elseif (is_string($input)) {
                    $trimmed = trim($input);
                    $json = json_decode($trimmed, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                        $arr = $json;
                    } else {
                        $arr = strpos($trimmed, ',') !== false
                            ? explode(',', $trimmed)
                            : ($trimmed !== '' ? [$trimmed] : []);
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

            $normalizeEnum = function (?string $v) {
                if ($v === null) return null;
                return str_replace('-', '_', strtolower($v));
            };

            // ---------------------------------------------------
            // Normalize incoming arrays/enums BEFORE validating
            // ---------------------------------------------------
            $skills        = $normalizeIdArray($request->input('skills'));
            $benefits      = $normalizeIdArray($request->input('benefits'));
            $jobTypeInput  = $normalizeEnum($request->input('job_type'));
            $locTypeInput  = $normalizeEnum($request->input('location_type'));

            $request->merge([
                'skills'        => $skills,
                'benefits'      => $benefits,
                'job_type'      => $jobTypeInput,
                'location_type' => $locTypeInput,
            ]);

            // ---------------------------------------------------
            // Validate base fields (description validated loosely here)
            // ---------------------------------------------------
            $validated = $request->validate([
                'title'             => 'required|string|max:191',
                'category_id'       => 'required|integer|exists:categories,id',

                // description arrives as array of section-objects; allow array|json
                'description'       => 'required',

                'job_type'          => 'required|in:full_time,part_time,temporary,contract,internship,fresher',
                'location_type'     => 'required|in:on_site,hybrid,remote',

                'city'              => 'nullable|string|max:120',
                'state_province'    => 'nullable|string|max:120',
                'vacancies'         => 'nullable|integer|min:1',
                'country_name'      => 'nullable|string|max:120',
                'country_code'      => 'nullable|string|size:2',

                'apply_url'         => 'nullable|url',
                'apply_email'       => 'nullable|email',

                'skills'            => 'nullable|array',
                'skills.*'          => 'integer|exists:skills,id',

                'benefits'          => 'nullable|array',
                'benefits.*'        => 'integer|exists:job_benefits,id', // adjust table if different

                // Optional compensation fields if provided
                'pay_min'           => 'nullable|numeric',
                'pay_max'           => 'nullable|numeric|gte:pay_min',
                'currency'          => 'nullable|string|size:3',
                'pay_period'        => 'nullable|in:hour,day,week,month,year',
                'pay_visibility'    => 'nullable|in:range,exact,starting_at',

                // Optional extras
                'location'          => 'nullable|string|max:191',
                'company_id'        => 'nullable|integer|exists:companies,id',
            ]);

            // Accept alias "state" from FE if provided
            if ($request->filled('state') && empty($validated['state_province'])) {
                $validated['state_province'] = $request->string('state')->toString();
            }

            // Backward compat: "countries" -> country_name
            if ($request->filled('countries') && empty($validated['country_name'])) {
                $validated['country_name'] = $request->string('countries')->toString();
            }

            // Default vacancies to 1 if missing
            if (!isset($validated['vacancies']) || $validated['vacancies'] === null) {
                $validated['vacancies'] = 1;
            }

            // ---------------------------------------------------
            // Parse description sections array
            // - Expected: array of objects, each with one key:
            //   [{ "overview": [..] }, { "responsibilities": [..] }, { "requirements": [..] }]
            // - Also tolerate a single object form.
            // ---------------------------------------------------
            $descPayload = $request->input('description');

            // If JSON string, decode
            if (is_string($descPayload)) {
                $decoded = json_decode($descPayload, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $descPayload = $decoded;
                }
            }

            // Normalize to an array of section objects
            if (is_array($descPayload) && self::isAssoc($descPayload)) {
                // Single object form => wrap
                $descPayload = [$descPayload];
            } elseif (!is_array($descPayload)) {
                // Invalid shape -> normalize to empty to avoid errors downstream
                $descPayload = [];
            }

            // Map section keys to DB `type` values
            $sectionKeyToType = function (string $key): ?string {
                $k = strtolower(str_replace('-', '_', $key));
                return match ($k) {
                    'overview'         => 'overview',
                    'responsibilities' => 'responsibility',
                    'requirements'     => 'requirement',
                    default            => null, // ignore unknown keys
                };
            };

            // Build a flat list of [type => string item] for insert
            $descRows = [];
            foreach ($descPayload as $sectionObj) {
                if (!is_array($sectionObj)) continue;

                foreach ($sectionObj as $key => $items) {
                    $type = $sectionKeyToType($key);
                    if (!$type) continue;

                    // items can be array or string; normalize to array of strings
                    if (is_string($items)) {
                        $items = [$items];
                    }
                    if (!is_array($items)) continue;

                    foreach ($items as $content) {
                        $content = trim((string) $content);
                        if ($content === '') continue;
                        $descRows[] = ['type' => $type, 'content' => $content];
                    }
                }
            }

            // ---------------------------------------------------
            // Persist everything in a transaction
            // ---------------------------------------------------
            return DB::transaction(function () use ($validated, $employerId, $skills, $benefits, $request, $descRows) {
                $job = Job::create([
                    'uuid'            => (string) Str::uuid(),
                    'employer_id'     => $employerId,
                    'company_id'      => $request->input('company_id'),

                    'title'           => $validated['title'],
                    'slug'            => Str::slug($validated['title']) . '-' . substr((string) Str::uuid(), 0, 8),

                    'category_id'     => $validated['category_id'],

                    // NOTE: main HTML/body can also be stored; if you want to keep a raw HTML section,
                    // you can either add a column or keep using descriptions[] with 'overview'.
                    // Here we do NOT store a standalone longtext "description" since your payload moved to structured sections.
                    // If your jobs table still has `description` column and you want to keep an HTML summary, uncomment below:
                    // 'description'    => is_string($request->input('description_html')) ? $request->input('description_html') : null,

                    'job_type'        => $validated['job_type'],
                    'location_type'   => $validated['location_type'],
                    'location'        => $request->input('location'),

                    'city'            => $validated['city'] ?? null,
                    'state_province'  => $validated['state_province'] ?? null,
                    'country_name'    => $validated['country_name'] ?? null,
                    'country_code'    => $validated['country_code'] ?? null,

                    'vacancies'       => $validated['vacancies'],

                    'pay_min'         => $request->input('pay_min'),
                    'pay_max'         => $request->input('pay_max'),
                    'currency'        => $request->input('currency'),
                    'pay_period'      => $request->input('pay_period'),
                    'pay_visibility'  => $request->input('pay_visibility'),

                    'status'          => 'published',
                    'posted_at'       => now(),
                ]);

                // Pivot: skills
                if (!empty($skills)) {
                    $job->skills()->sync($skills);
                }

                // Pivot: benefits
                if (!empty($benefits)) {
                    $job->benefits()->sync($benefits);
                }

                // job_descriptions bulk insert (overview/responsibility/requirement)
                if (!empty($descRows)) {
                    foreach ($descRows as $r) {
                        $job->descriptions()->create([
                            'type'    => $r['type'],
                            'content' => $r['content'],
                        ]);
                    }
                }

                // Optionally persist company meta (if you actually store them elsewhere)
                // $job->meta()->create([...]) or update related Company model, etc.

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

    /**
     * Tiny helper: is array associative?
     */
    private static function isAssoc(array $arr): bool
    {
        if ([] === $arr) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

}


