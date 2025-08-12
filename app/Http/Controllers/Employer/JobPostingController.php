<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\Employer;
use App\Models\Job;
use App\Models\JobPreference;
use Illuminate\Http\Request;

class JobPostingController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required','string','max:191'],
            'location_type' => ['required','in:on_site,hybrid,remote'],
            'city' => ['nullable','string','max:120'],
            'state_province' => ['nullable','string','max:120'],
            'country_code' => ['nullable','string','size:2'],
            'job_type' => ['required','in:full_time,part_time,temporary,contract,internship,fresher'],
            'pay_visibility' => ['nullable','in:range,exact,starting_at'],
            'pay_min' => ['nullable','numeric'],
            'pay_max' => ['nullable','numeric'],
            'pay_period' => ['nullable','in:hour,day,week,month,year'],
            'description' => ['required','string'],
        ]);
        $employer = Employer::firstOrCreate([
            'id' => optional($request->user()->employerUser)->employer_id
        ], [
            'company_name' => $request->user()->name . ' Company',
        ]);
        $job = $employer->jobs()->create($validated);
        $job->status = 'draft';
        $job->save();
        return response()->json($job, 201);
    }

    public function update(Request $request, Job $job)
    {
        $validated = $request->validate([
            'title' => ['sometimes','string','max:191'],
            'location_type' => ['sometimes','in:on_site,hybrid,remote'],
            'city' => ['nullable','string','max:120'],
            'state_province' => ['nullable','string','max:120'],
            'country_code' => ['nullable','string','size:2'],
            'job_type' => ['sometimes','in:full_time,part_time,temporary,contract,internship,fresher'],
            'pay_visibility' => ['nullable','in:range,exact,starting_at'],
            'pay_min' => ['nullable','numeric'],
            'pay_max' => ['nullable','numeric'],
            'pay_period' => ['nullable','in:hour,day,week,month,year'],
            'description' => ['sometimes','string'],
        ]);
        $job->fill($validated)->save();
        return response()->json($job);
    }

    public function publish(Request $request, Job $job)
    {
        $job->status = 'published';
        $job->posted_at = now();
        $job->save();
        return response()->json($job);
    }

    public function close(Request $request, Job $job)
    {
        $job->status = 'closed';
        $job->closed_at = now();
        $job->save();
        return response()->json($job);
    }
}


