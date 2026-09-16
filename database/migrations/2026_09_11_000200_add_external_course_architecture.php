<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $t) {
            if (! Schema::hasColumn('courses', 'is_external')) {
                $t->boolean('is_external')->default(false)->index();
            }
            if (! Schema::hasColumn('courses', 'difficulty')) {
                $t->string('difficulty', 20)->nullable();
            }
        });

        if (! Schema::hasTable('course_disciplines')) {
            Schema::create('course_disciplines', function (Blueprint $t) {
                $t->id();
                $t->foreignId('course_id')->constrained()->cascadeOnDelete();
                // Loose reference: nuc_disciplines is created by a later
                // migration (2026_09_12_000300), so a hard FK here would break
                // fresh-database migrations. The relationship is enforced by
                // Eloquent's belongsToMany and the unique pair below.
                $t->unsignedBigInteger('nuc_discipline_id')->index();
                $t->unique(['course_id', 'nuc_discipline_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('course_disciplines');
        Schema::table('courses', function (Blueprint $t) {
            if (Schema::hasColumn('courses', 'is_external')) $t->dropColumn('is_external');
            if (Schema::hasColumn('courses', 'difficulty')) $t->dropColumn('difficulty');
        });
    }
};
