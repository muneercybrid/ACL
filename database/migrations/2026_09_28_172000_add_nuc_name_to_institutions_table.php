<?php

use App\Services\InstitutionNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the NUC-published name alongside ACL's own name.
 *
 * Why this is needed
 * ------------------
 * `institutions.name` and `institutions.normalized_name` carry the name ACL
 * uses. The NUC reference list spells many of the same institutions
 * differently:
 *
 *   ACL      "University of Lagos, Lagos"                 "Michael Okpara University of Agriculture, Umudike"
 *   NUC      "University of Lagos"                        "Michael Okpara University of Agricultural Umudike"
 *   ACL      "Federal University, Dutsin-Ma, Katsina State"
 *   NUC      "Federal University, Dutsin-Ma, Katsina"
 *
 * Matching on `normalized_name` alone therefore missed 191 institutions that
 * were already present, and a naive import would have created a duplicate for
 * each. Recording the name exactly as NUC publishes it makes the second
 * reconciliation pass an exact lookup instead of a similarity guess, which is
 * what makes the reconcile command genuinely idempotent.
 *
 * `nuc_normalized_name` is deliberately NOT unique: the NUC list repeats some
 * affiliated colleges under more than one base university, so two ACL
 * institutions may legitimately carry the same published name.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('institutions')) {
            return;
        }

        Schema::table('institutions', function (Blueprint $table) {
            // Name exactly as published by the NUC, quirks preserved.
            $table->string('nuc_name', 255)->nullable()->after('name');

            // The same value through InstitutionNormalizer, for exact lookup.
            $table->string('nuc_normalized_name', 255)->nullable()->after('nuc_name');
        });

        Schema::table('institutions', function (Blueprint $table) {
            $table->index('nuc_normalized_name', 'institutions_nuc_normalized_idx');
        });

        // Seed the column from the names ACL already holds, so existing rows
        // participate in NUC matching from the first run.
        $normalizer = app(InstitutionNormalizer::class);

        DB::table('institutions')->whereNull('nuc_name')->chunkById(200, function ($rows) use ($normalizer) {
            foreach ($rows as $row) {
                DB::table('institutions')->where('id', $row->id)->update([
                    'nuc_name' => $row->name,
                    'nuc_normalized_name' => $normalizer->name((string) $row->name),
                ]);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('institutions')) {
            return;
        }

        Schema::table('institutions', function (Blueprint $table) {
            $table->dropIndex('institutions_nuc_normalized_idx');
        });

        Schema::table('institutions', function (Blueprint $table) {
            $table->dropColumn(['nuc_name', 'nuc_normalized_name']);
        });
    }
};
