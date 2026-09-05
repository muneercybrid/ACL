<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_offerings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('custom_code')->nullable(); // Departments sometimes use local codes
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Prevent the same department from offering the same course twice in one semester
            $table->unique(['course_id', 'semester_id', 'department_id'], 'course_semester_dept_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_offerings');
    }
};
