<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('learning_outcomes', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->enum('level', ['discipline','programme','course'])->default('course');
            $table->unsignedBigInteger('discipline_id')->nullable();
            $table->unsignedBigInteger('programme_id')->nullable();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->text('outcome_text');
            $table->string('source_type', 30)->nullable();
            $table->string('verification_status', 30)->default('unverified');
            $table->timestamps();
            $table->index(['level','course_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('learning_outcomes'); }
};
