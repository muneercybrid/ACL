<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_program_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // e.g., "100 Level"
            $table->string('code'); // e.g., "100"
            // smallInteger, not tinyInteger: institutions commonly number
            // levels 100/200/300/400, and 300+ overflows TINYINT's 255 limit
            // as error 1264 on MariaDB under strict mode.
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['academic_program_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('levels');
    }
};
