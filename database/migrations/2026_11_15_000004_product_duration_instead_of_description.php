<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Products are defined by name, price and a default duration.
     * The free-text description is dropped; duration values come from
     * SalesOrder::DURATIONS (3d, 7d, 15d, 30d, 3m, 6m, 1y).
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('duration', 20)->default('1y')->after('price');
            $table->dropColumn('description');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->dropColumn('duration');
        });
    }
};
