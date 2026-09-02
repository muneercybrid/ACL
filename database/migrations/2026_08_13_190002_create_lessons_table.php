<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->unsignedInteger('position')->default(0);
            $table->string('status')->default('draft'); // draft, pending_review, approved, published
            $table->timestamps();

            $table->unique(['chapter_id', 'slug']);
            $table->index(['chapter_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
