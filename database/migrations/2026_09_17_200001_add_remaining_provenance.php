<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('courses', function (Blueprint $table) {
            if (! Schema::hasColumn('courses', 'institution_id')) $table->string('institution_id', 30)->nullable()->after('source_url');
            if (! Schema::hasColumn('courses', 'provenance_notes')) $table->text('provenance_notes')->nullable()->after('institution_id');
            if (! Schema::hasColumn('courses', 'import_batch_id')) $table->string('import_batch_id', 50)->nullable()->after('provenance_notes');
            if (! Schema::hasColumn('courses', 'verification_status')) $table->string('verification_status', 30)->default('unverified')->after('source_type');
        });
    }
    public function down(): void {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['institution_id','provenance_notes','import_batch_id']);
        });
    }
};
