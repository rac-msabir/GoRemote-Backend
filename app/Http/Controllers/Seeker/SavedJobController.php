<?php

namespace App\Http\Controllers\Seeker;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\JobSeeker;
use Illuminate\Http\Request;

class SavedJobController extends Controller
{
    public function store(Request $request, Job $job)
    {
        $seeker = JobSeeker::firstOrCreate(['user_id' => $request->user()->id]);
        $seeker->savedJobs()->syncWithoutDetaching([$job->id => ['created_at' => now()]]);
        return response()->json(['saved' => true]);
    }

    public function destroy(Request $request, Job $job)
    {
        $seeker = JobSeeker::firstOrCreate(['user_id' => $request->user()->id]);
        $seeker->savedJobs()->detach($job->id);
        return response()->json(['saved' => false]);
    }
}


