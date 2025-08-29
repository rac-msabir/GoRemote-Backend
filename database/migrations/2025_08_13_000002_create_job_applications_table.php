<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('jobs')->onDelete('cascade');
            $table->foreignId('job_seeker_id')->nullable()->constrained('users')->onDelete('set null');
            
            // Personal info
            $table->string('name', 191);
            $table->string('email', 191);
            $table->string('phone', 50);
            
            // Address
            $table->string('country', 100);
            $table->string('province', 100);
            $table->string('city', 100);
            $table->string('zip', 20);
            $table->text('address');
            
            // Documents
            $table->string('linkedin_url')->nullable();
            $table->text('cover_letter')->nullable();
            $table->string('resume_path')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['job_id']);
            $table->index(['job_seeker_id']);
            
            // Optional: prevent duplicate applications per user per job
            $table->unique(['job_id', 'job_seeker_id'], 'unique_job_seeker_application');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_applications');
    }
};

