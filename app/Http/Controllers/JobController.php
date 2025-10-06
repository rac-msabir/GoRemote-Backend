<?php

namespace App\Http\Controllers;

use App\Models\Job;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use App\Models\JobSeeker;
use App\Models\User;

class JobController extends Controller
{
   public function index(Request $request)
{
    try {
        $perPage = max(1, (int) $request->query('per_page', 20));

        // Static lookups (1x each)
        $benefitsList  = DB::table('job_benefits')->select('id', 'name')->orderBy('name')->get();
        $categories    = DB::table('categories')->select('id', 'name')->orderBy('name')->get();
        $employersList = DB::table('employers')->select('id', 'company_name')->orderBy('company_name')->get();

        // Compute "featured" cut-off once
        $featuredCutoff = now()->subDays(7)->toDateTimeString();

        $query = Job::query()
            ->with(['descriptions']) // eager-load descriptions
            ->select([
                'jobs.*',
                'employers.company_name as company_name',
                'employers.website as employer_website',
                'employers.image as company_logo',
                'categories.name as category_name',
            ])
            // lightweight computed flag used only for sorting (and we’ll reuse in payload)
            ->addSelect(DB::raw("
                CASE
                    WHEN (jobs.pay_max IS NOT NULL AND jobs.pay_max >= 150000)
                         OR (jobs.posted_at >= '{$featuredCutoff}' AND jobs.job_type = 'full_time')
                    THEN 1 ELSE 0
                END as __is_featured
            "))
            ->leftJoin('employers', 'employers.id', '=', 'jobs.employer_id')
            ->leftJoin('categories', 'categories.id', '=', 'jobs.category_id')
            ->where('jobs.status', 'published');

        /** ----------------- Filters ----------------- */

        // EXPERIENCE (only if provided)
        if ($expRaw = $request->input('experiencelevel')) {
            $label   = Str::lower(trim($expRaw));
            $pattern = null;
            if (Str::startsWith($label, '0-1')) {
                $pattern = implode('|', [
                    '\b0-1\s*years?\b',
                    '\b(?:0|zero|1|one)\s*(?:\+?\s*)?(?:years?|yrs?)\b',
                    '\bno\s+experience\b',
                    '\bentry[-\s]?level\b',
                    '\bfresh(?:er)?\b',
                ]);
            } elseif (Str::startsWith($label, '2+')) {
                $pattern = implode('|', [
                    '\b(?:(?:[2-9]|[1-9][0-9]+))\s*\+?\s*(?:years?|yrs?)\b',
                    '\b(?:two|three|four|five|six|seven|eight|nine|ten)\s*\+?\s*(?:years?|yrs?)\b',
                    '\b(?:at\s+least|min(?:imum)?)\s*(?:2|two)\s*(?:years?|yrs?)\b',
                ]);
            } elseif (Str::startsWith($label, '3+')) {
                $pattern = implode('|', [
                    '\b(?:(?:[3-9]|[1-9][0-9]+))\s*\+?\s*(?:years?|yrs?)\b',
                    '\b(?:three|four|five|six|seven|eight|nine|ten)\s*\+?\s*(?:years?|yrs?)\b',
                    '\b(?:at\s+least|min(?:imum)?)\s*(?:3|three)\s*(?:years?|yrs?)\b',
                ]);
            } elseif (Str::startsWith($label, '5+')) {
                $pattern = implode('|', [
                    '\b(?:(?:[5-9]|[1-9][0-9]+))\s*\+?\s*(?:years?|yrs?)\b',
                    '\b(?:five|six|seven|eight|nine|ten)\s*\+?\s*(?:years?|yrs?)\b',
                    '\b(?:at\s+least|min(?:imum)?)\s*(?:5|five)\s*(?:years?|yrs?)\b',
                ]);
            } elseif (Str::startsWith($label, '10+')) {
                $pattern = implode('|', [
                    '\b(?:(?:1[0-9]|[2-9][0-9]+))\s*\+?\s*(?:years?|yrs?)\b',
                    '\b(?:ten|eleven|twelve)\s*\+?\s*(?:years?|yrs?)\b',
                    '\b(?:at\s+least|min(?:imum)?)\s*(?:10|ten)\s*(?:years?|yrs?)\b',
                ]);
            }
            if ($pattern) {
                $query->whereRaw('LOWER(jobs.description) REGEXP ?', [$pattern]);
            }
        }

        // SEARCH (simple & fast; across key fields)
        if (($search = trim((string) $request->query('search'))) !== '') {
            $terms = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY);
            $cols = [
                'jobs.title',
                'jobs.description',
                'jobs.job_type',
                'jobs.city',
                'jobs.state_province',
                'jobs.country_name',
                'jobs.country_code',
                'employers.company_name',
                'categories.name',
            ];
            $query->where(function ($q) use ($terms, $cols) {
                foreach ($terms as $term) {
                    $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $term) . '%';
                    $q->where(function ($qq) use ($cols, $like) {
                        foreach ($cols as $col) {
                            $qq->orWhere($col, 'like', $like);
                        }
                    });
                }
            });
        }

        if ($jobType = $request->string('jobtypes')->toString()) {
            $query->where('jobs.job_type', 'like', "%$jobType%");
        }

        if (($benefitId = $request->integer('benefits')) > 0) {
            $query->whereExists(function ($sub) use ($benefitId) {
                $sub->selectRaw(1)
                    ->from('job_benefit_job as jb')
                    ->whereColumn('jb.job_id', 'jobs.id')
                    ->where('jb.job_benefit_id', $benefitId);
            });
        }

        if (($categoryId = $request->integer('category')) > 0) {
            $query->where('jobs.category_id', $categoryId);
        }

        // COUNTRIES
        $countries = [];
        if ($c = $request->string('country')->toString()) { $countries[] = $c; }
        $countriesInput = $request->input('countries', []);
        if (is_string($countriesInput)) {
            $countriesInput = array_filter(array_map('trim', explode(',', $countriesInput)));
        }
        $countries = array_merge($countries, is_array($countriesInput) ? $countriesInput : []);
        if (!empty($countries)) {
            $countriesUpper = array_map('strtoupper', $countries);
            $query->where(function ($q) use ($countriesUpper, $countries) {
                $q->whereIn('jobs.country_code', $countriesUpper)
                  ->orWhereIn('jobs.country_name', $countries);
            });
        }

        // SALARY
        if (($salary = $request->query('salary')) && is_string($salary)) {
            $query->where(function ($q) use ($salary) {
                $parsed = $this->parseSalaryRange($salary); // ['min'=>?int,'max'=>?int]
                if (!$parsed) return;

                $min = $parsed['min'];
                $max = $parsed['max'];

                if ($min !== null && $max !== null) {
                    $q->where(function ($qq) use ($min, $max) {
                        $qq
                        ->where(function ($c) use ($min, $max) {
                            $c->whereNotNull('jobs.pay_min')
                              ->whereNotNull('jobs.pay_max')
                              ->where('jobs.pay_min', '<=', $max)
                              ->where('jobs.pay_max', '>=', $min);
                        })
                        ->orWhere(function ($c) use ($min, $max) {
                            $c->whereNotNull('jobs.pay_min')
                              ->whereNull('jobs.pay_max')
                              ->whereBetween('jobs.pay_min', [$min, $max]);
                        })
                        ->orWhere(function ($c) use ($min, $max) {
                            $c->whereNull('jobs.pay_min')
                              ->whereNotNull('jobs.pay_max')
                              ->whereBetween('jobs.pay_max', [$min, $max]);
                        });
                    });
                } elseif ($min !== null) {
                    $q->where(function ($qq) use ($min) {
                        $qq->where(function ($c) use ($min) {
                                $c->whereNotNull('jobs.pay_min')->where('jobs.pay_min', '>=', $min);
                            })
                           ->orWhere(function ($c) use ($min) {
                                $c->whereNotNull('jobs.pay_max')->where('jobs.pay_max', '>=', $min);
                            });
                    });
                } elseif ($max !== null) {
                    $q->where(function ($qq) use ($max) {
                        $qq->where(function ($c) use ($max) {
                                $c->whereNotNull('jobs.pay_min')->where('jobs.pay_min', '<=', $max);
                            })
                           ->orWhere(function ($c) use ($max) {
                                $c->whereNotNull('jobs.pay_max')->where('jobs.pay_max', '<=', $max);
                            });
                    });
                }
            });
        }

        // SKILLS
        $skillSlugs = $request->input('skills', []);
        if (is_string($skillSlugs)) {
            $skillSlugs = array_filter(array_map('trim', explode(',', $skillSlugs)));
        }
        if (!empty($skillSlugs)) {
            $slugged = array_map(fn($v) => Str::slug($v), $skillSlugs);
            $names   = $skillSlugs;
            $query->whereIn('jobs.id', function ($sub) use ($slugged, $names) {
                $sub->select('job_skill.job_id')
                    ->from('job_skill')
                    ->join('skills', 'skills.id', '=', 'job_skill.skill_id')
                    ->where(function ($qq) use ($slugged, $names) {
                        $qq->whereIn('skills.slug', $slugged)
                           ->orWhereIn('skills.name', $names);
                    });
            });
        }

        // DATE POSTED
        if (($datePostedRaw = $request->input('dateposted')) !== null
            && $datePostedRaw !== '' && Str::lower($datePostedRaw) !== 'any') {
            $label = Str::of($datePostedRaw)->lower()->trim();
            $from = match ((string) $label) {
                'last 24 hours', '24h', '1d', '1 day'     => now()->subDay(),
                'last 7 days', '7d'                       => now()->subDays(7),
                'last 30 days', '30d'                     => now()->subDays(30),
                'last 2 months', '2m', 'last two months'  => now()->subMonthsNoOverflow(2),
                default                                   => null,
            };
            if (!$from && preg_match('/last\s+(\d+)\s+(day|days|month|months|hour|hours)/i', (string) $datePostedRaw, $m)) {
                $n = (int) $m[1];
                $unit = Str::lower($m[2]);
                $from = match ($unit) {
                    'hour', 'hours'   => Carbon::now()->subHours($n),
                    'day', 'days'     => Carbon::now()->subDays($n),
                    'month', 'months' => Carbon::now()->subMonthsNoOverflow($n),
                    default           => null,
                };
            }
            if ($from) {
                $query->where('jobs.posted_at', '>=', $from);
            }
        }

        if (($employerId = $request->integer('company')) > 0) {
            $query->where('jobs.employer_id', $employerId);
        }

        // SORT: Featured first, then by posted_at
        $sort = $request->string('sort')->toString();
        $query->orderByDesc('__is_featured')
              ->orderBy('jobs.posted_at', $sort === 'oldest' ? 'asc' : 'desc');

        /** --------------- Execute --------------- */
        $paginator = $query->paginate($perPage);

        // USER CONTEXT (batch)
        $userId = Auth::guard('sanctum')->id();
        $jobIds = collect($paginator->items())->pluck('id')->all();

        $appByJob = collect();
        if ($userId && $jobIds) {
            $appByJob = DB::table('job_applications as ja')
                ->select('ja.job_id', 'ja.job_seeker_id', 'ja.created_at')
                ->where('ja.job_seeker_id', $userId)
                ->whereIn('ja.job_id', $jobIds)
                ->orderBy('ja.created_at', 'desc')
                ->get()
                ->groupBy('job_id')
                ->map->first();
        }

        $savedByJob = collect();
        if ($userId && $jobIds) {
            $seekerId = JobSeeker::where('user_id', $userId)->value('id');
            if ($seekerId) {
                $savedByJob = DB::table('saved_jobs as sj')
                    ->select('sj.job_id')
                    ->where('sj.job_seeker_id', $seekerId)
                    ->whereIn('sj.job_id', $jobIds)
                    ->get()
                    ->keyBy('job_id');
            }
        }

        // BENEFITS (single round trip)
        $benefitsByJob = collect();
        if ($jobIds) {
            $benefitsByJob = DB::table('job_benefit_job as jb')
                ->join('job_benefits as b', 'b.id', '=', 'jb.job_benefit_id')
                ->whereIn('jb.job_id', $jobIds)
                ->orderBy('b.name')
                ->get(['jb.job_id', 'b.name'])
                ->groupBy('job_id')
                ->map(fn($rows) => $rows->pluck('name')->all());
        }

        /** --------------- Shape response --------------- */
        $data = collect($paginator->items())->map(function ($row) use ($appByJob, $savedByJob, $benefitsByJob) {
            $postedAt = $row->posted_at ?: $row->created_at;
            $closedAt = $row->closed_at;
            $isNew    = $postedAt ? Carbon::parse($postedAt)->greaterThanOrEqualTo(now()->subDays(7)) : false;

            // Prefer SQL-computed flag if present
            $isFeat = isset($row->__is_featured)
                ? (bool) $row->__is_featured
                : (($row->pay_max && $row->pay_max >= 150000) || ($isNew && $row->job_type === 'full_time'));

            $tags = [];
            if ($isFeat) $tags[] = 'Featured';
            if ($row->job_type) $tags[] = self::humanizeJobType($row->job_type);
            if ($row->location_type === 'remote') $tags[] = 'Remote';

            $salaryRange = null;
            if ($row->pay_min || $row->pay_max) {
                $fmt = fn ($v) => is_null($v) ? null : ('$' . number_format((float)$v / 1000, 0) . 'k');
                $min = $fmt($row->pay_min);
                $max = $fmt($row->pay_max);
                $salaryRange = $min && $max ? "$min - $max" : ($min ?: $max);
            }

            $company = [
                'name'         => $row->company_name ?? 'Unknown Company',
                'location'     => $row->location_type === 'remote'
                    ? 'Remote'
                    : (trim(implode(', ', array_filter([$row->city, $row->state_province, $row->country_code]))) ?: $row->country_code),
                'website'      => $row->employer_website,
                'company_logo' => $row->company_logo,
            ];

            $app   = $appByJob->get($row->id);
            $saved = $savedByJob->get($row->id);
            $benef = $benefitsByJob->get($row->id, []);

            // Dynamic descriptions grouped by 'type'
            $descriptions = collect($row->descriptions)->groupBy('type')->map(
                fn($items) => $items->pluck('content')->values()->all()
            );

            return [
                'id'               => $row->uuid,
                'title'            => $row->title,
                'company'          => $company,
                'vacancies'        => $row->vacancies,
                'location_type'    => $row->location_type,
                'job_type'         => self::humanizeJobType($row->job_type),
                'salary_range'     => $salaryRange,
                'tags'             => $tags,
                'is_featured'      => (bool) $isFeat,
                'is_new'           => (bool) $isNew,
                'posted_at'        => optional($postedAt)?->toISOString() ?? null,
                'closed_at'        => optional($closedAt)?->toISOString() ?? null,
                'descriptions'     => $descriptions,
                'benefits'         => $benef,
                'application_link' => $company['website'] ?: null,
                'has_applied'      => (bool) $app,
                'is_saved'         => (bool) $saved,
            ];
        })->all();

        $responseData = [
            'jobs'       => $data,
            'benefits'   => $benefitsList,
            'categories' => $categories,
            'employers'  => $employersList,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'total_pages'  => $paginator->lastPage(),
                'total_jobs'   => $paginator->total(),
            ],
        ];

        return response()->api($responseData);

    } catch (\Throwable $e) {
        return response()->api(null, true, $e->getMessage(), 500);
    }
}





