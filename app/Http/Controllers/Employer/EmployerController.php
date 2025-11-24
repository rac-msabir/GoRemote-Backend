<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Employer;

class EmployerController extends Controller
{

    public function index(Request $request)
    {
        try {
            // Best practice: eager load jobs_count for fast listing
            $employers = Employer::withCount('jobs')
                ->orderBy('created_at', 'desc')
                ->get();

            $data = [
                'message'   => 'Employers fetched successfully',
                'employers' => $employers,   // full employer records + jobs_count
            ];

            return response()->api($data);

        } catch (\Throwable $e) {
            return response()->api(null, true, $e->getMessage(), 500);
        }
    }

   public function employerListDetail(Request $request, Employer $employer)
    {
        try {
            // Get full employer record + all job columns
            $employer = Employer::with([
                'jobs' => function ($query) {
                    $query->latest('posted_at'); // sirf order, koi select nahi
                },
            ])->findOrFail($employer->id);

            $data = [
                'message'  => 'Employer and jobs fetched successfully',
                'employer' => $employer, // pura model with all attributes + jobs relation
            ];

            return response()->api($data);

        } catch (\Throwable $e) {
            return response()->api(null, true, $e->getMessage(), 500);
        }
    }

}
