<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyReview extends Model
{
    /** @use HasFactory<\Database\Factories\CompanyReviewFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id','job_seeker_id','rating_overall','title','review_text','employment_status',
        'job_title_at_time','city','country_code','posted_at',
    ];
}
