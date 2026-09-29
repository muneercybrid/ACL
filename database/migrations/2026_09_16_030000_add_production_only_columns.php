<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 *  * Adds the columns that production has and the migration history never
 * created.
 *
 * These came from schema work applied to the live database rather than
 * committed as migrations, which is why a fresh build diverged from
 * production. Without them a fresh database is missing, among others,
 * `courses.normalized_code` and `users.force_password_change` — columns the
 * application itself depends on, and the first of which
 * 2026_09_17_000000 queries.
 *
 * Definitions are taken from the live production schema, so a database built
 * from migrations matches the one the application serves.
 *
 * Placed here — after the base tables exist, before the first migration that
 * reads one of these columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('institution_id')->nullable();
            $table->string('avatar_path', 255)->nullable();
            $table->boolean('force_password_change')->default(0);
            $table->string('provider', 255)->nullable();
            $table->string('provider_id', 255)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('suspended_at')->nullable();
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->string('short_name', 50)->nullable();
            $table->string('state', 255)->nullable();
            $table->string('website', 255)->nullable();
            $table->text('address')->nullable();
            $table->string('logo_path', 255)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('onboarded_at')->nullable();
            $table->string('email', 255)->nullable();
        });

        Schema::table('academic_programs', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('nuc_programme_id')->nullable();
        });

        Schema::table('academic_sessions', function (Blueprint $table) {
            $table->year('start_year')->nullable();
            $table->year('end_year')->nullable();
            $table->boolean('is_current')->default(0);
            $table->string('status', 20)->default('active');
        });

        Schema::table('semesters', function (Blueprint $table) {
            /* UNMAPPED tinyint for number */
            $table->boolean('is_current')->default(0);
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->string('normalized_code', 30);
            $table->text('short_description')->nullable();
            $table->string('normalized_title', 255)->nullable();
            $table->string('status', 20)->default('active');
            $table->unsignedBigInteger('nuc_discipline_id')->nullable();
            $table->text('source_document')->nullable();
            $table->integer('source_page')->nullable();
            $table->date('date_verified')->nullable();
        });

        Schema::table('nuc_disciplines', function (Blueprint $table) {
            $table->text('description')->nullable();
            $table->string('nuc_document', 255)->nullable();
        });

        Schema::table('curriculum_versions', function (Blueprint $table) {
            $table->text('description')->nullable();
            $table->date('effective_date')->nullable();
            $table->date('expiry_date')->nullable();
        });

        Schema::table('curriculum_courses', function (Blueprint $table) {
            /* UNMAPPED enum('physical','online','blended','hybrid') for delivery_mode */
            $table->boolean('is_mandatory')->default(1);
        });

        Schema::table('user_consents', function (Blueprint $table) {
            $table->string('version', 255)->default('1.0');
        });

        Schema::table('states', function (Blueprint $table) {
            $table->string('country', 50)->default('Nigeria');
        });

    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('institution_id');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar_path');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('force_password_change');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('provider');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('provider_id');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('status');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('suspended_at');
        });
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('short_name');
        });
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('state');
        });
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('website');
        });
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('address');
        });
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('logo_path');
        });
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('status');
        });
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('onboarded_at');
        });
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('email');
        });
        Schema::table('academic_programs', function (Blueprint $table) {
            $table->dropColumn('organization_id');
        });
        Schema::table('academic_programs', function (Blueprint $table) {
            $table->dropColumn('nuc_programme_id');
        });
        Schema::table('academic_sessions', function (Blueprint $table) {
            $table->dropColumn('start_year');
        });
        Schema::table('academic_sessions', function (Blueprint $table) {
            $table->dropColumn('end_year');
        });
        Schema::table('academic_sessions', function (Blueprint $table) {
            $table->dropColumn('is_current');
        });
        Schema::table('academic_sessions', function (Blueprint $table) {
            $table->dropColumn('status');
        });
        Schema::table('semesters', function (Blueprint $table) {
            $table->dropColumn('number');
        });
        Schema::table('semesters', function (Blueprint $table) {
            $table->dropColumn('is_current');
        });
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('normalized_code');
        });
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('short_description');
        });
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('normalized_title');
        });
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('status');
        });
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('nuc_discipline_id');
        });
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('source_document');
        });
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('source_page');
        });
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('date_verified');
        });
        Schema::table('nuc_disciplines', function (Blueprint $table) {
            $table->dropColumn('description');
        });
        Schema::table('nuc_disciplines', function (Blueprint $table) {
            $table->dropColumn('nuc_document');
        });
        Schema::table('curriculum_versions', function (Blueprint $table) {
            $table->dropColumn('description');
        });
        Schema::table('curriculum_versions', function (Blueprint $table) {
            $table->dropColumn('effective_date');
        });
        Schema::table('curriculum_versions', function (Blueprint $table) {
            $table->dropColumn('expiry_date');
        });
        Schema::table('curriculum_courses', function (Blueprint $table) {
            $table->dropColumn('delivery_mode');
        });
        Schema::table('curriculum_courses', function (Blueprint $table) {
            $table->dropColumn('is_mandatory');
        });
        Schema::table('user_consents', function (Blueprint $table) {
            $table->dropColumn('version');
        });
        Schema::table('states', function (Blueprint $table) {
            $table->dropColumn('country');
        });
    }
};
