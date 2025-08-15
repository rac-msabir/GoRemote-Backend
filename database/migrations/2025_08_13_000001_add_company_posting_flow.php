<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create company_users table for company-user relationships
        Schema::create('company_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('role', ['owner','manager','recruiter','viewer'])->default('owner');
            $table->timestamps();
            $table->unique('user_id');
            $table->index('company_id');
        });

        // Add company_id to jobs table
        Schema::table('jobs', function (Blueprint $table) {
            if (Schema::hasColumn('jobs', 'employer_id')) {
                // Make employer_id nullable and add company_id
                $table->unsignedBigInteger('employer_id')->nullable()->change();
                $table->foreignId('company_id')->nullable()->after('employer_id')->constrained('companies')->nullOnDelete();
                $table->index('company_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            if (Schema::hasColumn('jobs', 'company_id')) {
                $table->dropConstrainedForeignId('company_id');
            }
        });
        Schema::dropIfExists('company_users');
    }
};
