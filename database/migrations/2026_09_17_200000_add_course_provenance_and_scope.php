<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            // Provenance / source classification per spec
            $table->string('scope', 30)->default('university')->after('description'); // nuc / university / department / verified / student_submitted / other
            $table->string('source_type', 30)->nullable()->after('scope'); // NUC / university / department / verified_admin / student_submitted / other
            $table->string('verification_status', 30)->default('unverified')->after('source_type');
            $table->string('source_url', 500)->nullable()->after('verification_status');
            $table->string('institution_id', 30)->nullable()->after('source_url'); // reference to institution when university-scoped
            $table->text('provenance_notes')->nullable()->after('institution_id');
            $table->string('import_batch_id', 50)->nullable()->after('provenance_notes');
        });

        // Drop global unique on code alone; will replace with compound unique via separate step if needed,
        // but for safety first just drop unique so university-specific codes can coexist.
        Schema::table('courses', function (Blueprint $table) {
            $table->dropUnique(['code']);
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['scope','source_type','verification_status','source_url','institution_id','provenance_notes','import_batch_id']);
        });
        // Note: original unique('code') not re-added in down (data may have duplicates now); manual restore only if needed.
    }
};
