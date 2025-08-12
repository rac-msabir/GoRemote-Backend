<?php

namespace App\Http\Controllers;

use App\Models\Job;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class JobController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max(1, (int) $request->query('per_page', 20));

        $query = Job::query()
            ->select([
                'jobs.*',
                'employers.company_name as company_name',
                'employers.website as employer_website',
                DB::raw('COALESCE(categories.name, NULL) as category_name'),
            ])
            ->join('employers', 'employers.id', '=', 'jobs.employer_id')
            ->leftJoin('categories', 'categories.id', '=', 'jobs.category_id')
            ->where('jobs.status', 'published');

        // Filters
        // search
        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('jobs.title', 'like', "%$search%")
                  ->orWhere('jobs.description', 'like', "%$search%");
            });
        }

        // categories (slugs) - category, categories[], or CSV categories
        $categorySlugs = [];
        if ($slug = $request->string('category')->toString()) { $categorySlugs[] = $slug; }
        $catInput = $request->input('categories', []);
        if (is_string($catInput)) {
            $catInput = array_filter(array_map('trim', explode(',', $catInput)));
        }
        $categorySlugs = array_merge($categorySlugs, is_array($catInput) ? $catInput : []);
        if (!empty($categorySlugs)) {
            $slugs = array_map(fn($v) => Str::slug($v), $categorySlugs);
            $names = $categorySlugs;
            $query->whereIn('jobs.category_id', function($sub) use ($slugs, $names) {
                $sub->select('id')
                    ->from('categories')
                    ->where(function ($qq) use ($slugs, $names) {
                        $qq->whereIn('slug', $slugs)
                           ->orWhereIn('name', $names);
                    });
            });
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
        $salaryMin = $request->query('salary_min');
        $salaryMax = $request->query('salary_max');
        if (is_numeric($salaryMin)) {
            $query->where(function ($q) use ($salaryMin) {
                $q->whereNotNull('jobs.pay_max')
                  ->where('jobs.pay_max', '>=', (float) $salaryMin);
            });
        }
        if (is_numeric($salaryMax)) {
            $query->where(function ($q) use ($salaryMax) {
                $q->whereNotNull('jobs.pay_min')
                  ->where('jobs.pay_min', '<=', (float) $salaryMax);
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

        // salary ranges - accept salary_min/salary_max OR salary_ranges[]/salary_range (strings like "$10,000 - $25,000 USD")
        $applySalary = false;
        $salaryMin = $request->query('salary_min');
        $salaryMax = $request->query('salary_max');
        $ranges = $request->input('salary_ranges', []);
        if ($single = $request->input('salary_range')) { $ranges = array_merge((array) $ranges, (array) $single); }
        if (!empty($ranges) && is_string($ranges)) { $ranges = [$ranges]; }

        // Parse human range labels into numeric pairs
        $parsedRanges = [];
        foreach ((array) $ranges as $label) {
            if (!is_string($label)) { continue; }
            // Extract two numbers; ignore currency and commas
            if (preg_match_all('/(\d[\d,]*)/', $label, $m) && count($m[1]) >= 1) {
                $min = isset($m[1][0]) ? (float) str_replace(',', '', $m[1][0]) : null;
                $max = isset($m[1][1]) ? (float) str_replace(',', '', $m[1][1]) : null;
                if (!is_null($min) || !is_null($max)) {
                    $parsedRanges[] = [$min, $max];
                }
            }
        }

        if (is_numeric($salaryMin) || is_numeric($salaryMax) || !empty($parsedRanges)) {
            $query->where(function ($q) use ($salaryMin, $salaryMax, $parsedRanges) {
                $clausesAdded = false;
                if (is_numeric($salaryMin)) {
                    $q->where(function ($qq) use ($salaryMin) {
                        $qq->whereNotNull('jobs.pay_max')->where('jobs.pay_max', '>=', (float) $salaryMin);
                    });
                    $clausesAdded = true;
                }
                if (is_numeric($salaryMax)) {
                    $q->where(function ($qq) use ($salaryMax) {
                        $qq->whereNotNull('jobs.pay_min')->where('jobs.pay_min', '<=', (float) $salaryMax);
                    });
                    $clausesAdded = true;
                }
                if (!empty($parsedRanges)) {
                    $q->where(function ($qq) use ($parsedRanges) {
                        foreach ($parsedRanges as [$min, $max]) {
                            $qq->orWhere(function ($or) use ($min, $max) {
                                if (!is_null($min)) { $or->where('jobs.pay_max', '>=', $min); }
                                if (!is_null($max)) { $or->where('jobs.pay_min', '<=', $max); }
                            });
                        }
                    });
                }
            });
        }

        // posted_within
        $postedWithin = $request->string('posted_within')->toString();
        $withinDays = match ($postedWithin) {
            '24h' => 1,
            '3d' => 3,
            '7d' => 7,
            '14d', '2w', '2weeks' => 14,
            '30d' => 30,
            'any', '' => null,
            default => null,
        };
        if ($withinDays) {
            $query->where('jobs.posted_at', '>=', now()->subDays($withinDays));
        }

        // Sorting
        $sort = $request->string('sort')->toString();
        if ($sort === 'oldest') {
            $query->orderBy('jobs.posted_at', 'asc');
        } else {
            $query->orderBy('jobs.posted_at', 'desc'); // default newest
        }

        $paginator = $query->paginate($perPage);

        $data = collect($paginator->items())->map(function ($job) {
            $postedAt = $job->posted_at ?: $job->created_at;
            $isNew = $postedAt ? Carbon::parse($postedAt)->greaterThanOrEqualTo(now()->subDays(7)) : false;
            $isFeatured = ($job->pay_max && $job->pay_max >= 150000) || ($isNew && $job->job_type === 'full_time');

            $location = $job->location_type === 'remote'
                ? 'Remote'
                : trim(implode(', ', array_filter([$job->city, $job->state_province, $job->country_code])));

            $tags = [];
            if ($isFeatured) { $tags[] = 'Featured'; }
            if ($job->job_type) { $tags[] = self::humanizeJobType($job->job_type); }
            if ($job->location_type === 'remote') { $tags[] = 'Remote'; }

            $salaryRange = self::composeSalaryRange(
                $job->pay_visibility,
                $job->pay_min,
                $job->pay_max,
                $job->currency,
            );

            return [
                'id' => (int) $job->id,
                'title' => $job->title,
                'company_name' => $job->company_name,
                'location' => $location ?: 'Anywhere in the World',
                'tags' => $tags,
                'job_type' => self::humanizeJobType($job->job_type),
                'category_name' => $job->category_name,
                'salary_range' => $salaryRange,
                'is_featured' => (bool) $isFeatured,
                'is_new' => (bool) $isNew,
                'created_at' => optional($postedAt)->toISOString() ?? null,
            ];
        })->all();

        $response = [
            'data' => $data,
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

        $response = [
            'id' => (int) $job->id,
            'title' => $job->title,
            'company' => $company,
            'job_type' => self::humanizeJobType($job->job_type),
            'salary_range' => $salaryRange,
            'tags' => $tags,
            'is_featured' => (bool) $isFeatured,
            'is_new' => (bool) $isNew,
            'posted_at' => optional($postedAt)->toISOString() ?? null,
            'description' => (string) $job->description,
            'requirements' => $this->generateRequirementsFromDescription($job->description),
            'responsibilities' => $this->generateResponsibilitiesFromDescription($job->description),
            'benefits' => $benefits,
            'application_link' => $company['website'] ?: null,
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
}


