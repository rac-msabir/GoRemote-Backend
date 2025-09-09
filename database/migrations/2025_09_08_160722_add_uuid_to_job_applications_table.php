<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->uuid('uuid')->unique()->after('id');
        });

        // Generate UUIDs for existing job applications
        $applications = DB::table('job_applications')->whereNull('uuid')->get();
        foreach ($applications as $application) {
            DB::table('job_applications')->where('id', $application->id)->update([
                'uuid' => Str::uuid()->toString()
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
};