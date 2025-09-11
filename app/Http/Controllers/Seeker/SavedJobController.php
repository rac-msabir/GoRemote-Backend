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

class SavedJobController extends Controller
{
    public function store(Request $request, Job $job)
    {
        $seeker = JobSeeker::firstOrCreate(['user_id' => $request->user()->id]);
        $seeker->savedJobs()->syncWithoutDetaching([$job->id => ['created_at' => now()]]);
        return response()->json([
            'message' => 'Job saved successfully',
            'saved' => true,
        ], 200);
    }

    public function destroy(Request $request, Job $job)
    {
        $seeker = JobSeeker::firstOrCreate(['user_id' => $request->user()->id]);
        $seeker->savedJobs()->detach($job->id);
        return response()->json([
            'message' => 'Job unsaved successfully',
            'saved' => false,
        ], 200);
    }
}


