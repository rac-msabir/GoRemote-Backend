<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('slug', 140)->unique();
            $table->timestamps();
        });

        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('slug', 140)->unique();
            $table->timestamps();
        });

        Schema::create('job_skill', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('jobs')->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained('skills')->cascadeOnDelete();
            $table->unique(['job_id','skill_id']);
        });

        Schema::table('jobs', function (Blueprint $table) {
            $table->string('slug', 191)->nullable()->unique()->after('title');
            $table->char('currency', 3)->nullable()->after('pay_max');
            $table->string('country_name', 120)->nullable()->after('country_code');
            $table->string('location', 191)->nullable()->after('country_name');
            $table->boolean('is_featured')->default(false)->after('status');
            $table->boolean('is_pinned')->default(false)->after('is_featured');
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete()->after('employer_id');
        });
    }

    public function down(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn(['slug','currency','country_name','location','is_featured','is_pinned']);
        });

        Schema::dropIfExists('job_skill');
        Schema::dropIfExists('skills');
        Schema::dropIfExists('categories');
    }
};



