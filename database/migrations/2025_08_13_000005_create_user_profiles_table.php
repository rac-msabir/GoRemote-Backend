<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            // Contact Information
            $table->string('phone', 50)->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('website')->nullable();
            $table->text('bio')->nullable();
            
            // Address
            $table->string('country', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('zip', 20)->nullable();
            $table->text('address')->nullable();
            
            // Professional
            $table->string('current_title')->nullable();
            $table->string('current_company')->nullable();
            $table->integer('years_of_experience')->nullable();
            $table->string('resume_path')->nullable();
            
            $table->timestamps();
            
            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};

