<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobSeeker extends Model
{
    /** @use HasFactory<\Database\Factories\JobSeekerFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'city',
        'state_province',
        'postal_code',
        'country_code',
        'remote_preference',
        'min_base_pay',
        'min_pay_period',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function desiredTitles()
    {
        return $this->hasMany(SeekerDesiredTitle::class);
    }

    public function resumes()
    {
        return $this->hasMany(Resume::class);
    }

    public function savedJobs()
    {
        return $this->belongsToMany(Job::class, 'saved_jobs', 'job_seeker_id', 'job_id');
    }
}
