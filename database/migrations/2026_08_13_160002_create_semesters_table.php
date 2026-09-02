<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('semesters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // e.g., "First Semester"
            $table->string('slug');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['academic_session_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('semesters');
    }
};
