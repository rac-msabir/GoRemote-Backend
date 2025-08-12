<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanySalary extends Model
{
    /** @use HasFactory<\Database\Factories\CompanySalaryFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id','job_title','city','country_code','pay_min','pay_max','pay_period','data_source',
    ];
}
