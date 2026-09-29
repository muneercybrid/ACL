<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconstructs the `chapter_sections` table.
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
        Schema::create('chapter_sections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('chapter_id');
            $table->unsignedTinyInteger('position');
            $table->string('title', 255)->default('');
            $table->longText('content');
                        // production uses ENUM here; see ADR note in this file.
            $table->enum('section_type', ['concept', 'explanation', 'example', 'practical', 'summary'])->default('concept');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['chapter_id', 'position'], 'chapter_sections_chapter_id_position_unique');
            $table->index(['chapter_id'], 'chapter_sections_chapter_id_foreign');
        });
        Schema::table('chapter_sections', function (Blueprint $table) {
            $table->foreign('chapter_id', 'chapter_sections_chapter_id_foreign')
                ->references('id')
                ->on('course_chapters')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chapter_sections');
    }
};
