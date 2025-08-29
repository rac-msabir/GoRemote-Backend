<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Job extends Model
{
    /** @use HasFactory<\Database\Factories\JobFactory> */
    use HasFactory;

    protected $fillable = [
        'employer_id','category_id','title','slug','description','location_type','city','state_province','country_code','country_name','location',
        'job_type','pay_visibility','pay_min','pay_max','currency','pay_period','status','is_featured','is_pinned','posted_at','closed_at',
    ];

    protected $casts = [
        'posted_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function employer()
    {
        return $this->belongsTo(Employer::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function preferences()
    {
        return $this->hasOne(JobPreference::class);
    }

    public function benefits()
    {
        return $this->belongsToMany(JobBenefit::class, 'job_benefit_job');
    }

    public function screeningQuestions()
    {
        return $this->hasMany(JobScreeningQuestion::class);
    }

    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'job_skill');
    }

    public function applications()
    {
        return $this->hasMany(JobApplication::class);
    }
}
