<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The searchable NUC CCMAS 2023 course catalogue.
 *
 * This is a catalogue of what the 17 CCMAS discipline documents *say*, not a
 * list of ACL courses. A level coordinator populating a programme searches
 * here by title or code, picks the CCMAS entry, and maps it onto whatever
 * their institution actually offers. Nothing in this table is a student-facing
 * record and nothing here is attached to an institution.
 *
 * The extraction is deliberately conservative: a row that cannot be read as
 * "<CODE> <Title>" inside a "Course Contents and Learning Outcomes" section is
 * dropped rather than guessed, because a garbage row here would end up on a
 * real programme.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ccmas_courses', function (Blueprint $table) {
            $table->id();

            // Provenance. `source_line` is the 1-based line number in the .txt
            // file. Page numbers are deliberately NOT stored: the OCR renders a
            // bare integer both in the page footer and inside every table
            // (1,135 bare integers against 228 pages in computing.txt alone),
            // so a derived page number would be a guess wearing a number.
            $table->string('source_document', 100);            // e.g. computing
            $table->string('source_file', 255);                // e.g. computing.txt
            $table->unsignedInteger('source_line')->nullable();

            // The discipline the document belongs to. The code is denormalised
            // so a coordinator can filter without joining nuc_disciplines; the
            // foreign key is the integrity control.
            $table->string('discipline_code', 30)->nullable();
            $table->foreignId('nuc_discipline_id')
                ->nullable()
                ->constrained('nuc_disciplines')
                ->nullOnDelete();

            $table->string('course_code', 30);                 // e.g. "CYB 301"
            $table->string('title', 255);

            // Credit units as printed in the source, e.g. "(2 Units C: LH 15;
            // PH 45)". Frequently absent in the corpus and never inferred.
            $table->decimal('credit_units', 5, 2)->nullable();

            // From an explicit "100 Level" / "300-Level Courses" heading only.
            // Never derived from the digits of the course code: GST 111 is a
            // 100-level course and 111 is not a level.
            $table->unsignedSmallInteger('level')->nullable();

            $table->string('programme_title', 255)->nullable();

            $table->string('status', 30)->default('imported');
            // imported / needs_review / verified / superseded
            // "needs_review" means the row was extracted from a section that
            // carried no explicit level heading, or whose programme could not
            // be identified. It is a flag for a human, not a rejection.

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Control, not decoration: the same course listed twice inside one
            // programme is one row, and the import is idempotent against it.
            // 2280 bytes across utf8mb4, inside the 3072-byte InnoDB limit.
            $table->unique(
                ['discipline_code', 'programme_title', 'course_code', 'title'],
                'ccmas_courses_programme_course_unique'
            );

            $table->index('course_code', 'ccmas_courses_code_idx');
            $table->index('title', 'ccmas_courses_title_idx');
            $table->index(['discipline_code', 'level'], 'ccmas_courses_discipline_level_idx');
            $table->index('source_document', 'ccmas_courses_source_document_idx');
            $table->index('status', 'ccmas_courses_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ccmas_courses');
    }
};
