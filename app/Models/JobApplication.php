<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class JobApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_id',
        'job_seeker_id',
        'name',
        'email',
        'phone',
        'country',
        'province',
        'city',
        'zip',
        'address',
        'linkedin_url',
        'cover_letter',
        'resume_path',
        'uuid',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

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

    // Relationships
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function jobSeeker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'job_seeker_id');
    }

    public function experiences(): HasMany
    {
        return $this->hasMany(JobApplicationExperience::class);
    }

    public function educations(): HasMany
    {
        return $this->hasMany(JobApplicationEducation::class);
    }

    // Accessors
    public function getResumeUrlAttribute(): ?string
    {
        if (!$this->resume_path) {
            return null;
        }

        // If it's already a full URL, return as is
        if (filter_var($this->resume_path, FILTER_VALIDATE_URL)) {
            return $this->resume_path;
        }

        // Otherwise, generate URL from storage
        return asset('storage/' . $this->resume_path);
    }
}

