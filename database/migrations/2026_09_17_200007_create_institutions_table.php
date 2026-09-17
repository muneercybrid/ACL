<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('institutions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('normalized_name', 255)->nullable();
            $table->enum('ownership', ['Federal','State','Private'])->default('Private');
            $table->string('state', 100)->nullable();
            $table->year('established_year')->nullable();
            $table->string('website')->nullable();
            $table->string('nuc_source_ref')->nullable();
            $table->enum('institution_status', ['ACTIVE','INACTIVE','SUSPENDED','ARCHIVED'])->default('ACTIVE');
            $table->enum('onboarding_status', ['NOT_ONBOARDED','INVITED','CREDENTIALS_ISSUED','ADMIN_FIRST_LOGIN','PROFILE_VERIFICATION','ONBOARDING_IN_PROGRESS','ONBOARDED','SUSPENDED'])->default('NOT_ONBOARDED');
            $table->string('slug', 255)->nullable();
            $table->string('import_batch')->nullable();
            $table->dateTime('synchronized_at')->nullable();
            $table->timestamps();
            $table->index(['ownership','institution_status','onboarding_status']);
            $table->unique(['normalized_name']);
        });
    }
    public function down(): void { Schema::dropIfExists('institutions'); }
};
