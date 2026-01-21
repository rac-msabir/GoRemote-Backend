<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserEducation;
use App\Models\UserExperience;
use App\Models\UserProject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use Smalot\PdfParser\Parser as PdfParser;

class CvParsingController extends Controller
{
    public function CvParsing(Request $request)
    {
        // ✅ Only file validation (as you asked)
        $request->validate([
            'resume' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
        ]);

        $file = $request->file('resume');

        // 1) Extract resume text
        $text = $this->extractText($file);
        if (!is_string($text) || trim($text) === '') {
            return response()->api(null, false, 'Unable to extract text from resume', 422);
        }

        // 2) Call Groq for JSON extraction
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
            return response()->api(null, false, 'Resume parsing failed', 500);
        }

        $content = $response->json('choices.0.message.content');
        if (!is_string($content) || trim($content) === '') {
            return response()->api(null, false, 'Empty parser response', 500);
        }

        $parsedJson = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($parsedJson)) {
            return response()->api([
                'raw' => $content,
                'json_error' => json_last_error_msg(),
            ], false, 'Invalid JSON from parser', 422);
        }

        // 3) Save resume file
        $fileName = Str::uuid()->toString() . '.' . $file->getClientOriginalExtension();
        $year  = now()->format('Y');
        $month = now()->format('m');
        $filePath = $file->storeAs("resumes/{$year}/{$month}", $fileName, 'public');

        // 4) Create NEW user + profile + experiences + educations + projects
        DB::beginTransaction();

        try {
            $email = isset($parsedJson['email']) && is_string($parsedJson['email'])
                ? trim($parsedJson['email'])
                : null;

            if (!$email) {
                DB::rollBack();
                return response()->api(null, false, 'Parsed resume email is missing. Cannot create user.', 422);
            }

            // If you want to block duplicates:
            if (User::where('email', $email)->exists()) {
                DB::rollBack();
                return response()->api(null, false, 'User with this email already exists.', 422);
            }

            $name = isset($parsedJson['name']) && is_string($parsedJson['name'])
                ? trim($parsedJson['name'])
                : 'New User';

            // Generate a password (you can change this behavior as needed)
            $plainPassword = Str::random(12);

            /** @var \App\Models\User $user */
            $user = User::create([
                'name'          => $name,
                'email'         => $email,
                'is_cv_parsed'  => true,
                'password'      => Hash::make($plainPassword),
            ]);

            // ---- Normalize links ----
            $linkedin = $this->normalizeUrl($parsedJson['linkedin'] ?? null);
            $website  = $this->normalizeUrl($parsedJson['website'] ?? null);
            $github   = $this->normalizeUrl($parsedJson['github'] ?? null);

            // ---- Skills (array) ----
            $skills = null;
            if (!empty($parsedJson['skills']) && is_array($parsedJson['skills'])) {
                $skills = collect($parsedJson['skills'])
                    ->map(fn($s) => is_string($s) ? trim($s) : '')
                    ->filter(fn($s) => $s !== '')
                    ->unique()
                    ->values()
                    ->all();

                if (count($skills) === 0) {
                    $skills = null;
                }
            }

            // ---- Profile upsert ----
            $profileData = [
                'phone'          => $parsedJson['phone'] ?? null,
                'linkedin_url'   => $linkedin,
                'website'        => $website,
                'github_profile' => $github,
                'skills'         => $skills,
                'resume_path'    => $filePath,
            ];

            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                $profileData
            );

            // ---- Experiences: create ----
            foreach (($parsedJson['experience'] ?? []) as $exp) {
                if (!is_array($exp)) continue;

                $company = $exp['company'] ?? null;
                $title   = $exp['title'] ?? null;
                $desc    = $exp['description'] ?? null;

                if (empty($company) && empty($title) && empty($desc)) {
                    continue;
                }

                // Supports BOTH formats:
                // 1) start_date/end_date (old)
                // 2) start_month/start_year/end_month/end_year (your new response)
                $startDate = $this->parseAnyDate(
                    $exp['start_date'] ?? null,
                    $exp['start_month'] ?? null,
                    $exp['start_year'] ?? null
                );

                $endRaw = $exp['end_date'] ?? null;
                $endDate = $this->parseAnyDate(
                    $exp['end_date'] ?? null,
                    $exp['end_month'] ?? null,
                    $exp['end_year'] ?? null
                );

                $isCurrent = $this->toBool($exp['is_current'] ?? null);

                // If they send "Present"/current, keep end null
                if ($this->looksLikePresent($endRaw)) {
                    $isCurrent = true;
                    $endDate = null;
                }

                UserExperience::create([
                    'user_id'      => $user->id,
                    'company_name' => $company,
                    'job_title'    => $title,
                    'is_current'   => $isCurrent ? 1 : 0,
                    'start_date'   => $startDate,
                    'end_date'     => $isCurrent ? null : $endDate,
                    'description'  => $desc,
                    'location'     => null,
                ]);
            }

            // ---- Educations: create ----
            foreach (($parsedJson['education'] ?? []) as $edu) {
                if (!is_array($edu)) continue;

                $degree = $edu['degree'] ?? null;
                $inst   = $edu['institution'] ?? null;

                if (empty($degree) && empty($inst)) {
                    continue;
                }

                $startDate = $this->parseAnyDate(
                    $edu['start_date'] ?? null,
                    $edu['start_month'] ?? null,
                    $edu['start_year'] ?? null
                );

                $endRaw = $edu['end_date'] ?? null;
                $endDate = $this->parseAnyDate(
                    $edu['end_date'] ?? null,
                    $edu['end_month'] ?? null,
                    $edu['end_year'] ?? null
                );

                $isCurrent = $this->toBool($edu['is_current'] ?? null);

                if ($this->looksLikePresent($endRaw)) {
                    $isCurrent = true;
                    $endDate = null;
                }

                UserEducation::create([
                    'user_id'      => $user->id,
                    'degree_title' => $degree,
                    'institution'  => $inst,
                    'is_current'   => $isCurrent ? 1 : 0,
                    'start_date'   => $startDate,
                    'end_date'     => $isCurrent ? null : $endDate,
                    'description'  => null,
                    'gpa'          => null,
                ]);
            }

            // ---- Projects: create ----
            foreach (($parsedJson['projects'] ?? []) as $proj) {
                if (!is_array($proj)) continue;

                $title = $proj['title'] ?? null;
                $desc  = $proj['description'] ?? null;

                // Some parsers return "Live Demo" (not a URL). We'll only normalize if it looks like a URL.
                $live  = $this->normalizeUrl($proj['live_url'] ?? null);
                $gh    = $this->normalizeUrl($proj['github_url'] ?? null);

                if (empty($title) && empty($desc) && empty($live) && empty($gh)) {
                    continue;
                }

                UserProject::create([
                    'user_id'     => $user->id,
                    'title'       => $title,
                    'description' => $desc,
                    'live_url'    => $live,
                    'github_url'  => $gh,
                ]);
            }

            DB::commit();

            $user->load(['profile', 'experiences', 'educations', 'projects']);

            return response()->api([
                'parsed' => $parsedJson,
                'user'   => $user,
                // optional: return generated password so you can show it once / email it, etc.
                'generated_password' => $plainPassword,
            ], true, 'Resume parsed and new user created successfully', 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->api([
                'error' => $e->getMessage(),
            ], false, 'Failed to create user from resume', 500);
        }
    }

    private function extractText($file)
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'pdf') {
            $parser = new PdfParser();
            $pdf = $parser->parseFile($file->getPathname());
            return trim($pdf->getText());
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

        // If you want to support .doc too, you need a different approach/library.
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
  \"linkedin\": \"\",
  \"github\": \"\",
  \"website\": \"\",
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
      \"live_url\": \"\",
      \"github_url\": \"\"
    }
  ]
}";
    }

    private function normalizeUrl(?string $url): ?string
    {
        $url = is_string($url) ? trim($url) : null;
        if (empty($url)) return null;

        // If parser returns labels like "Live Demo", ignore
        if (!preg_match('#\.[a-z]{2,}#i', $url) && !str_starts_with(strtolower($url), 'http')) {
            return null;
        }

        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }

        return $url;
    }

    private function looksLikePresent($value): bool
    {
        if (!is_string($value)) return false;
        $v = strtolower(trim($value));
        return str_contains($v, 'present') || str_contains($v, 'current') || str_contains($v, 'now');
    }

    private function toBool($value): bool
    {
        // Handles: true/false, "true"/"false", "1"/"0", 1/0, "yes"/"no"
        if (is_bool($value)) return $value;
        if (is_int($value)) return $value === 1;
        if (!is_string($value)) return false;

        $v = strtolower(trim($value));
        return in_array($v, ['1', 'true', 'yes', 'y'], true);
    }

    /**
     * Supports:
     * - "12/2022"  => 2022-12-01
     * - "2022-12-01" => same
     * - month+year ("Aug", "2024") => 2024-08-01
     * - if missing => null
     */
    private function parseAnyDate($dateString = null, $month = null, $year = null): ?string
    {
        // 1) If we already have start_date/end_date
        if (is_string($dateString)) {
            $v = trim($dateString);
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
        }

        // 2) month + year fields (your new response)
        $y = is_string($year) ? trim($year) : (is_int($year) ? (string)$year : '');
        if ($y === '' || !preg_match('#^(19|20)\d{2}$#', $y)) {
            return null;
        }

        $m = is_string($month) ? trim($month) : (is_int($month) ? (string)$month : '');
        if ($m === '') {
            // If only year exists, default to Jan
            return "{$y}-01-01";
        }

        // Allow: Aug / August / 8 / 08
        $monthMap = [
            'jan' => '01', 'january' => '01',
            'feb' => '02', 'february' => '02',
            'mar' => '03', 'march' => '03',
            'apr' => '04', 'april' => '04',
            'may' => '05',
            'jun' => '06', 'june' => '06',
            'jul' => '07', 'july' => '07',
            'aug' => '08', 'august' => '08',
            'sep' => '09', 'sept' => '09', 'september' => '09',
            'oct' => '10', 'october' => '10',
            'nov' => '11', 'november' => '11',
            'dec' => '12', 'december' => '12',
        ];

        $lower = strtolower($m);

        if (preg_match('#^(0?[1-9]|1[0-2])$#', $lower)) {
            $mm = str_pad($lower, 2, '0', STR_PAD_LEFT);
            return "{$y}-{$mm}-01";
        }

        if (isset($monthMap[$lower])) {
            return "{$y}-{$monthMap[$lower]}-01";
        }

        // Unknown month string
        return "{$y}-01-01";
    }
}
