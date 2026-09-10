<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_registration_verifications', function (Blueprint $table) {
            $table->string('school_registration_number', 100)
                ->nullable()
                ->after('jamb_registration_number_hash');
        });
    }

    public function down(): void
    {
        Schema::table('student_registration_verifications', function (Blueprint $table) {
            $table->dropColumn('school_registration_number');
        });
    }
};
