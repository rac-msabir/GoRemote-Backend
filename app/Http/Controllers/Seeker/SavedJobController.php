<?php

namespace App\Http\Controllers\Seeker;

use App\Http\Controllers\Controller;
use Auth;
use DB;
use Carbon\Carbon;
use App\Models\Job;
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

            return response()->json([
                'status_code'  => 200,
                'error'        => false,
                'errorMessage' => null,
                'data'         => [
                    'message' => 'Job saved successfully',
                    'saved'   => true,
                    'job_id'  => $job->id,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status_code'  => 500,
                'error'        => true,
                'errorMessage' => $e->getMessage(),
                'data'         => null,
            ], 500);
        }
    }

    public function destroy(Request $request, Job $job)
    {
        try {
            $seeker = JobSeeker::firstOrCreate(['user_id' => $request->user()->id]);

            $seeker->savedJobs()->detach($job->id);

            return response()->json([
                'status_code'  => 200,
                'error'        => false,
                'errorMessage' => null,
                'data'         => [
                    'message' => 'Job unsaved successfully',
                    'saved'   => false,
                    'job_id'  => $job->id,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status_code'  => 500,
                'error'        => true,
                'errorMessage' => $e->getMessage(),
                'data'         => null,
            ], 500);
        }
    }

}


