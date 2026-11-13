<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CRM customers. A customer row is only promoted from `enquiry` to
     * `paying` after a *verified* purchase — see config/leadx.php -> crm.
     * Normalized phone is the primary matching key, with a
     * (phone, email) pair as the fallback; the composite unique index
     * keeps repeat purchases from ever creating a duplicate record.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email', 190)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('normalized_phone', 30)->nullable();
            $table->string('normalized_email', 190)->nullable();
            $table->string('status', 20)->default('enquiry')->index(); // enquiry | paying
            $table->unsignedBigInteger('team_id')->nullable()->index();
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            // Purchase history (updated on each verified purchase).
            $table->unsignedInteger('purchases_count')->default(0);
            $table->decimal('total_purchased', 15, 2)->default(0);
            $table->timestamp('last_purchase_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'normalized_phone', 'normalized_email'], 'customer_match_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
