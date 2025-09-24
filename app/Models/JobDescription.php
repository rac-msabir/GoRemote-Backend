<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobDescription extends Model
{
    protected $table = 'job_descriptions';

    protected $fillable = [
        'job_id',
        'type',
        'content',
    ];

    public function job()
    {
        return $this->belongsTo(Job::class);
    }
}
