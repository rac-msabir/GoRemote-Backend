<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_application_experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_application_id')->constrained('job_applications')->onDelete('cascade');
            $table->string('company_name', 191);
            $table->boolean('is_current')->default(false);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['job_application_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_application_experiences');
    }
};

