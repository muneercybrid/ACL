<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_consents')) {
            Schema::create('user_consents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('document_type', 30);   // terms | privacy
                $table->string('policy_version', 20);
                $table->timestamp('consented_at');
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 300)->nullable();
                $table->timestamps();
                $table->index(['user_id', 'document_type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_consents');
    }
};
