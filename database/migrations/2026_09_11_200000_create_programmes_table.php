<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programmes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            // Loose reference: nuc_disciplines is created by a later migration
            $table->unsignedBigInteger('nuc_discipline_id')->nullable()->index();
            $table->string('name');
            $table->string('code', 30)->nullable();
            $table->string('degree_type', 30)->default('B.Sc.');
            $table->tinyInteger('duration_years')->default(4);
            $table->enum('scope', ['national', 'university', 'faculty', 'department', 'programme'])->default('programme');
            $table->string('verification_status', 30)->default('unverified');
            $table->string('source_type')->nullable();
            $table->text('source_document')->nullable();
            $table->string('source_url')->nullable();
            $table->date('date_verified')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index('organization_id');
            $table->index('verification_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programmes');
    }
};