<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Job;
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

        $companyId = optional($request->user()->companyUser)->company_id;
        if (!$companyId) {
            return response()->json(['message' => 'No company associated with this account'], 403);
        }

        $job = new Job($validated);
        $job->company_id = $companyId;
        $job->status = 'draft';
        $job->save();

        return response()->json([
            'message' => 'Job created successfully',
            'job' => $job
        ], 201);
    }

    public function update(Request $request, Job $job)
    {
        $companyId = optional($request->user()->companyUser)->company_id;
        if (!$companyId || $job->company_id !== $companyId) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

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

        return response()->json([
            'message' => 'Job updated successfully',
            'job' => $job
        ]);
    }

    public function publish(Request $request, Job $job)
    {
        $companyId = optional($request->user()->companyUser)->company_id;
        if (!$companyId || $job->company_id !== $companyId) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $job->status = 'published';
        $job->posted_at = now();
        $job->save();

        return response()->json([
            'message' => 'Job published successfully',
            'job' => $job
        ]);
    }

    public function close(Request $request, Job $job)
    {
        $companyId = optional($request->user()->companyUser)->company_id;
        if (!$companyId || $job->company_id !== $companyId) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $job->status = 'closed';
        $job->closed_at = now();
        $job->save();

        return response()->json([
            'message' => 'Job closed successfully',
            'job' => $job
        ]);
    }
}
