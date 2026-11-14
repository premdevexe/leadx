<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive: record whether a WhatsApp share carried the customer's API key.
 *
 * This is an audit FACT (boolean) only — the key itself is scrubbed out of
 * the stored `message` and is never written to the log. The column exists so
 * "an authorized user explicitly chose to share the key" is verifiable in
 * the share history without ever being able to recover the key from it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_share_logs', function (Blueprint $table) {
            $table->boolean('shared_api_key')->default(false)->after('message');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_share_logs', function (Blueprint $table) {
            $table->dropColumn('shared_api_key');
        });
    }
};