//    public function show($jobId)
//     {
//         try {
//             // Find job by ID/UUID
//             $job = Job::with(['employer','preferences','screeningQuestions'])
//                     ->where('uuid', $jobId)
//                     ->first();

//             if (!$job) {
//                 // Job not found → return null in data
//                 return response()->api(null, true, null, 200);
//             }

//             // Company details
//             $company = [
//                 'name' => optional($job->employer)->company_name,
//                 'location' => $job->location_type === 'remote'
//                     ? 'Remote'
//                     : trim(implode(', ', array_filter([$job->city, $job->state_province, $job->country_code]))),
//                 'website' => optional($job->employer)->website,
//             ];

//             // Benefits (names)
//             $benefits = DB::table('job_benefit_job')
//                 ->join('job_benefits', 'job_benefits.id', '=', 'job_benefit_job.job_benefit_id')
//                 ->where('job_benefit_job.job_id', $job->id)
//                 ->pluck('job_benefits.name')
//                 ->all();

//             // Dates
//             $postedAt = $job->posted_at ?: $job->created_at;
//             $closedAt = $job->closed_at;

//             // Flags
//             $isNew = $postedAt ? Carbon::parse($postedAt)->greaterThanOrEqualTo(now()->subDays(7)) : false;
//             $isFeatured = ($job->pay_max && $job->pay_max >= 150000) || ($isNew && $job->job_type === 'full_time');

