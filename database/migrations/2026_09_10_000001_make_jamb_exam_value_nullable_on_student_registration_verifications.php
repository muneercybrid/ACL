<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The verification row is created before the provider is called, so the
     * examination option value (only known once JAMB's portal has been read,
     * and unknown entirely when the provider fails) cannot be present at
     * insert time. The NOT NULL constraint made every audit row fail under
     * strict mode.
     */
    public function up(): void
    {
        Schema::table('student_registration_verifications', function (Blueprint $table) {
            $table->string('jamb_exam_value', 10)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('student_registration_verifications', function (Blueprint $table) {
            $table->string('jamb_exam_value', 10)->nullable(false)->change();
        });
    }
};
