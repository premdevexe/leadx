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
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->string('backup_id')->unique();
            $table->string('backup_type')->default('full'); // full, database, storage
            $table->string('disk')->default('local');       // local, s3, r2, sftp
            $table->string('file_path');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('database_status')->default('skipped'); // success, failed, skipped
            $table->string('storage_status')->default('skipped');  // success, failed, skipped
            $table->string('remote_status')->default('skipped');   // uploaded, failed, skipped, pending
            $table->string('integrity_status')->default('unverified'); // verified, failed, unverified
            $table->string('checksum')->nullable();
            $table->boolean('is_encrypted')->default(true);
            $table->json('metadata')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['backup_type', 'created_at']);
            $table->index('integrity_status');
        });

        Schema::create('backup_restore_logs', function (Blueprint $table) {
            $table->id();
            $table->string('backup_id');
            $table->foreignId('initiated_by_admin_id')->constrained('admins')->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending, in_progress, completed, failed
            $table->string('restore_type')->default('full'); // full, database, storage
            $table->string('environment')->default('production');
            $table->string('pre_restore_backup_id')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->longText('log_output')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('backup_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backup_restore_logs');
        Schema::dropIfExists('backups');
    }
};
