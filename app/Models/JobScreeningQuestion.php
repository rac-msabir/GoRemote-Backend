<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobScreeningQuestion extends Model
{
    /** @use HasFactory<\Database\Factories\JobScreeningQuestionFactory> */
    use HasFactory;

    protected $fillable = [
        'job_id', 'question', 'type', 'is_required',
    ];

    public function job()
    {
        return $this->belongsTo(Job::class);
    }
}




