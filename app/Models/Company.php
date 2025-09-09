<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Company extends Model
{
    /** @use HasFactory<\Database\Factories\CompanyFactory> */
    use HasFactory;

    protected $fillable = ['name','website','country_code','uuid'];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Str::uuid()->toString();
            }
        });
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

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
