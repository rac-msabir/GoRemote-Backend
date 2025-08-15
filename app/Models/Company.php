<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    /** @use HasFactory<\Database\Factories\CompanyFactory> */
    use HasFactory;

    protected $fillable = ['name','website','country_code'];

    public function reviews()
    {
        return $this->hasMany(CompanyReview::class);
    }

    public function salaries()
    {
        return $this->hasMany(CompanySalary::class);
    }

    public function users()
    {
        return $this->hasMany(CompanyUser::class);
    }

    public function jobs()
    {
        return $this->hasMany(Job::class);
    }
}
