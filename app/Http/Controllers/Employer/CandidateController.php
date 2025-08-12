<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\Http\Request;

class CandidateController extends Controller
{
    public function index(Request $request)
    {
        $applications = Application::with(['job','jobSeeker.user'])
            ->latest('applied_at')
            ->paginate(20);
        return $applications;
    }

    public function updateStatus(Request $request, Application $application)
    {
        $validated = $request->validate([
            'status' => ['required','in:applied,reviewed,interviewing,offered,hired,rejected,withdrawn'],
        ]);
        $application->status = $validated['status'];
        $application->updated_at = now();
        $application->save();
        return response()->json($application);
    }
}


