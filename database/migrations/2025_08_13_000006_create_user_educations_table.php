<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_educations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('degree_title', 191);
            $table->string('institution', 191);
            $table->boolean('is_current')->default(false);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->text('description')->nullable();
            $table->decimal('gpa', 3, 2)->nullable();
            $table->timestamps();
            
            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_educations');
    }
};

