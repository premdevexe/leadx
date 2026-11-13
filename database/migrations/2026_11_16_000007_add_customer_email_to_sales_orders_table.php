<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive: capture the customer's email on the order so the CRM
     * matching policy (phone, then phone+email pair) has both keys to
     * work with. Legacy rows keep NULL.
     */
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->string('customer_email', 190)->nullable()->after('customer_phone');
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn('customer_email');
        });
    }
};
