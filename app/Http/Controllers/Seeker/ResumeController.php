<?php

namespace App\Http\Controllers\Seeker;

use App\Http\Controllers\Controller;
use App\Models\JobSeeker;
use App\Models\Resume;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ResumeController extends Controller
{
    public function index(Request $request)
    {
        $seeker = JobSeeker::firstOrCreate(['user_id' => $request->user()->id]);
        return $seeker->resumes()->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'file' => ['required','file','mimes:pdf,doc,docx','max:5120'],
            'is_public' => ['boolean'],
        ]);
        $seeker = JobSeeker::firstOrCreate(['user_id' => $request->user()->id]);
        $file = $validated['file'];
        $path = $file->store('resumes', 'public');
        $resume = $seeker->resumes()->create([
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'is_public' => (bool)($validated['is_public'] ?? false),
        ]);
        return response()->json($resume, 201);
    }
}


