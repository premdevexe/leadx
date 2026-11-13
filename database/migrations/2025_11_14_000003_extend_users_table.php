<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Incremental: adds columns to the existing users table without
     * touching existing admin data.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // username was added in 2025_11_14_000001 if not already present.
            if (! Schema::hasColumn('users', 'username')) {
                $table->string('username')->unique()->nullable()->after('name');
            }

            $table->string('phone')->nullable()->after('username');
            $table->string('role', 20)->default('employee')->index()->after('phone');
            $table->string('status', 20)->default('active')->index()->after('role');

            // team_id nullable — set up after teams table so FK is valid.
            $table->unsignedBigInteger('team_id')->nullable()->after('status');
            $table->foreign('team_id')->references('id')->on('teams')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
            $table->dropColumn(['phone', 'role', 'status']);
        });
    }
};
