<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('code')->nullable();
            $table->string('degree_type')->nullable();
            $table->unsignedSmallInteger('duration_years')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['department_id', 'slug']);
            $table->unique(['department_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_programs');
    }
};
