<?php

namespace App\Http\Controllers;

use App\Models\Job;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use App\Models\JobSeeker;

class JobController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max(1, (int) $request->query('per_page', 20));
        $benefits = DB::table('job_benefits')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
        $categories = DB::table('categories')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
        $employers = DB::table('employers')
            ->select('id', 'company_name')
            ->orderBy('company_name')
            ->get();

        $query = Job::query()
            ->select([
                'jobs.*',
                'employers.company_name as company_name',
                'employers.website as employer_website',
                'categories.name as category_name', // null when no category
            ])
            ->leftJoin('employers', 'employers.id', '=', 'jobs.employer_id') // was INNER JOIN
            ->leftJoin('categories', 'categories.id', '=', 'jobs.category_id')
            ->where('jobs.status', 'published');

        // Filters
        $expRaw = $request->input('experiencelevel');
        if ($expRaw) {
           
            $label = Str::lower(trim($expRaw));
            $pattern = null;

            // Build MySQL REGEXP patterns against LOWER(jobs.description)
            if (Str::startsWith($label, '0-1')) {
                // 0 or 1 year, entry level, fresher, no experience
                $pattern = implode('|', [
                    '\b0-1\s*years?\b',
                    '\b(?:0|zero|1|one)\s*(?:\+?\s*)?(?:years?|yrs?)\b',
                    '\bno\s+experience\b',
                    '\bentry[-\s]?level\b',
                    '\bfresh(?:er)?\b',
                ]);
            } elseif (Str::startsWith($label, '2+')) {
                // >= 2 years
                $pattern = implode('|', [
                    '\b(?:(?:[2-9]|[1-9][0-9]+))\s*\+?\s*(?:years?|yrs?)\b',
                    '\b(?:two|three|four|five|six|seven|eight|nine|ten)\s*\+?\s*(?:years?|yrs?)\b',
                    '\b(?:at\s+least|min(?:imum)?)\s*(?:2|two)\s*(?:years?|yrs?)\b',
                ]);
            } elseif (Str::startsWith($label, '3+')) {
                // >= 3 years
                $pattern = implode('|', [
                    '\b(?:(?:[3-9]|[1-9][0-9]+))\s*\+?\s*(?:years?|yrs?)\b',
                    '\b(?:three|four|five|six|seven|eight|nine|ten)\s*\+?\s*(?:years?|yrs?)\b',
                    '\b(?:at\s+least|min(?:imum)?)\s*(?:3|three)\s*(?:years?|yrs?)\b',
                ]);
            } elseif (Str::startsWith($label, '5+')) {
                // >= 5 years
                $pattern = implode('|', [
                    '\b(?:(?:[5-9]|[1-9][0-9]+))\s*\+?\s*(?:years?|yrs?)\b',
                    '\b(?:five|six|seven|eight|nine|ten)\s*\+?\s*(?:years?|yrs?)\b',
                    '\b(?:at\s+least|min(?:imum)?)\s*(?:5|five)\s*(?:years?|yrs?)\b',
                ]);
            } elseif (Str::startsWith($label, '10+')) {
                // >= 10 years
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

        // search
        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('jobs.title', 'like', "%$search%")
                  ->orWhere('jobs.description', 'like', "%$search%");
            });
        }

        // job types
        if ($jobType = $request->string('jobtypes')->toString()) {
            $query->where(function ($q) use ($jobType) {
                $q->where('jobs.job_type', 'like', "%$jobType%");
            });
        }

        $benefitId = $request->integer('benefits');

        if ($benefitId > 0) {
            $query->whereExists(function ($sub) use ($benefitId) {
                $sub->selectRaw(1)
                    ->from('job_benefit_job as jb') 
                    ->whereColumn('jb.job_id', 'jobs.id')
                    ->where('jb.job_benefit_id', $benefitId);
            });
        }

        // categories (slugs) - category, categories[], or CSV categories
        // $categorySlugs = [];
        // if ($slug = $request->string('category')->toString()) { $categorySlugs[] = $slug; }
        // $catInput = $request->input('categories', []);
        // if (is_string($catInput)) {
        //     $catInput = array_filter(array_map('trim', explode(',', $catInput)));
        // }
        // $categorySlugs = array_merge($categorySlugs, is_array($catInput) ? $catInput : []);
        // if (!empty($categorySlugs)) {
        //     $slugs = array_map(fn($v) => Str::slug($v), $categorySlugs);
        //     $names = $categorySlugs;
        //     $query->whereIn('jobs.category_id', function($sub) use ($slugs, $names) {
        //         $sub->select('id')
        //             ->from('categories')
        //             ->where(function ($qq) use ($slugs, $names) {
        //                 $qq->whereIn('slug', $slugs)
        //                    ->orWhereIn('name', $names);
        //             });
        //     });
        // }

        $categoryId = $request->integer('category'); // returns 0 if not present/invalid

        if ($categoryId > 0) {
            $query->where('jobs.category_id', $categoryId);
        }
        

        // countries (codes or names) - country, countries[], or CSV countries
        $countries = [];
        if ($c = $request->string('country')->toString()) { $countries[] = $c; }
        $countriesInput = $request->input('countries', []);
        if (is_string($countriesInput)) {
            $countriesInput = array_filter(array_map('trim', explode(',', $countriesInput)));
        }
        $countries = array_merge($countries, is_array($countriesInput) ? $countriesInput : []);
        if (!empty($countries)) {
            $countriesUpper = array_map(fn($v) => strtoupper($v), $countries);
            $query->where(function ($q) use ($countries, $countriesUpper) {
                $q->whereIn('jobs.country_code', $countriesUpper)
                  ->orWhereIn('jobs.country_name', $countries);
            });
        }

        // Salary filtering
        $salary = $request->query('salary');
        if ($salary && is_string($salary)) {
            $query->where(function ($q) use ($salary) {
                // Parse salary range from frontend format like "$50k - $80k", "$80k - $120k", "$120k - $180k", "$180k+"
                $parsedRange = $this->parseSalaryRange($salary);
                
                if ($parsedRange) {
                    $min = $parsedRange['min'];
                    $max = $parsedRange['max'];
                    
                    if ($min && $max) {
                        // Range: $50k - $80k -> find jobs that fit COMPLETELY within this range
                        $q->where(function ($qq) use ($min, $max) {
                            $qq->whereNotNull('jobs.pay_min')
                                ->where('jobs.pay_min', '>=', $min * 1000) // Job min must be >= search min
                                ->whereNotNull('jobs.pay_max')
                                ->where('jobs.pay_max', '<=', $max * 1000); // Job max must be <= search max
                        });
                    } elseif ($min && !$max) {
                        // $180k+ -> find jobs where pay_min >= 180k
                        $q->where('jobs.pay_min', '>=', $min * 1000);
                    } elseif (!$min && $max) {
                        // Up to $80k -> find jobs where pay_max <= 80k
                        $q->where('jobs.pay_max', '<=', $max * 1000);
                    }
                }
            });
        }

        // skills by slug: skills[]= or comma-separated 'skills'
        $skillSlugs = $request->input('skills', []);
        if (is_string($skillSlugs)) {
            $skillSlugs = array_filter(array_map('trim', explode(',', $skillSlugs)));
        }
        if (!empty($skillSlugs)) {
            $slugged = array_map(fn($v) => Str::slug($v), $skillSlugs);
            $names = $skillSlugs;
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

        // posted_within
        $datePostedRaw = $request->input('dateposted');

        if ($datePostedRaw !== null && $datePostedRaw !== '' && Str::lower($datePostedRaw) !== 'any') {
            $label = Str::of($datePostedRaw)->lower()->trim();

            // Direct label/alias mapping
            $from = match ((string) $label) {
                'last 24 hours', '24h', '1d', '1 day' => now()->subDay(),
                'last 7 days', '7d'                   => now()->subDays(7),
                'last 30 days', '30d'                 => now()->subDays(30),
                'last 2 months', '2m', 'last two months' => now()->subMonthsNoOverflow(2),
                default => null,
            };

            // Fallback: parse "last {n} day(s)/month(s)"
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

        $employerId = $request->integer('company'); // returns 0 if not present/invalid
        
        if ($employerId > 0) {
            $query->where('jobs.employer_id', $employerId);
        }

        // Sorting
        $sort = $request->string('sort')->toString();
        if ($sort === 'oldest') {
            $query->orderBy('jobs.posted_at', 'asc');
        } else {
            $query->orderBy('jobs.posted_at', 'desc'); // default newest
        }

        $paginator = $query->paginate($perPage);

        //check job status if applied by auth user
            $userId = Auth::guard('sanctum')->id();
            $jobIds = collect($paginator->items())->pluck('id')->all();
            $appByJob = collect();
            if ($userId && !empty($jobIds)) {
                // Grab the latest application per job for this user
                $appByJob = DB::table('job_applications as ja')
                    ->select('ja.job_id', 'ja.job_seeker_id', 'ja.created_at')
                    ->where('ja.job_seeker_id', $userId)
                    ->whereIn('ja.job_id', $jobIds)
                    ->orderBy('ja.created_at', 'desc')
                    ->get()
                    ->groupBy('job_id')
                    ->map->first(); // keep only the latest row per job_id
            }

            //saved job
            $seekerId = JobSeeker::where('user_id', $userId)->value('id');
            $savedByJob = collect();
            if ($userId && !empty($jobIds)) {
                $savedByJob = DB::table('saved_jobs as sj')
                    ->select('sj.job_id')
                    ->where('sj.job_seeker_id', $seekerId) // <-- adjust if your FK differs
                    ->whereIn('sj.job_id', $jobIds)
                    ->get()
                    ->keyBy('job_id'); // existence = saved
            }   
        
        $data = collect($paginator->items())->map(function ($job) use ($appByJob,$savedByJob){
            // Load full job data for detailed response
            $fullJob = Job::with(['employer','preferences','screeningQuestions'])->find($job->id);
            
            // Company details
            $company = [
                'name' => optional($fullJob->employer)->company_name ?? 'Unknown Company',
                'location' => $fullJob->location_type === 'remote'
                    ? 'Remote'
                    : (trim(implode(', ', array_filter([$fullJob->city, $fullJob->state_province, $fullJob->country_code]))) ?: $fullJob->country_code),
                'website' => optional($fullJob->employer)->website,
            ];

            // Benefits (names)
            $benefits = DB::table('job_benefit_job')
                ->join('job_benefits', 'job_benefits.id', '=', 'job_benefit_job.job_benefit_id')
                ->where('job_benefit_job.job_id', $fullJob->id)
                ->pluck('job_benefits.name')
                ->all();

            $postedAt = $fullJob->posted_at ?: $fullJob->created_at;
            $closedAt = $fullJob->closed_at;
            $isNew = $postedAt ? Carbon::parse($postedAt)->greaterThanOrEqualTo(now()->subDays(7)) : false;
            $isFeatured = ($fullJob->pay_max && $fullJob->pay_max >= 150000) || ($isNew && $fullJob->job_type === 'full_time');
            
            $tags = [];
            if ($isFeatured) { $tags[] = 'Featured'; }
            if ($fullJob->job_type) { $tags[] = self::humanizeJobType($fullJob->job_type); }
            if ($fullJob->location_type === 'remote') { $tags[] = 'Remote'; }
          
            $salaryRange = null;
            if ($fullJob->pay_min || $fullJob->pay_max) {
                $fmt = fn ($v) => is_null($v) ? null : ('$'.number_format((float)$v/1000, 0).'k');
                $min = $fmt($fullJob->pay_min);
                $max = $fmt($fullJob->pay_max);
                $salaryRange = $min && $max ? "$min - $max" : ($min ?: $max);
            }
          
            $app = $appByJob->get($fullJob->id);
            $saved = $savedByJob->get($fullJob->id);
          
            return [
                'id' => (int) $fullJob->id,
                'title' => $fullJob->title,
                'company' => $company,
                'vacancies' => $fullJob->vacancies,
                'job_type' => self::humanizeJobType($fullJob->job_type),
                'salary_range' => $salaryRange,
                'tags' => $tags,
                'is_featured' => (bool) $isFeatured,
                'is_new' => (bool) $isNew,
                'posted_at' => optional($postedAt)->toISOString() ?? null,
                'closed_at' => optional($closedAt)->toISOString() ?? null,
                'description' => (string) $fullJob->description,
                'overview' => $this->generateOverviewFromDescription($fullJob->description),
                'requirements' => $this->generateRequirementsFromDescription($fullJob->description),
                'responsibilities' => $this->generateResponsibilitiesFromDescription($fullJob->description),
                'benefits' => $benefits,
                'application_link' => $company['website'] ?: null,
                'has_applied'        => (bool) $app,
                'is_saved' => (bool) $saved,
            ];
        })->all();  

        $response = [
            'data' => $data,
            'benefits' => $benefits,
            'categories' => $categories,
            'employers' => $employers,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'total_pages' => $paginator->lastPage(),
                'total_jobs' => $paginator->total(),
            ],
        ];

        return response()->json($response);
    }

   public function show(Job $job)
    {
        // Eager-load related data
        $job->load(['employer','preferences','screeningQuestions']);

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

        $postedAt = $job->posted_at ?: $job->created_at;
        $closedAt = $job->closed_at;
        $isNew = $postedAt ? Carbon::parse($postedAt)->greaterThanOrEqualTo(now()->subDays(7)) : false;
        $isFeatured = ($job->pay_max && $job->pay_max >= 150000) || ($isNew && $job->job_type === 'full_time');

        $tags = [];
        if ($isFeatured) { $tags[] = 'Featured'; }
        if ($job->job_type) { $tags[] = self::humanizeJobType($job->job_type); }
        if ($job->location_type === 'remote') { $tags[] = 'Remote'; }

        $salaryRange = null;
        if ($job->pay_min || $job->pay_max) {
            $fmt = fn ($v) => is_null($v) ? null : ('$'.number_format((float)$v/1000, 0).'k');
            $min = $fmt($job->pay_min);
            $max = $fmt($job->pay_max);
            $salaryRange = $min && $max ? "$min - $max" : ($min ?: $max);
        }

        // ---> New bits (keep consistent with index):
        $userId = Auth::guard('sanctum')->id();
        $app = false;
        $saved = false;

        if ($userId) {
            // If your job_applications table stores job_seeker_id = USER id, this matches your index() logic.
            // If it actually stores the seeker PK, switch $userId to $seekerId below.
            $app = DB::table('job_applications as ja')
                ->where('ja.job_id', $job->id)
                ->where('ja.job_seeker_id', $userId)
                ->exists();

            $seekerId = JobSeeker::where('user_id', $userId)->value('id');
            
            if ($seekerId) {
                $saved = DB::table('saved_jobs as sj')
                    ->where('sj.job_seeker_id', $seekerId)
                    ->where('sj.job_id', $job->id)
                    ->exists();
            }
        }
        // <--- end new bits

        $response = [
            'id' => (int) $job->id,
            'title' => $job->title,
            'company' => $company,
            'vacancies' => $job->vacancies,                       // NEW
            'job_type' => self::humanizeJobType($job->job_type),
            'salary_range' => $salaryRange,
            'tags' => $tags,
            'is_featured' => (bool) $isFeatured,
            'is_new' => (bool) $isNew,
            'posted_at' => optional($postedAt)->toISOString() ?? null,
            'closed_at' => optional($closedAt)->toISOString() ?? null,  // NEW
            'description' => (string) $job->description,
            'overview' => $this->generateOverviewFromDescription($job->description),
            'requirements' => $this->generateRequirementsFromDescription($job->description),
            'responsibilities' => $this->generateResponsibilitiesFromDescription($job->description),
            'benefits' => $benefits,
            'application_link' => $company['website'] ?: null,
            'has_applied' => (bool) $app,                         // NEW
            'is_saved' => (bool) $saved,                          // NEW
            
        ];

        return response()->json($response);
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
    private function parseSalaryRange(string $salary): ?array
    {
        // Remove currency symbols and spaces
        $clean = preg_replace('/[\$\s]/', '', $salary);
        
        // Handle range format: "50k-80k" or "50k - 80k"
        if (preg_match('/^(\d+)k\s*-\s*(\d+)k$/i', $clean, $matches)) {
            return [
                'min' => (int) $matches[1],
                'max' => (int) $matches[2]
            ];
        }
        
        // Handle "180k+" format
        if (preg_match('/^(\d+)k\+$/i', $clean, $matches)) {
            return [
                'min' => (int) $matches[1],
                'max' => null
            ];
        }
        
        // Handle single value: "80k"
        if (preg_match('/^(\d+)k$/i', $clean, $matches)) {
            return [
                'min' => null,
                'max' => (int) $matches[1]
            ];
        }
        
        return null;
    }
}


