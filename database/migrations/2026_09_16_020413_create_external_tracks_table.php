<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconstructs the `external_tracks` table.
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
        Schema::create('external_tracks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('programme_id');
            $table->string('title', 255)->default('');
            $table->string('slug', 255)->default('');
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['slug'], 'external_tracks_slug_unique');
            $table->index(['programme_id'], 'external_tracks_programme_id_foreign');
        });
        Schema::table('external_tracks', function (Blueprint $table) {
            $table->foreign('programme_id', 'external_tracks_programme_id_foreign')
                ->references('id')
                ->on('programmes')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_tracks');
    }
};
