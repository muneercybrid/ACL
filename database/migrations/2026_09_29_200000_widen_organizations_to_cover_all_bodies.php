<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Widens `organizations` so it can hold every kind of body ACL works with.
 *
 * Why
 * ---
 * ACL carried two parallel notions of "the body a user belongs to":
 *
 *   institutions    the NUC register, 500 rows
 *   organizations   the operational entities, 126 rows
 *
 * They were completely disjoint — not one name matched — so the same real-world
 * university could exist as a row in each, and nothing stopped that from being
 * two different universities. Every query had to know which table to ask.
 *
 * The register is not the right model going forward. Third-party bodies —
 * training providers, corporate academies, schools, polytechnics that are not
 * on the NUC list — have to be first-class, and calling that set "institutions"
 * while the rest are "organizations" guarantees the contradiction recurs.
 *
 * So: one table, `organizations`, with an explicit `is_nuc_listed` flag
 * recording whether a given row is a recognised NUC institution. Registration
 * status and the NUC register metadata are properties of a row, not a reason
 * for it to live somewhere else.
 *
 * `normalized_name` carries a UNIQUE index so the two tables' distinguishing
 * guarantee — one row per distinct body — now applies across all of them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            // The register key. Unique so a body cannot be inserted twice under
            // different spellings once both populations share this table.
            $table->string('normalized_name')->nullable()->unique();

            // Whether this body appears on the NUC register. Distinguishes a
            // registered university from a third-party provider without
            // putting the two in separate tables.
            $table->boolean('is_nuc_listed')->default(false)->index();

            // NUC register provenance.
            $table->string('nuc_name')->nullable();
            $table->string('nuc_normalized_name')->nullable();
            $table->string('nuc_section')->nullable();
            $table->string('nuc_source_ref')->nullable();
            $table->date('source_verified_at')->nullable();
            $table->date('synchronized_at')->nullable();
            $table->string('import_batch')->nullable();

            // Register detail carried over from `institutions`.
            $table->string('official_name')->nullable();
            $table->string('abbr', 20)->nullable();
            $table->string('ownership', 30)->nullable();
            $table->year('established_year')->nullable();

            // The register field was named canonical_institution_id because it
            // self-referenced the institutions table. Renamed to match the table
            // that now holds the rows, so the name does not outlive its meaning.
            $table->unsignedBigInteger('canonical_organization_id')->nullable();
            $table->index('canonical_organization_id');

            // Onboarding progress, previously a per-institution enum. A string
            // with the permitted values documented inline, per the Development
            // Constitution section 4.5, which prefers not to use MySQL ENUM.
            $table->string('onboarding_status', 40)->nullable()
                ->comment('NOT_ONBOARDED | INVITED | CREDENTIALS_ISSUED | ADMIN_FIRST_LOGIN | PROFILE_VERIFICATION | ONBOARDING_IN_PROGRESS | ONBOARDED | SUSPENDED');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropIndex(['normalized_name']);
            $table->dropIndex(['is_nuc_listed']);
            $table->dropIndex(['canonical_organization_id']);
            $table->dropColumn([
                'normalized_name', 'is_nuc_listed', 'nuc_name', 'nuc_normalized_name',
                'nuc_section', 'nuc_source_ref', 'source_verified_at', 'synchronized_at',
                'import_batch', 'official_name', 'abbr', 'ownership', 'established_year',
                'canonical_organization_id', 'onboarding_status',
            ]);
        });
    }
};
