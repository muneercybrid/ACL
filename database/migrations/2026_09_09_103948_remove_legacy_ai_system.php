<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Remove the legacy ACL AI database tables.
         *
         * Order matters because ai_messages references
         * ai_conversations.
         */

        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
        Schema::dropIfExists('ai_subscriptions');
        Schema::dropIfExists('ai_capabilities');

        /*
         * Remove legacy AI fields that were added directly
         * to the normal ACL subscriptions table.
         */

        Schema::table('subscriptions', function (Blueprint $table) {
            $columns = [
                'ai_entitlement',
                'ai_provider',
                'ai_model',
                'daily_token_limit',
                'monthly_token_limit',
                'usage_count_today',
                'usage_count_this_month',
                'daily_reset_at',
                'monthly_reset_at',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('subscriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        /*
         * The legacy AI system is intentionally not restored.
         *
         * ACLi will have a new database architecture rather than
         * recreating the obsolete AI schema.
         */
    }
};
