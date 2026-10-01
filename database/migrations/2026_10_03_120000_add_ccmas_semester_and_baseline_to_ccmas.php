<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('ccmas_courses', function (Blueprint $t) {
            $t->string('semester', 20)->nullable()->after('level')
              ->comment('Assigned by national-curriculum architect; CCMAS source has level only');
            $t->string('course_type', 20)->nullable()->after('semester')
              ->comment('mandatory / elective / core — preserved from CCMAS document classification');
            $t->boolean('is_mandatory')->default(false)->after('course_type')
              ->comment('Is this part of the national 70% mandatory baseline');
            $t->tinyInteger('credit_units')->nullable()->change(); // already exists
        });

        Schema::table('curriculum_versions', function (Blueprint $t) {
            // Configurable baseline proportion (domain concept, not hard-coded 70 in logic)
            $t->tinyInteger('ccmas_baseline_percentage')->default(70)
              ->after('is_active')
              ->comment('National CCMAS mandatory-baseline percentage, configurable');
        });
    }

    public function down(): void {
        Schema::table('ccmas_courses', function (Blueprint $t) {
            $t->dropColumn(['semester','course_type','is_mandatory']);
        });
        Schema::table('curriculum_versions', function (Blueprint $t) {
            $t->dropColumn('ccmas_baseline_percentage');
        });
    }
};
