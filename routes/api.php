<?php

use Illuminate\Support\Facades\Route;

// Auth
Route::post('/auth/register', [\App\Http\Controllers\AuthController::class, 'register']);
Route::post('/auth/login', [\App\Http\Controllers\AuthController::class, 'login']);
Route::post('/auth/logout', [\App\Http\Controllers\AuthController::class, 'logout'])->middleware('auth:sanctum');

// Onboarding - Job Seeker
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [\App\Http\Controllers\AuthController::class, 'me']);
    Route::put('/seeker/location', [\App\Http\Controllers\Seeker\OnboardingController::class, 'updateLocation']);
    Route::put('/seeker/min-pay', [\App\Http\Controllers\Seeker\OnboardingController::class, 'updateMinPay']);
    Route::put('/seeker/titles', [\App\Http\Controllers\Seeker\OnboardingController::class, 'updateDesiredTitles']);
    Route::post('/seeker/resumes', [\App\Http\Controllers\Seeker\ResumeController::class, 'store']);
    Route::get('/seeker/resumes', [\App\Http\Controllers\Seeker\ResumeController::class, 'index']);
});

// Jobs browsing (public + authed)
Route::get('/jobs', [\App\Http\Controllers\JobController::class, 'index']);
Route::get('/jobs/filter', [\App\Http\Controllers\JobController::class, 'index']);
Route::get('/jobs/{job}', [\App\Http\Controllers\JobController::class, 'show']);
Route::post('/jobs/{job}/save', [\App\Http\Controllers\Seeker\SavedJobController::class, 'store'])->middleware('auth:sanctum');
Route::delete('/jobs/{job}/save', [\App\Http\Controllers\Seeker\SavedJobController::class, 'destroy'])->middleware('auth:sanctum');
Route::post('/jobs/{job}/apply', [\App\Http\Controllers\ApplicationController::class, 'apply'])->middleware('auth:sanctum');

// Employers
Route::middleware(['auth:sanctum', 'role:employer'])->group(function () {
    Route::post('/employers/jobs', [\App\Http\Controllers\Employer\JobPostingController::class, 'store']);
    Route::put('/employers/jobs/{job}', [\App\Http\Controllers\Employer\JobPostingController::class, 'update']);
    Route::post('/employers/jobs/{job}/publish', [\App\Http\Controllers\Employer\JobPostingController::class, 'publish']);
    Route::post('/employers/jobs/{job}/close', [\App\Http\Controllers\Employer\JobPostingController::class, 'close']);
    Route::get('/employers/candidates', [\App\Http\Controllers\Employer\CandidateController::class, 'index']);
    Route::put('/applications/{application}/status', [\App\Http\Controllers\Employer\CandidateController::class, 'updateStatus']);
});

// Companies
Route::get('/companies', [\App\Http\Controllers\CompanyController::class, 'index']);
Route::get('/companies/{company}', [\App\Http\Controllers\CompanyController::class, 'show']);
Route::get('/companies/{company}/reviews', [\App\Http\Controllers\CompanyController::class, 'reviews']);
Route::get('/companies/{company}/salaries', [\App\Http\Controllers\CompanyController::class, 'salaries']);