//             // Tags
//             $tags = [];
//             if ($isFeatured) $tags[] = 'Featured';
//             if ($job->job_type) $tags[] = self::humanizeJobType($job->job_type);
//             if ($job->location_type === 'remote') $tags[] = 'Remote';

//             // Salary
//             $salaryRange = null;
//             if ($job->pay_min || $job->pay_max) {
//                 $fmt = fn ($v) => is_null($v) ? null : ('$'.number_format((float)$v/1000, 0).'k');
//                 $min = $fmt($job->pay_min);
//                 $max = $fmt($job->pay_max);
//                 $salaryRange = $min && $max ? "$min - $max" : ($min ?: $max);
//             }

//             // User-related info
//             $userId = Auth::guard('sanctum')->id();
//             $hasApplied = false;
//             $isSaved = false;

//             if ($userId) {
//                 $hasApplied = DB::table('job_applications')
//                     ->where('job_id', $job->id)
//                     ->where('job_seeker_id', $userId)
//                     ->exists();

//                 $seekerId = JobSeeker::where('user_id', $userId)->value('id');
//                 if ($seekerId) {
//                     $isSaved = DB::table('saved_jobs')
//                         ->where('job_seeker_id', $seekerId)
//                         ->where('job_id', $job->id)
//                         ->exists();
//                 }
//             }

