<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\Http\Request;

class CandidateController extends Controller
{
    public function index(Request $request)
    {
        $companyId = optional($request->user()->companyUser)->company_id;
        if (!$companyId) {
            return response()->json(['message' => 'No company associated with this account'], 403);
        }

        $applications = Application::with(['job','jobSeeker.user'])
            ->whereHas('job', fn($q) => $q->where('company_id', $companyId))
            ->latest('applied_at')
            ->paginate(20);

        return response()->json([
            'message' => 'Candidates retrieved successfully',
            'applications' => $applications
        ]);
    }

    public function updateStatus(Request $request, Application $application)
    {
        $companyId = optional($request->user()->companyUser)->company_id;
        if (!$companyId || optional($application->job)->company_id !== $companyId) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'status' => ['required','in:applied,reviewed,interviewing,offered,hired,rejected,withdrawn'],
        ]);

        $application->status = $validated['status'];
        $application->updated_at = now();
        $application->save();

        return response()->json([
            'message' => 'Application status updated successfully',
            'application' => $application
        ]);
    }
}
