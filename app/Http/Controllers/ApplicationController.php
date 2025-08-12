<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Job;
use App\Models\JobSeeker;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    public function apply(Request $request, Job $job)
    {
        $validated = $request->validate([
            'resume_id' => ['nullable','exists:resumes,id'],
            'external_redirect' => ['boolean'],
        ]);
        $seeker = JobSeeker::firstOrCreate(['user_id' => $request->user()->id]);
        $application = Application::create([
            'job_seeker_id' => $seeker->id,
            'job_id' => $job->id,
            'resume_id' => $validated['resume_id'] ?? null,
            'status' => 'applied',
            'applied_at' => now(),
            'external_redirect' => (bool)($validated['external_redirect'] ?? false),
        ]);
        return response()->json($application->load('job'), 201);
    }
}


