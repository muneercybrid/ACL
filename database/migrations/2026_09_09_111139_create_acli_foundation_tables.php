<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /*
         * ACLi capabilities
         *
         * Defines the AI capabilities exposed by ACLi.
         * Provider/model configuration is intentionally kept outside
         * the database for this foundation.
         */
        Schema::create('acli_capabilities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        /*
         * ACLi conversations
         *
         * A conversation belongs to a user and may optionally be
         * anchored to ACL academic/learning context.
         *
         * Context references are nullable because ACLi must also
         * support general learning conversations for external learners.
         */
        Schema::create('acli_conversations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('course_offering_id')
                ->nullable()
                ->constrained('course_offerings')
                ->nullOnDelete();

            $table->foreignId('chapter_id')
                ->nullable()
                ->constrained('chapters')
                ->nullOnDelete();

            $table->foreignId('lesson_id')
                ->nullable()
                ->constrained('lessons')
                ->nullOnDelete();

            $table->string('title')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('course_offering_id');
            $table->index('chapter_id');
            $table->index('lesson_id');
        });

        /*
         * ACLi messages
         *
         * Stores the conversation messages.
         * Metadata is intentionally JSON so provider-specific information
         * does not leak into the core ACLi schema.
         */
        Schema::create('acli_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('conversation_id')
                ->constrained('acli_conversations')
                ->cascadeOnDelete();

            $table->string('role');
            $table->longText('content');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
            $table->index('role');
        });

        /*
         * ACLi requests
         *
         * One record represents one model/provider request.
         * This gives ACLi independent usage, latency, token and cost
         * accounting without modifying ACL subscriptions.
         */
        Schema::create('acli_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('conversation_id')
                ->nullable()
                ->constrained('acli_conversations')
                ->nullOnDelete();

            $table->foreignId('capability_id')
                ->nullable()
                ->constrained('acli_capabilities')
                ->nullOnDelete();

            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->string('status')->default('pending');

            $table->unsignedBigInteger('input_tokens')->nullable();
            $table->unsignedBigInteger('output_tokens')->nullable();
            $table->unsignedBigInteger('total_tokens')->nullable();

            $table->decimal('estimated_cost', 12, 6)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();

            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['conversation_id', 'created_at']);
            $table->index(['capability_id', 'created_at']);
            $table->index(['provider', 'model']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acli_requests');
        Schema::dropIfExists('acli_messages');
        Schema::dropIfExists('acli_conversations');
        Schema::dropIfExists('acli_capabilities');
    }
};
