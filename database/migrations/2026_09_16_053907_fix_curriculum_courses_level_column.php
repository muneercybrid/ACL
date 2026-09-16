<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fix curriculum_courses.level column: it was created as TINYINT UNSIGNED
 * in the live database (max 255), but 300/400 level courses overflow it.
 * Widen to SMALLINT UNSIGNED (max 65535) — ample for 100–400+ levels.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('curriculum_courses', function (Blueprint $table) {
            $table->unsignedSmallInteger('level')->default(100)->change();
        });
    }

    public function down(): void
    {
        Schema::table('curriculum_courses', function (Blueprint $table) {
            $table->tinyInteger('level')->unsigned()->default(100)->change();
        });
    }
};