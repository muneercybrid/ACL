<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Student verdicts on ACLi answers.
 *
 * Kept separate from the conversation history so that quality reporting does
 * not require reading message bodies, and so deleting a conversation does not
 * silently erase the record that an answer was rated unhelpful.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acli_message_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->text('content')->nullable();
            $table->enum('verdict', ['like', 'dislike']);
            $table->timestamps();

            // The superadmin quality report filters by verdict over a period.
            $table->index(['verdict', 'created_at']);
            $table->index('conversation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acli_message_feedback');
    }
};
