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
        // employers
        Schema::create('employers', function (Blueprint $table) {
            $table->id();
            $table->string('company_name', 191);
            $table->string('website', 191)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->timestamps();
        });

        // employer_users
        Schema::create('employer_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employer_id')->constrained('employers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('role', ['owner','manager','recruiter','viewer'])->default('recruiter');
            $table->timestamps();
            $table->unique('user_id');
            $table->index('employer_id');
        });

        // jobs
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employer_id')->constrained('employers')->cascadeOnDelete();
            $table->string('title', 191)->index();
            $table->longText('description');
            $table->enum('location_type', ['on_site','hybrid','remote'])->default('on_site')->index();
            $table->string('city', 120)->nullable();
            $table->string('state_province', 120)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->enum('job_type', ['full_time','part_time','temporary','contract','internship','fresher'])->index();
            $table->enum('pay_visibility', ['range','exact','starting_at'])->nullable();
            $table->decimal('pay_min', 12, 2)->nullable();
            $table->decimal('pay_max', 12, 2)->nullable();
            $table->enum('pay_period', ['hour','day','week','month','year'])->nullable();
            $table->enum('status', ['draft','published','closed'])->default('draft')->index();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index('employer_id');
        });

        // job_benefits lookup
        Schema::create('job_benefits', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->timestamps();
        });

        // pivot job_benefit_job
        Schema::create('job_benefit_job', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('jobs')->cascadeOnDelete();
            $table->foreignId('job_benefit_id')->constrained('job_benefits')->cascadeOnDelete();
            $table->unique(['job_id','job_benefit_id']);
            $table->index('job_id');
            $table->index('job_benefit_id');
        });

        // screening questions
        Schema::create('job_screening_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('jobs')->cascadeOnDelete();
            $table->string('question', 255);
            $table->enum('type', ['text','boolean','number']);
            $table->boolean('is_required')->default(false);
            $table->timestamps();
            $table->index('job_id');
        });

        // job_preferences
        Schema::create('job_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->unique()->constrained('jobs')->cascadeOnDelete();
            $table->string('daily_updates_email', 191)->nullable();
            $table->boolean('notify_each_application')->default(false);
            $table->boolean('resume_required')->default(true);
            $table->boolean('allow_candidate_email')->default(false);
            $table->enum('hiring_timeline', ['asap','1_2_weeks','2_4_weeks','1_3_months','flexible']);
            $table->unsignedSmallInteger('hires_planned_30d')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_preferences');
        Schema::dropIfExists('job_screening_questions');
        Schema::dropIfExists('job_benefit_job');
        Schema::dropIfExists('job_benefits');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('employer_users');
        Schema::dropIfExists('employers');
    }
};
