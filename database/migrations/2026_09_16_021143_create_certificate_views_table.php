<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconstructs the `certificate_views` table.
 *
 * This table exists in production but no migration created it, which is why a
 * fresh database could not be built and the test suite could not run at all.
 * The definition below is taken from the live production schema, so the schema a
 * test runs against is the schema the application actually serves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_views', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('certificate_id');
            $table->string('ip_address', 45)->nullable()->default('');
            $table->string('user_agent', 255)->nullable()->default('');
            $table->string('referrer', 255)->nullable()->default('');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['certificate_id'], 'certificate_views_certificate_id_foreign');
            $table->index(['certificate_id', 'created_at'], 'certificate_views_certificate_id_created_at_index');
        });
        Schema::table('certificate_views', function (Blueprint $table) {
            $table->foreign('certificate_id', 'certificate_views_certificate_id_foreign')
                ->references('id')
                ->on('certificates')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_views');
    }
};
