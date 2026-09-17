<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('platform_orientations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('completed')->default(false);
            $table->integer('quiz_score')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index('user_id');
        });
    }
    public function down(): void { Schema::dropIfExists('platform_orientations'); }
};
