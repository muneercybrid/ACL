<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 *  * Adds the production-only columns for `institutions`.
 *
 * Split out of 2026_09_16_030000 because `institutions` is not created until
 * 2026_09_17_200007, which runs after that migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->string('official_name', 255)->nullable();
            $table->string('abbr', 20)->nullable();
        });

    }

    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropColumn('official_name');
        });
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropColumn('abbr');
        });
    }
};
