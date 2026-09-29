<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records which semester a registration belongs to.
 *
 * The existing `semester_id` is a nullable FK to a semesters table that is
 * empty, so it cannot distinguish a first-semester registration from a
 * second-semester one. Without that distinction the ordered flow cannot be
 * enforced: the server would see "some courses registered" and could not tell
 * whether the student had finished semester 1.
 *
 * This stores the 1 or 2 the curriculum already carries, denormalised on
 * purpose. It is the value the flow branches on, and joining through an empty
 * table to recover it would be both slower and less clear.
 *
 * Additive only, per AGENTS.md section 4.2.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('student_course_registrations')) {
            return;
        }

        if (Schema::hasColumn('student_course_registrations', 'semester')) {
            return;
        }

        Schema::table('student_course_registrations', function (Blueprint $table) {
            $table->unsignedTinyInteger('semester')->nullable()->after('course_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('student_course_registrations')
            || ! Schema::hasColumn('student_course_registrations', 'semester')) {
            return;
        }

        Schema::table('student_course_registrations', function (Blueprint $table) {
            $table->dropColumn('semester');
        });
    }
};
