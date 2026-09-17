<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('source_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('source_url', 500)->nullable();
            $table->string('document_version', 20)->nullable(); // e.g., 2023, 2024
            $table->date('retrieval_date');
            $table->string('file_path', 500)->nullable();
            $table->string('file_hash', 128)->nullable(); // sha256
            $table->enum('status', ['active','review','failed'])->default('review');
            $table->string('document_type', 30)->default('ccmas'); // ccmas / other
            $table->text('extraction_notes')->nullable();
            $table->string('import_batch_id', 50)->nullable();
            $table->timestamps();
            $table->index('status');
            $table->index('document_type');
        });
    }
    public function down(): void {
        Schema::dropIfExists('source_documents');
    }
};
