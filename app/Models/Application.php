<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    /** @use HasFactory<\Database\Factories\ApplicationFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'job_seeker_id','job_id','resume_id','status','applied_at','updated_at','external_redirect','notes_internal',
    ];

    protected $casts = [
        'applied_at' => 'datetime',
        'updated_at' => 'datetime',
        'external_redirect' => 'boolean',
    ];

    public function job()
    {
        return $this->belongsTo(Job::class);
    }

    public function jobSeeker()
    {
        return $this->belongsTo(JobSeeker::class);
    }
}
