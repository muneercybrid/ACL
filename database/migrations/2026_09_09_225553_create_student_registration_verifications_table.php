<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_registration_verifications', function (Blueprint $table) {
            $table->id();

            /*
             * Server-side onboarding reference.
             * This is what allows a successful JAMB verification to be
             * continued into account creation without trusting browser data.
             */
            $table->uuid('token')->unique();

            /*
             * The user is null until account creation succeeds.
             */
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            /*
             * Verification lifecycle:
             * pending | verified | failed | used | expired
             */
            $table->string('status')->default('pending');
            $table->index('status');

            /*
             * JAMB examination selected by the student.
             * Do not infer this from the registration number.
             */
            $table->unsignedSmallInteger('jamb_exam_year');
            $table->string('jamb_exam_type', 20)->default('UTME');
            $table->string('jamb_exam_value', 10);

            /*
             * The actual JAMB registration number is encrypted at the
             * application layer. The hash allows duplicate detection
             * without exposing the plaintext value to database queries.
             */
            $table->text('jamb_registration_number');
            $table->char('jamb_registration_number_hash', 64)->index('jamb_reg_hash_idx');

            /*
             * Information returned by JAMB/Zyte.
             */
            $table->string('verified_name')->nullable();
            $table->text('verified_institution')->nullable();
            $table->text('verified_programme')->nullable();
            $table->text('jamb_status')->nullable();

            /*
             * ACL's server-side academic mapping.
             * These are nullable because a JAMB result may verify
             * successfully but require manual mapping.
             */
            $table->foreignId('organization_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('academic_program_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            /*
             * Preserve non-authoritative provider response information
             * for audit/debugging without putting it into the user record.
             */
            $table->json('verification_metadata')->nullable();

            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['jamb_exam_year', 'status']);
            $table->index(['organization_id', 'academic_program_id'], 'org_program_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_registration_verifications');
    }
};
