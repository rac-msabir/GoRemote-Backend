<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserProject extends Model
{

    use HasFactory;

    protected $table = 'user_projects'; // change if you used 'user_project'

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'live_url',
        'github_url',
    ];

    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
