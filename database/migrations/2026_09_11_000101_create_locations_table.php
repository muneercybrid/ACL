<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('locations')) {
            Schema::create('locations', function (Blueprint $table) {
                $table->id();
                $table->string('country', 60)->default('Nigeria');
                $table->string('state_code', 8);
                $table->string('state', 80);
                $table->string('lga', 120);
                $table->timestamps();
                $table->unique(['country', 'state', 'lga']);
                $table->index('state');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
