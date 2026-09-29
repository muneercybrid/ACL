<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconstructs the `external_curriculum_tables` table.
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
        Schema::create('external_curriculum_tables', function (Blueprint $table) {
            $table->id();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_curriculum_tables');
    }
};
