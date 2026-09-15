<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nuc_disciplines')) {
            Schema::create('nuc_disciplines', function (Blueprint $t) {
                $t->id();
                $t->string('code', 10)->unique();
                $t->string('name');
                $t->string('status', 20)->default('active');
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('academic_sessions')) {
            Schema::create('academic_sessions', function (Blueprint $t) {
                $t->id();
                $t->string('name');
                $t->string('slug')->nullable();
                $t->integer('start_year')->nullable();
                $t->integer('end_year')->nullable();
                $t->boolean('is_current')->default(false);
                $t->string('status', 20)->default('active');
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('curriculum_versions')) {
            Schema::create('curriculum_versions', function (Blueprint $t) {
                $t->id();
                $t->foreignId('programme_id')->constrained()->cascadeOnDelete();
                $t->unsignedBigInteger('academic_session_id')->nullable();
                $t->string('version_label');
                $t->string('slug')->nullable();
                $t->string('scope', 30)->default('nuc_baseline');
                $t->string('verification_status', 30)->default('verified');
                $t->string('source_type', 30)->nullable();
                $t->string('source_document')->nullable();
                $t->boolean('is_active')->default(true);
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('curriculum_courses')) {
            Schema::create('curriculum_courses', function (Blueprint $t) {
                $t->id();
                $t->foreignId('curriculum_version_id')->constrained()->cascadeOnDelete();
                $t->foreignId('course_id')->constrained()->cascadeOnDelete();
                $t->integer('level')->default(100);
                $t->integer('semester')->nullable();
                $t->string('course_type', 40)->default('core');
                $t->integer('credit_units')->default(0);
                $t->string('status', 20)->default('active');
                $t->timestamps();
            });
        }

        if (Schema::hasTable('programmes') && ! Schema::hasColumn('programmes', 'nuc_discipline_id')) {
            Schema::table('programmes', function (Blueprint $t) {
                $t->unsignedBigInteger('nuc_discipline_id')->nullable()->index();
            });
        }
    }

    public function down(): void {}
};
