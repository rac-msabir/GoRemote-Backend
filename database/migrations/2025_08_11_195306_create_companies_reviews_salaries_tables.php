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
        // companies
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191)->unique();
            $table->string('website', 191)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->timestamps();
        });

        // company_reviews
        Schema::create('company_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('job_seeker_id')->constrained('job_seekers')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating_overall');
            $table->string('title', 191)->nullable();
            $table->text('review_text');
            $table->enum('employment_status', ['current','former'])->nullable();
            $table->string('job_title_at_time', 120)->nullable();
            $table->string('city', 120)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->timestamp('posted_at');
            $table->timestamps();
            $table->index('company_id');
            $table->index('job_seeker_id');
        });

        // company_salaries
        Schema::create('company_salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('job_title', 120)->index();
            $table->string('city', 120)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->decimal('pay_min', 12, 2)->nullable();
            $table->decimal('pay_max', 12, 2)->nullable();
            $table->enum('pay_period', ['hour','day','week','month','year']);
            $table->enum('data_source', ['user_reported','aggregated','employer_provided'])->default('user_reported');
            $table->timestamps();
            $table->index('company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_salaries');
        Schema::dropIfExists('company_reviews');
        Schema::dropIfExists('companies');
    }
};
