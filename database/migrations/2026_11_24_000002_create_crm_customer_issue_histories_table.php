<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_customer_issue_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('issue_id')->index();
            $table->unsignedBigInteger('employee_id')->nullable()->index();
            $table->string('old_status', 20)->nullable();
            $table->string('new_status', 20);
            $table->text('update_note')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('issue_id')->references('id')->on('crm_customer_issues')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('users')->nullOnDelete();

            $table->index(['issue_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_customer_issue_histories');
    }
};
