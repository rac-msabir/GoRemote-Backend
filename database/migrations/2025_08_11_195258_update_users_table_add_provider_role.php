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
        Schema::table('users', function (Blueprint $table) {
            $table->enum('provider', ['local','google','apple','facebook'])->default('local')->after('password');
            $table->string('provider_id', 191)->nullable()->after('provider');
            $table->enum('role', ['seeker','employer','admin'])->default('seeker')->after('provider_id');
            // ensure email unique already exists by default in base migration
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['provider', 'provider_id', 'role']);
        });
    }
};
