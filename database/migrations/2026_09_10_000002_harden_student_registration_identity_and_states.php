<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_registration_verifications', function (Blueprint $table) {
            $table->unsignedSmallInteger('jamb_exam_year')->nullable()->change();
            $table->string('jamb_exam_type', 20)->nullable()->change();
            $table->dateTime('manual_review_requested_at')->nullable()->after('verified_at');
        });

        Schema::table('users', function (Blueprint $table) {
            // One immutable JAMB identity can activate only one ACL account.
            $table->char('jamb_registration_number_hash', 64)->nullable()->unique()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['jamb_registration_number_hash']);
            $table->dropColumn('jamb_registration_number_hash');
        });

        Schema::table('student_registration_verifications', function (Blueprint $table) {
            $table->dropColumn('manual_review_requested_at');
            $table->string('jamb_exam_type', 20)->nullable(false)->change();
            $table->unsignedSmallInteger('jamb_exam_year')->nullable(false)->change();
        });
    }
};
