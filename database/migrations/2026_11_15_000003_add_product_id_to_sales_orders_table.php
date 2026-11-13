<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Link orders to the catalog product they were created from.
     *
     * product_name / product_value stay as denormalized snapshots so
     * orders survive product edits and deletions; this FK is informational.
     */
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->after('product_name');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropIndex('product_id');
            $table->dropColumn('product_id');
        });
    }
};
