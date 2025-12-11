<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'phone',
        'linkedin_url',
        'website',
        'bio',
        'country',
        'province',
        'city',
        'zip',
        'address',
        'current_title',
        'current_company',
        'years_of_experience',
        'resume_path',
        'cover_letter'
    ];

    protected $casts = [
        'years_of_experience' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getResumeUrlAttribute(): ?string
    {
        if (!$this->resume_path) {
            return null;
        }

        if (filter_var($this->resume_path, FILTER_VALIDATE_URL)) {
            return $this->resume_path;
        }

        return asset('storage/' . $this->resume_path);
    }
}

