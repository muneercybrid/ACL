<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('course_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nuc_course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('institution_course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->string('institution_id', 30)->nullable();
            $table->string('mapping_type', 30)->default('approximate'); // exact / approximate / institution_variant / unverified
            $table->string('source_document_id', 30)->nullable();
            $table->string('verification_status', 30)->default('unverified');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['nuc_course_id','institution_course_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('course_mappings');
    }
};
