<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeekerDesiredTitle extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_seeker_id','title','priority',
    ];

    public function jobSeeker()
    {
        return $this->belongsTo(JobSeeker::class);
    }
}


