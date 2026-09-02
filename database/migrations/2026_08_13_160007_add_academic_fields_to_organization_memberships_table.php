<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_memberships', function (Blueprint $table) {
            $table->foreignId('academic_program_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->foreignId('current_level_id')->nullable()->after('academic_program_id')->constrained('levels')->nullOnDelete();
            $table->string('matric_number')->nullable()->after('current_level_id');
            
            $table->unique(['organization_id', 'matric_number']);
        });
    }

    public function down(): void
    {
        Schema::table('organization_memberships', function (Blueprint $table) {
            $table->dropForeign(['academic_program_id']);
            $table->dropForeign(['current_level_id']);
            $table->dropColumn(['academic_program_id', 'current_level_id', 'matric_number']);
        });
    }
};