//             // Build job data
//             $data = [
//                 'id' => $job->uuid,
//                 'title' => $job->title,
//                 'company' => $company,
//                 'vacancies' => $job->vacancies,
//                 'job_type' => self::humanizeJobType($job->job_type),
//                 'salary_range' => $salaryRange,
//                 'tags' => $tags,
//                 'is_featured' => (bool) $isFeatured,
//                 'is_new' => (bool) $isNew,
//                 'posted_at' => optional($postedAt)?->toISOString(),
//                 'closed_at' => optional($closedAt)?->toISOString(),
//                 'description' => (string) $job->description,
//                 'overview' => $this->generateOverviewFromDescription($job->description),
//                 'requirements' => $this->generateRequirementsFromDescription($job->description),
//                 'responsibilities' => $this->generateResponsibilitiesFromDescription($job->description),
//                 'benefits' => $benefits,
//                 'application_link' => $company['website'] ?: null,
//                 'has_applied' => $hasApplied,
//                 'is_saved' => $isSaved,
//             ];

//             // Example lists
//             $benefitsList = DB::table('job_benefits')->pluck('name')->all();
//             $categories = DB::table('categories')->pluck('name')->all();
//             $employersList = DB::table('employers')->pluck('company_name')->all();

//              $responseData = [
//                 'jobs' => $data,
//                 'benefits' => $benefitsList,
//                 'categories' => $categories,
//                 'employers' => $employersList,
//                 'pagination' => null,
//             ];

