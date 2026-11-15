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
        // 1. permissions
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('module')->index();
            $table->string('action');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // 2. permission_presets
        Schema::create('permission_presets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->timestamps();
        });

        // 3. permission_preset_items
        Schema::create('permission_preset_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('preset_id')->constrained('permission_presets')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->boolean('granted')->default(true);
            $table->timestamps();

            $table->unique(['preset_id', 'permission_id']);
        });

        // 4. users: add preset_id
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('preset_id')->nullable()->after('team_id')->constrained('permission_presets')->nullOnDelete();
        });

        // 5. employee_permissions (overrides)
        Schema::create('employee_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->boolean('granted')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'permission_id']);
        });

        // 6. employee_permission_histories
        Schema::create('employee_permission_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->boolean('old_value')->nullable();
            $table->boolean('new_value');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        // 7. employee_team_access_grants
        Schema::create('employee_team_access_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_team_access_grants');
        Schema::dropIfExists('employee_permission_histories');
        Schema::dropIfExists('employee_permissions');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('preset_id');
        });

        Schema::dropIfExists('permission_preset_items');
        Schema::dropIfExists('permission_presets');
        Schema::dropIfExists('permissions');
    }
};
