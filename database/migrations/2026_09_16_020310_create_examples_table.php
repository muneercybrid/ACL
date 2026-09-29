<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconstructs the `examples` table.
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
        Schema::create('examples', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('chapter_id');
            $table->unsignedTinyInteger('position');
            $table->string('title', 255)->default('');
                        // production uses ENUM here; see ADR note in this file.
            $table->enum('example_type', ['academic', 'practical', 'nigerian_context', 'industry', 'real_world', 'worked', 'code'])->default('practical');
            $table->longText('content');
            $table->longText('explanation')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['chapter_id'], 'examples_chapter_id_foreign');
            $table->index(['chapter_id', 'position'], 'examples_chapter_id_position_index');
        });
        Schema::table('examples', function (Blueprint $table) {
            $table->foreign('chapter_id', 'examples_chapter_id_foreign')
                ->references('id')
                ->on('course_chapters')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('examples');
    }
};