//             return response()->api($responseData);

//         } catch (\Throwable $e) {

//              return response()->api(null, true, $e->getMessage(), 500);
//         }
//     }


    public function show($jobId)
    {
        try {
            // Find job with relations
            $job = Job::with([
                'employer',
                'preferences',
                'screeningQuestions',
                'descriptions'
            ])->where('uuid', $jobId)->first();

            if (!$job) {
                return response()->api(null, true, null, 200);
            }

            // Company details
            $company = [
                'name' => optional($job->employer)->company_name,
                'location' => $job->location_type === 'remote'
                    ? 'Remote'
                    : trim(implode(', ', array_filter([$job->city, $job->state_province, $job->country_code]))),
                'website' => optional($job->employer)->website,
            ];

            // Benefits (names)
            $benefits = DB::table('job_benefit_job')
                ->join('job_benefits', 'job_benefits.id', '=', 'job_benefit_job.job_benefit_id')
                ->where('job_benefit_job.job_id', $job->id)
                ->pluck('job_benefits.name')
                ->all();

            // Dates
            $postedAt = $job->posted_at ?: $job->created_at;
            $closedAt = $job->closed_at;

            // Flags
            $isNew = $postedAt ? Carbon::parse($postedAt)->greaterThanOrEqualTo(now()->subDays(7)) : false;
            $isFeatured = ($job->pay_max && $job->pay_max >= 150000) || ($isNew && $job->job_type === 'full_time');

            // Tags
            $tags = [];
            if ($isFeatured) $tags[] = 'Featured';
            if ($job->job_type) $tags[] = self::humanizeJobType($job->job_type);
            if ($job->location_type === 'remote') $tags[] = 'Remote';

            // Salary
            $salaryRange = null;
            if ($job->pay_min || $job->pay_max) {
                $fmt = fn ($v) => is_null($v) ? null : ('$'.number_format((float)$v/1000, 0).'k');
                $min = $fmt($job->pay_min);
                $max = $fmt($job->pay_max);
                $salaryRange = $min && $max ? "$min - $max" : ($min ?: $max);
            }

            // User-related info
            $userId = Auth::guard('sanctum')->id();
            $hasApplied = false;
            $isSaved = false;

            if ($userId) {
                $hasApplied = DB::table('job_applications')
                    ->where('job_id', $job->id)
                    ->where('job_seeker_id', $userId)
                    ->exists();

                $seekerId = JobSeeker::where('user_id', $userId)->value('id');
                if ($seekerId) {
                    $isSaved = DB::table('saved_jobs')
                        ->where('job_seeker_id', $seekerId)
                        ->where('job_id', $job->id)
                        ->exists();
                }
            }

            // ✅ Group descriptions dynamically by type
            $descriptions = $job->descriptions
                ->groupBy('type')
                ->map(function ($items) {
                    return $items->pluck('content')->all();
                })
                ->toArray();

            // Build job data
            $data = [
                'id' => $job->uuid,
                'title' => $job->title,
                'company' => $company,
                'vacancies' => $job->vacancies,
                'job_type' => self::humanizeJobType($job->job_type),
                'salary_range' => $salaryRange,
                'tags' => $tags,
                'is_featured' => (bool) $isFeatured,
                'is_new' => (bool) $isNew,
                'posted_at' => optional($postedAt)?->toISOString(),
                'closed_at' => optional($closedAt)?->toISOString(),
                'descriptions' => $descriptions,
                'benefits' => $benefits,
                'application_link' => $company['website'] ?: null,
                'has_applied' => $hasApplied,
                'is_saved' => $isSaved,
            ];

            // Example lists
            $benefitsList = DB::table('job_benefits')->pluck('name')->all();
            $categories = DB::table('categories')->pluck('name')->all();
            $employersList = DB::table('employers')->pluck('company_name')->all();

            $responseData = [
                'jobs' => $data,
                'benefits' => $benefitsList,
                'categories' => $categories,
                'employers' => $employersList,
                'pagination' => null,
            ];

            return response()->api($responseData);

        } catch (\Throwable $e) {
            return response()->api(null, true, $e->getMessage(), 500);
        }
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

    private static function composeSalaryRange(?string $visibility, $min, $max, ?string $currency): ?string
    {
        if (!$visibility) { return null; }
        $fmt = function ($v) use ($currency) {
            if (is_null($v)) { return null; }
            $prefix = ($currency ?? 'USD') === 'USD' ? '$' : '';
            return $prefix.number_format((float)$v/1000, 0).'k';
        };
        $minF = $fmt($min);
        $maxF = $fmt($max);

        return match ($visibility) {
            'range' => $minF && $maxF ? "$minF - $maxF" : ($minF ?: $maxF),
            'exact' => $maxF ?: $minF,
            'starting_at' => $minF ? ($minF.'+') : null,
            default => null,
        };
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

    /**
     * Parse salary range from frontend format like "$50k - $80k", "$180k+"
     */

   protected function parseSalaryRange(string $label): ?array
    {
        $s = strtolower($label);
        $s = str_replace(['$', ','], '', $s);
        $s = preg_replace('/\s+/', ' ', trim($s));

        if (preg_match('/^(\d+)\s*k\s*-\s*(\d+)\s*k$/', $s, $m)) {
            return ['min' => (int)$m[1] * 1000, 'max' => (int)$m[2] * 1000];
        }
        if (preg_match('/^(\d+)\s*k\s*\+$/', $s, $m)) {
            return ['min' => (int)$m[1] * 1000, 'max' => null];
        }
        if (preg_match('/^(?:up to|<=?|≤)\s*(\d+)\s*k$/', $s, $m)) {
            return ['min' => null, 'max' => (int)$m[1] * 1000];
        }
        if (preg_match('/^(\d+)\s*k$/', $s, $m)) {
            $v = (int)$m[1] * 1000;
            return ['min' => $v, 'max' => $v];
        }
        return null;
    }

    public function statsHero(Request $request)
    {
        // By default count only published jobs; override with ?published=0 to count all
        $onlyPublished = filter_var($request->query('published', '1'), FILTER_VALIDATE_BOOLEAN);

        $jobQuery = Job::query();
        if ($onlyPublished) {
            $jobQuery->where('status', 'published');
        }

        $totalJobs       = $jobQuery->count();
        $totalSeekers    = User::where('role', 'seeker')->count();
        $totalEmployers  = User::where('role', 'employer')->count();

        // Distinct employers that have at least one (published) job — "Companies Hiring"
        $companiesHiring = Job::when($onlyPublished, fn($q) => $q->where('status', 'published'))
            ->whereNotNull('employer_id')
            ->distinct('employer_id')
            ->count('employer_id');

        return response()->json([
            'total_jobs'       => $totalJobs,
            'total_seekers'    => $totalSeekers,
            'total_employers'  => $totalEmployers,
            'companies_hiring' => $companiesHiring,
        ]);
    }

     public function getSavedJobs(Request $request)
    {
        $perPage = max(1, (int) $request->query('per_page', 20));

        $userId = Auth::guard('sanctum')->id();
        if (!$userId) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        // Get job_seeker_id from job_seekers table
        $seekerId = \App\Models\JobSeeker::where('user_id', $userId)->value('id');
        if (!$seekerId) {
            return response()->json([
                'data' => [],
                'pagination' => [
                    'current_page' => 1,
                    'total_pages'  => 0,
                    'total_jobs'   => 0,
                ]
            ]);
        }

        // Fetch saved jobs
        $query = Job::query()
            ->select([
                'jobs.*',
                'employers.company_name as company_name',
                'employers.website as employer_website',
                'categories.name as category_name',
            ])
            ->leftJoin('employers', 'employers.id', '=', 'jobs.employer_id')
            ->leftJoin('categories', 'categories.id', '=', 'jobs.category_id')
            ->join('saved_jobs as sj', 'sj.job_id', '=', 'jobs.id')
            ->where('sj.job_seeker_id', $seekerId)
            ->where('jobs.status', 'published')
            ->orderBy('sj.created_at', 'desc');

        $paginator = $query->paginate($perPage);

        $jobIds = collect($paginator->items())->pluck('id')->all();

        // Benefits for all saved jobs
        $benefitsByJob = collect();
        if ($jobIds) {
            $benefitsByJob = DB::table('job_benefit_job as jb')
                ->join('job_benefits as b', 'b.id', '=', 'jb.job_benefit_id')
                ->whereIn('jb.job_id', $jobIds)
                ->orderBy('b.name')
                ->get(['jb.job_id', 'b.name'])
                ->groupBy('job_id')
                ->map(fn($rows) => $rows->pluck('name')->all());
        }
        // Applications for these jobs
        $appByJob = collect();
        if ($jobIds) {
            $appByJob = DB::table('job_applications as ja')
                ->select('ja.job_id', 'ja.job_seeker_id')
                ->where('ja.job_seeker_id', $userId)
                ->whereIn('ja.job_id', $jobIds)
                ->get()
                ->keyBy('job_id');
        }
        // Shape response
        $data = collect($paginator->items())->map(function ($row) use ($benefitsByJob, $appByJob) {
            $postedAt = $row->posted_at ?: $row->created_at;
            $closedAt = $row->closed_at;
            $isNew    = $postedAt ? Carbon::parse($postedAt)->greaterThanOrEqualTo(now()->subDays(7)) : false;
            $isFeat   = ($row->pay_max && $row->pay_max >= 150000) || ($isNew && $row->job_type === 'full_time');

            $tags = [];
            if ($isFeat) $tags[] = 'Featured';
            if ($row->job_type) $tags[] = self::humanizeJobType($row->job_type);
            if ($row->location_type === 'remote') $tags[] = 'Remote';

            $salaryRange = null;
            if ($row->pay_min || $row->pay_max) {
                $fmt = fn ($v) => is_null($v) ? null : ('$' . number_format((float)$v / 1000, 0) . 'k');
                $min = $fmt($row->pay_min);
                $max = $fmt($row->pay_max);
                $salaryRange = $min && $max ? "$min - $max" : ($min ?: $max);
            }

            $company = [
                'name'     => $row->company_name ?? 'Unknown Company',
                'location' => $row->location_type === 'remote'
                    ? 'Remote'
                    : (trim(implode(', ', array_filter([$row->city, $row->state_province, $row->country_code]))) ?: $row->country_code),
                'website'  => $row->employer_website,
            ];

            $benef = $benefitsByJob->get($row->id, []);
            $app   = $appByJob->get($row->id);

            return [
                'id'              => $row->uuid,
                'title'           => $row->title,
                'company'         => $company,
                'vacancies'       => $row->vacancies,
                'job_type'        => self::humanizeJobType($row->job_type),
                'salary_range'    => $salaryRange,
                'tags'            => $tags,
                'is_featured'     => (bool) $isFeat,
                'is_new'          => (bool) $isNew,
                'posted_at'       => optional($postedAt)->toISOString() ?? null,
                'closed_at'       => optional($closedAt)->toISOString() ?? null,
                'description'     => (string) $row->description,
                'overview'        => $this->generateOverviewFromDescription($row->description),
                'requirements'    => $this->generateRequirementsFromDescription($row->description),
                'responsibilities'=> $this->generateResponsibilitiesFromDescription($row->description),
                'benefits'        => $benef,
                'application_link'=> $company['website'] ?: null,
                'is_saved'        => true,  // always true here
                'has_applied'     => (bool) $app, // ✅ added like index()
            ];
        })->all();

        return response()->json([
            'data' => $data,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'total_pages'  => $paginator->lastPage(),
                'total_jobs'   => $paginator->total(),
            ],
        ]);
    }

    public function getJobNames()
    {
        try {
            $jobs = Job::query()
                ->select([
                    'jobs.title',
                    'jobs.uuid',
                    'employers.company_name',
                ])
                ->leftJoin('employers', 'employers.id', '=', 'jobs.employer_id')
                ->where('jobs.status', 'published')
                ->get();

            if ($jobs->isEmpty()) {
                return response()->api(null, true, 'No jobs found', 200);
            }

            $data = [
                'jobs' => $jobs,
            ];

            return response()->api($data); // ✅ success response
        } catch (\Throwable $e) {
            return response()->api(null, true, $e->getMessage(), 500); // ✅ error response
        }
    }



}


