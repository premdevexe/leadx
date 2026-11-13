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
        if (Schema::hasTable('payments') && ! Schema::hasColumn('payments', 'verification_source')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('verification_source', 20)->nullable()->after('verified_by_admin_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'verification_source')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('verification_source');
            });
        }
    }
};
