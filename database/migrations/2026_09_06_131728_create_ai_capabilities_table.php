<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_capabilities', function (Blueprint $table) {
            $table->id();
            $table->string('capability_key')->unique(); // e.g., student.ask, tutor.analyze_students
            $table->string('name'); // Human-readable name
            $table->text('description')->nullable();
            $table->string('role_required'); // e.g., student, tutor, teacher, department_admin
            $table->boolean('require_paid_subscription')->default(false); // Students MUST have paid subscription
            $table->boolean('require_ai_entitlement')->default(true);
            $table->string('scope')->default('student'); // student, tutor, department, institution, platform
            $table->string('usage_limit_type')->nullable(); // daily, monthly, per_session, none
            $table->string('usage_limit_value')->nullable(); // e.g., 10, 50, etc.
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Insert default capability definitions
        \App\Models\AiCapability::insert([
            ['capability_key' => 'student.ask', 'name' => 'Ask ACL AI', 'role_required' => 'student', 'require_paid_subscription' => true, 'scope' => 'student'],
            ['capability_key' => 'student.chat', 'name' => 'Chat with ACL AI', 'role_required' => 'student', 'require_paid_subscription' => true, 'scope' => 'student'],
            ['capability_key' => 'student.tutor', 'name' => 'AI Tutor', 'role_required' => 'student', 'require_paid_subscription' => true, 'scope' => 'student'],
            ['capability_key' => 'student.highlight_explain', 'name' => 'Explain highlighted text', 'role_required' => 'student', 'require_paid_subscription' => true, 'scope' => 'student'],
            ['capability_key' => 'student.equation_explain', 'name' => 'Explain equation', 'role_required' => 'student', 'require_paid_subscription' => true, 'scope' => 'student'],
            ['capability_key' => 'student.code_explain', 'name' => 'Explain code', 'role_required' => 'student', 'require_paid_subscription' => true, 'scope' => 'student'],
            ['capability_key' => 'student.quiz_generate', 'name' => 'Generate quiz', 'role_required' => 'student', 'require_paid_subscription' => true, 'scope' => 'student'],
            ['capability_key' => 'student.question_generate', 'name' => 'Generate questions', 'role_required' => 'student', 'require_paid_subscription' => true, 'scope' => 'student'],
            ['capability_key' => 'student.flashcard_generate', 'name' => 'Generate flashcards', 'role_required' => 'student', 'require_paid_subscription' => true, 'scope' => 'student'],
            ['capability_key' => 'student.study_plan', 'name' => 'Create study plan', 'role_required' => 'student', 'require_paid_subscription' => true, 'scope' => 'student'],
            ['capability_key' => 'student.performance_analysis', 'name' => 'Performance analysis', 'role_required' => 'student', 'require_paid_subscription' => true, 'scope' => 'student'],
            ['capability_key' => 'student.course_analysis', 'name' => 'Course analysis', 'role_required' => 'student', 'require_paid_subscription' => true, 'scope' => 'student'],
            ['capability_key' => 'student.summarize', 'name' => 'Summarize', 'role_required' => 'student', 'require_paid_subscription' => true, 'scope' => 'student'],
            ['capability_key' => 'student.translate', 'name' => 'Translate', 'role_required' => 'student', 'require_paid_subscription' => true, 'scope' => 'student'],
            ['capability_key' => 'student.image_generate', 'name' => 'Generate image', 'role_required' => 'student', 'require_paid_subscription' => true, 'scope' => 'student'],
            ['capability_key' => 'student.presentation_generate', 'name' => 'Generate presentation', 'role_required' => 'student', 'require_paid_subscription' => true, 'scope' => 'student'],
            ['capability_key' => 'tutor.analyze_students', 'name' => 'Analyze students', 'role_required' => 'tutor', 'require_paid_subscription' => false, 'scope' => 'tutor'],
            ['capability_key' => 'tutor.generate_lessons', 'name' => 'Generate lessons', 'role_required' => 'tutor', 'require_paid_subscription' => false, 'scope' => 'tutor'],
            ['capability_key' => 'teacher.difficulty', 'name' => 'Identify struggling students', 'role_required' => 'teacher', 'require_paid_subscription' => false, 'scope' => 'teacher'],
            ['capability_key' => 'department.analytics', 'name' => 'Department analytics', 'role_required' => 'department_admin', 'require_paid_subscription' => false, 'scope' => 'department'],
            ['capability_key' => 'institution.summary', 'name' => 'Institution summary', 'role_required' => 'institution_admin', 'require_paid_subscription' => false, 'scope' => 'institution'],
            ['capability_key' => 'platform.operations', 'name' => 'Platform operations', 'role_required' => 'super_admin', 'require_paid_subscription' => false, 'scope' => 'platform'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_capabilities');
    }
};