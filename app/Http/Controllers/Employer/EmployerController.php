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
            // $employer is already fetched by uuid here

            $employer->load([
                'jobs' => function ($query) {
                    $query->latest('posted_at');
                },
            ]);

            $data = [
                'message'  => 'Employer and jobs fetched successfully',
                'employer' => $employer,
            ];

            return response()->api($data);

        } catch (\Throwable $e) {
            return response()->api(null, true, $e->getMessage(), 500);
        }
    }

}
