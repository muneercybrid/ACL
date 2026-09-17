<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('outline_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_outline_id')->constrained('course_outlines')->cascadeOnDelete();
            $table->text('question_text');
            $table->text('answer_key');
            $table->string('question_type')->default('short_answer');
            $table->json('options')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
            $table->index(['course_outline_id', 'order']);
        });

        Schema::create('student_course_outlines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('course_outline_id')->constrained('course_outlines')->cascadeOnDelete();
            $table->json('answers')->nullable();
            $table->boolean('questions_passed')->default(false);
            $table->timestamp('passed_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'course_id', 'course_outline_id'], 'sc_outlines_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_course_outlines');
        Schema::dropIfExists('outline_questions');
    }
};
