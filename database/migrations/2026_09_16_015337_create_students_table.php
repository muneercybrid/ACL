<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('acl_student_id')->unique();
            $table->enum('verification_method', ['jamb', 'manual', 'external'])->default('jamb');
            $table->enum('verification_status', ['pending', 'verified', 'failed'])->default('pending')->index();
            $table->string('nationality')->nullable();
            $table->string('state')->nullable();
            $table->string('local_government')->nullable();
            $table->string('admission_year', 10)->nullable();
            $table->string('lga', 80)->nullable();
            $table->string('region', 80)->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};