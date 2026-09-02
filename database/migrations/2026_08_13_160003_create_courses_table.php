<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // e.g., CSC101
            $table->string('title');
            $table->string('slug')->unique();
            // smallInteger, not tinyInteger: TINYINT tops out at 255 and
            // MariaDB raises error 1264 under strict mode rather than
            // clamping, which SQLite would have silently accepted.
            $table->unsignedSmallInteger('credit_units')->default(1);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
