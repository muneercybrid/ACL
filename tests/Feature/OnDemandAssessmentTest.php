<?php

namespace Tests\Feature;

use App\Services\Courses\OnDemandAssessmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A student's assessment is written when they ask for it, and written for them
 * alone.
 *
 * The previous approach fixed one set of questions per chapter for everyone,
 * which both cost a full course's assessment material whether or not anyone
 * opened it and put the same paper in front of every student.
 */
class OnDemandAssessmentTest extends TestCase
{
    use RefreshDatabase;

    protected function chapterWithContent(): int
    {
        $courseId = DB::table('courses')->insertGetId([
            'code' => 'COS101', 'normalized_code' => 'COS101', 'title' => 'Introduction to Computing Sciences',
            'slug' => 'cos101', 'scope' => 'national', 'source_type' => 'nuc_ccmas',
            'verification_status' => 'verified', 'status' => 'active', 'is_external' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $sessionId = DB::table('academic_sessions')->insertGetId([
            'name' => '2026/2027', 'slug' => '2026-2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31',
            'is_active' => true, 'is_current' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $semesterId = DB::table('semesters')->insertGetId([
            'academic_session_id' => $sessionId, 'name' => 'First Semester', 'slug' => 'first-semester',
            'start_date' => '2026-09-01', 'end_date' => '2027-07-31', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $offeringId = DB::table('course_offerings')->insertGetId([
            'course_id' => $courseId, 'semester_id' => $semesterId, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('course_chapters')->insertGetId([
            'course_id' => $courseId, 'position' => 1, 'title' => 'Basic Components of Computers',
            'slug' => 'basic-components', 'placeholder' => 0, 'status' => 'draft',
            'introduction' => 'A computer is built from hardware and software. Hardware is the physical parts you can touch.',
            'summary' => 'Hardware and software work together.',
            'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function student(): int
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Test Student', 'email' => 'student-' . uniqid() . '@acl.test',
            'password' => bcrypt('secret1234'), 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('students')->insertGetId([
            'user_id' => $userId, 'acl_student_id' => 'ACL-' . $userId, 'level' => 100,
            'verification_status' => 'verified', 'verification_method' => 'jamb',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_two_students_get_different_seeds_for_the_same_chapter(): void
    {
        $service = app(OnDemandAssessmentService::class);
        $method = new \ReflectionMethod($service, 'seed');
        $method->setAccessible(true);

        // The whole point: a shared chapter, two different papers. The seed is
        // what varies, and it must vary by student as well as by chapter.
        $a = $method->invoke($service, 1, 99, 5);
        $b = $method->invoke($service, 2, 99, 5);

        $this->assertNotSame($a, $b);
    }

    public function test_the_seed_is_stable_for_the_same_student_and_chapter(): void
    {
        $service = app(OnDemandAssessmentService::class);
        $method = new \ReflectionMethod($service, 'seed');
        $method->setAccessible(true);

        // Re-requesting must not reshuffle a set the student has already seen,
        // or a student returning to an attempt would face a different paper.
        $this->assertSame(
            $method->invoke($service, 1, 99, 5),
            $method->invoke($service, 1, 99, 5)
        );
    }

    public function test_it_refuses_to_invent_questions_for_an_unwritten_chapter(): void
    {
        $chapterId = $this->chapterWithContent();

        // Strip the chapter back to having no teaching text.
        DB::table('course_chapters')->where('id', $chapterId)
            ->update(['introduction' => null, 'summary' => null, 'key_takeaways' => null]);

        $studentId = $this->student();

        $result = app(OnDemandAssessmentService::class)->forStudent($studentId, $chapterId);

        // Questions can only be asked about something the chapter taught.
        $this->assertSame([], $result['questions']);
        $this->assertSame(0, DB::table('assessment_questions')->count());
    }

    public function test_a_generated_set_is_stored_against_that_student_only(): void
    {
        $chapterId = $this->chapterWithContent();
        $studentId = $this->student();

        // Seed a stored set directly so the test does not depend on the model.
        $assessmentId = DB::table('assessments')->insertGetId([
            'course_id' => DB::table('course_chapters')->where('id', $chapterId)->value('course_id'),
            'chapter_id' => $chapterId, 'title' => 'Chapter assessment', 'scope' => 'chapter',
            'passing_score' => 50, 'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('assessment_questions')->insert([
            'assessment_id' => $assessmentId, 'position' => 1, 'question_type' => 'mcq',
            'question' => 'What is hardware?', 'options' => json_encode(['Parts you can touch', 'A program']),
            'correct_answer' => 'Parts you can touch', 'explanation' => 'It is physical.',
            'marks' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('assessment_attempts')->insert([
            'student_id' => $studentId, 'assessment_id' => $assessmentId,
            'course_id' => DB::table('course_chapters')->where('id', $chapterId)->value('course_id'),
            'chapter_id' => $chapterId, 'total_marks' => 0, 'score_earned' => 0,
            'percentage' => 0, 'passed' => false, 'status' => 'in_progress',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $result = app(OnDemandAssessmentService::class)->forStudent($studentId, $chapterId);

        $this->assertTrue($result['reused'], 'a student must keep the set they were given');
        $this->assertCount(1, $result['questions']);

        // Another student sees nothing of it.
        $otherStudent = $this->student();
        $other = app(OnDemandAssessmentService::class)->forStudent($otherStudent, $chapterId);

        $this->assertFalse($other['reused']);
    }

    public function test_questions_that_cannot_be_marked_are_dropped(): void
    {
        $service = app(OnDemandAssessmentService::class);
        $method = new \ReflectionMethod($service, 'parse');
        $method->setAccessible(true);

        $chapter = (object) ['id' => 1, 'title' => 'T'];

        $questions = $method->invoke($service, json_encode([
            ['question' => 'Good one', 'options' => ['a', 'b'], 'correct_answer' => 'a'],
            ['question' => 'Only one option', 'options' => ['a'], 'correct_answer' => 'a'],
            ['question' => '', 'options' => ['a', 'b']],
        ]), $chapter);

        // A single-option question cannot be marked, so it must not be shown.
        $this->assertCount(1, $questions);
        $this->assertSame('Good one', $questions[0]['question']);
    }
}