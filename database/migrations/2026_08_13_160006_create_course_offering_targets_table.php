<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_offering_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_offering_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_id')->nullable()->constrained()->nullOnDelete(); // Null means ALL levels in the program
            $table->boolean('is_mandatory')->default(true); // Core course vs Elective
            $table->timestamps();
            
            $table->unique(['course_offering_id', 'academic_program_id', 'level_id'], 'offering_target_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_offering_targets');
    }
};
