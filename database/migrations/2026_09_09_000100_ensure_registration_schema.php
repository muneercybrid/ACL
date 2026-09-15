<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('states')) {
            Schema::create('states', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('code', 8)->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('lgas')) {
            Schema::create('lgas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('state_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->timestamps();
                $table->index(['state_id', 'name']);
            });
        }
        if (Schema::hasTable('students')) {
            Schema::table('students', function (Blueprint $table) {
                foreach (['nationality', 'state', 'lga', 'region'] as $col) {
                    if (! Schema::hasColumn('students', $col)) {
                        $table->string($col, 80)->nullable();
                    }
                }
            });
        }
        if (Schema::hasTable('student_external_identities')) {
            Schema::table('student_external_identities', function (Blueprint $table) {
                if (! Schema::hasColumn('student_external_identities', 'provider')) {
                    $table->string('provider', 30)->index();
                }
                if (! Schema::hasColumn('student_external_identities', 'identifier')) {
                    $table->string('identifier', 60)->index();
                }
                if (! Schema::hasColumn('student_external_identities', 'payload')) {
                    $table->json('payload')->nullable();
                }
                if (! Schema::hasColumn('student_external_identities', 'verified_at')) {
                    $table->timestamp('verified_at')->nullable();
                }
            });
        }
    }

    public function down(): void {}
};
