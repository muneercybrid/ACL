<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // text, video, image, link, pdf
            $table->unsignedInteger('position')->default(0);
            $table->json('data'); // Flexible payload for each block type
            $table->timestamps();

            $table->index(['lesson_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_blocks');
    }
};
