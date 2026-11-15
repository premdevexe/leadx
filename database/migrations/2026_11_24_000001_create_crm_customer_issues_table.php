<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_customer_issues', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->index();
            $table->unsignedBigInteger('customer_id');
            $table->string('issue_title', 255);
            $table->text('issue_description');
            // still_facing | fixed
            $table->string('status', 20)->default('still_facing')->index();
            $table->date('issue_date')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->restrictOnDelete();

            $table->index(['employee_id', 'status']);
            $table->index(['employee_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_customer_issues');
    }
};
