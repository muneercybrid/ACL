<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds scope columns to role_assignments.
 *
 * Why
 * ---
 * Authorization in ACL is Authentication -> Role -> Permission -> Scope ->
 * Capability. `entity_type`/`entity_id` carry the scope, but a single id can
 * only express "on this thing" — not "on this level of this thing".
 *
 * A level coordinator is the case that needs it. A 100-level coordinator must
 * reach 100 level of Computer Science and nothing else: not 200 level, and not
 * another programme. Encoding that in `entity_id` is impossible, so the scope
 * was either unenforced or smuggled in as a string inside another column.
 *
 * These columns make the level explicit, so the same authorization check
 * applies uniformly whether the scope is an organization, a programme, or a
 * level of a programme.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('role_assignments', function (Blueprint $table) {
            $table->string('scope_type', 40)->nullable()->after('entity_id')
                ->comment('organization | programme | level | platform');
            $table->string('scope_id', 64)->nullable()->after('scope_type')
                ->comment('The level or sub-scope within entity_id, when narrower than the entity itself');
        });
    }

    public function down(): void
    {
        Schema::table('role_assignments', function (Blueprint $table) {
            $table->dropColumn(['scope_type', 'scope_id']);
        });
    }
};
