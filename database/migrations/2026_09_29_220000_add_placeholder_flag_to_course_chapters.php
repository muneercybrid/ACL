<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks a chapter row as an empty slot awaiting content.
 *
 * Without this, a scaffolded chapter and a chapter whose content was written
 * and then lost look identical, and the two need opposite handling: one is a
 * task waiting to be done, the other is a bug. Advisory, so plain booleans are
 * enough -- the Constitution requires a string status on the reserved status
 * column, which is untouched here.
 *
 * Additive only, per AGENTS.md section 4.2: no ran migration is edited.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('course_chapters')) {
            return;
        }

        if (Schema::hasColumn('course_chapters', 'placeholder')) {
            return;
        }

        Schema::table('course_chapters', function (Blueprint $table) {
            $table->boolean('placeholder')->default(false)->index('course_chapters_placeholder_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('course_chapters') || ! Schema::hasColumn('course_chapters', 'placeholder')) {
            return;
        }

        Schema::table('course_chapters', function (Blueprint $table) {
            $table->dropIndex('course_chapters_placeholder_idx');
            $table->dropColumn('placeholder');
        });
    }
};
