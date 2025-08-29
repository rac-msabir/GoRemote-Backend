<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobApplicationEducation extends Model
{
    use HasFactory;
    protected $table = 'job_application_educations';
    protected $fillable = [
        'job_application_id',
        'degree_title',
        'institution',
        'is_current',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'is_current' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function jobApplication(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class);
    }
}

