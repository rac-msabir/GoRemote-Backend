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
        Schema::table('companies', function (Blueprint $table) {
            $table->uuid('uuid')->unique()->after('id');
        });

        // Generate UUIDs for existing companies
        $companies = DB::table('companies')->whereNull('uuid')->get();
        foreach ($companies as $company) {
            DB::table('companies')->where('id', $company->id)->update([
                'uuid' => Str::uuid()->toString()
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
};