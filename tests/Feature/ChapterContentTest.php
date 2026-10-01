<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The course page carries only short previews; the full explanation is fetched
 * when a student asks for it.
 */
class ChapterContentTest extends TestCase
{
    use RefreshDatabase;

    protected function enrolledStudent(): array
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Test Student', 'email' => 'student-' . uniqid() . '@acl.test',
            'password' => bcrypt('secret1234'), 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $studentId = DB::table('students')->insertGetId([
            'user_id' => $userId, 'acl_student_id' => 'ACL-' . $userId, 'level' => 100,
            'verification_status' => 'verified', 'verification_method' => 'jamb',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $courseId = DB::table('courses')->insertGetId([
            'code' => 'COS101', 'normalized_code' => 'COS101', 'title' => 'Introduction to Computing Sciences',
            'slug' => 'cos101', 'scope' => 'national', 'source_type' => 'nuc_ccmas',
            'verification_status' => 'verified', 'status' => 'active', 'is_external' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $sessionId = DB::table('academic_sessions')->insertGetId([
            'name' => '2026/2027', 'slug' => '2026-2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31',
            'is_current' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
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

        DB::table('enrollments')->insert([
            'user_id' => $userId, 'course_offering_id' => $offeringId, 'source' => 'self',
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $chapterId = DB::table('course_chapters')->insertGetId([
            'course_id' => $courseId, 'position' => 1, 'title' => 'Basic Components of Computers',
            'slug' => 'basic-components', 'placeholder' => 0, 'status' => 'draft',
            'introduction' => str_repeat('A computer is built from hardware and software. ', 12),
            'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$userId, $studentId, $chapterId];
    }

    public function test_an_enrolled_student_can_fetch_the_full_chapter_text(): void
    {
        [$userId, , $chapterId] = $this->enrolledStudent();

        $this->actingAs(User::find($userId))
            ->getJson('/student/chapter/' . $chapterId . '/content')
            ->assertOk()
            ->assertJsonStructure(['introduction', 'summary', 'key_takeaways']);
    }

    public function test_a_student_not_enrolled_cannot_read_a_chapter_by_id(): void
    {
        [, , $chapterId] = $this->enrolledStudent();

        $outsiderId = DB::table('users')->insertGetId([
            'name' => 'Outsider', 'email' => 'out-' . uniqid() . '@acl.test',
            'password' => bcrypt('secret1234'), 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('students')->insert([
            'user_id' => $outsiderId, 'acl_student_id' => 'ACL-O' . $outsiderId, 'level' => 100,
            'verification_status' => 'verified', 'verification_method' => 'jamb',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Otherwise the endpoint would let any student read any chapter by
        // guessing its id.
        $this->actingAs(User::find($outsiderId))
            ->getJson('/student/chapter/' . $chapterId . '/content')
            ->assertForbidden();
    }
}
