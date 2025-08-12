<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // job_seekers
        Schema::create('job_seekers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('city', 120)->nullable();
            $table->string('state_province', 120)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->char('country_code', 2)->nullable()->index();
            $table->boolean('remote_preference')->default(false);
            $table->decimal('min_base_pay', 12, 2)->nullable();
            $table->enum('min_pay_period', ['hour','day','week','month','year'])->nullable();
            $table->timestamps();
        });

        // seeker_desired_titles
        Schema::create('seeker_desired_titles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_seeker_id')->constrained('job_seekers')->cascadeOnDelete();
            $table->string('title', 120);
            $table->unsignedTinyInteger('priority')->default(0);
            $table->timestamps();
            $table->unique(['job_seeker_id','title']);
            $table->index('job_seeker_id');
        });

        // resumes
        Schema::create('resumes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_seeker_id')->constrained('job_seekers')->cascadeOnDelete();
            $table->string('file_path', 255);
            $table->string('file_name', 191);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('size_bytes')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
            $table->index('job_seeker_id');
        });

        // job_alerts
        Schema::create('job_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_seeker_id')->constrained('job_seekers')->cascadeOnDelete();
            $table->string('keywords', 191)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('state_province', 120)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->enum('frequency', ['daily','weekly'])->default('daily');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index('job_seeker_id');
        });
        
        // saved_jobs
        Schema::create('saved_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_seeker_id')->constrained('job_seekers')->cascadeOnDelete();
            $table->foreignId('job_id')->constrained('jobs')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['job_seeker_id','job_id']);
            $table->index('job_seeker_id');
            $table->index('job_id');
        });

        // applications
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_seeker_id')->constrained('job_seekers')->cascadeOnDelete();
            $table->foreignId('job_id')->constrained('jobs')->cascadeOnDelete();
            $table->foreignId('resume_id')->nullable()->constrained('resumes')->nullOnDelete();
            $table->enum('status', ['applied','reviewed','interviewing','offered','hired','rejected','withdrawn'])->default('applied');
            $table->timestamp('applied_at');
            $table->timestamp('updated_at')->nullable();
            $table->boolean('external_redirect')->default(false);
            $table->text('notes_internal')->nullable();
            $table->index(['job_seeker_id','job_id']);
        });

        // application_answers
        Schema::create('application_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('job_screening_questions')->cascadeOnDelete();
            $table->text('answer_text')->nullable();
            $table->boolean('answer_bool')->nullable();
            $table->decimal('answer_number', 12, 2)->nullable();
            $table->timestamps();
            $table->unique(['application_id','question_id']);
            $table->index('application_id');
            $table->index('question_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_answers');
        Schema::dropIfExists('applications');
        Schema::dropIfExists('saved_jobs');
        Schema::dropIfExists('job_alerts');
        Schema::dropIfExists('resumes');
        Schema::dropIfExists('seeker_desired_titles');
        Schema::dropIfExists('job_seekers');
    }
};
