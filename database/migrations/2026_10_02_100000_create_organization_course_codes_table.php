<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A school's own code for a course that lives centrally.
 *
 * This is the table that makes the platform's model work. A course exists once,
 * in `courses`, and is shared by every school that runs it. But each school
 * codes it its own way — "NUK-CYB101" at one, "BUK-CSC 111" at another — and
 * that local code is the school's business, not the platform's. So the mapping
 * lives here rather than on the course.
 *
 * The same central course can therefore appear under a different code at every
 * institution that teaches it, while pointing at one set of course content.
 * Neither school edits the other's, and neither can claim the code as theirs.
 *
 * Unique on (organization_id, local_code): a school may not register two
 * different central courses under one of its own codes, which is what stops a
 * second coordinator quietly reassigning a code that already means something.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_course_codes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            // The shared course this local code names.
            $table->foreignId('course_id')
                ->constrained('courses')
                ->cascadeOnDelete();

            // The school's own code, stored as given apart from surrounding
            // space. Case is preserved because institutions are not consistent
            // about it and the difference is visible to them.
            $table->string('local_code', 64);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['organization_id', 'local_code'], 'occ_org_local_code_unq');

            // Reading "which courses does this school call what" is by course.
            $table->index('course_id', 'occ_course_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_course_codes');
    }
};
