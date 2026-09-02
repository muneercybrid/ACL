<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chapters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_offering_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['course_offering_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chapters');
    }
};
