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
                $t->foreignId('nuc_discipline_id')->constrained()->cascadeOnDelete();
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
