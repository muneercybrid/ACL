<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Services\Courses\InstitutionCourseOutlineScaffolder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A school-added course reaches a student with nothing to open.
 *
 * The national catalogue arrived with chapter placeholders already attached.
 * A course the school adds is not in that catalogue, so it had no outline and
 * no chapters, which the student sees as a broken course rather than an
 * unwritten one.
 *
 * The important property is not that content appears. It is that what appears
 * is a *draft* a human still has to approve. A test that only checked for rows
 * would pass just as happily against published, unreviewed material, which is
 * the outcome this must never produce.
 */
class InstitutionCourseOutlineScaffolderTest extends TestCase
{
    use RefreshDatabase;

    protected function institutionCourse(): Course
    {
        $id = DB::table('courses')->insertGetId([
            'code' => 'NUKCYB101', 'normalized_code' => 'NUKCYB101',
            'title' => 'Introduction to Malware & Social Engineering', 'slug' => 'nukcyb101',
            'credit_units' => 3, 'scope' => 'university', 'source_type' => 'institution',
            'verification_status' => 'verified', 'status' => 'active', 'is_active' => 1, 'is_external' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return Course::find($id);
    }

    public function test_it_creates_a_draft_outline_and_placeholder_chapters(): void
    {
        $course = $this->institutionCourse();

        $result = app(InstitutionCourseOutlineScaffolder::class)->scaffoldForCourse($course->id);

        $this->assertSame('created', $result['outline']);
        $this->assertSame(10, $result['chapters']);

        $outline = DB::table('course_outlines')->where('course_id', $course->id)->first();

        $this->assertNotNull($outline);
        $this->assertSame('draft', $outline->status);
    }

    public function test_nothing_is_published_or_approved(): void
    {
        // The whole point. A scaffolded outline must never reach a student as
        // reviewed material, so it carries no approver and no approval time.
        $course = $this->institutionCourse();
        app(InstitutionCourseOutlineScaffolder::class)->scaffoldForCourse($course->id);

        $outline = DB::table('course_outlines')->where('course_id', $course->id)->first();

        $this->assertSame('draft', $outline->status);
        $this->assertNotSame('published', $outline->status);
        $this->assertNotSame('approved', $outline->status);
        $this->assertNull($outline->approved_by);
        $this->assertNull($outline->approved_at);
        $this->assertFalse((bool) $outline->is_locked);

        $chapters = DB::table('course_chapters')->where('course_id', $course->id)->get();
        $this->assertNotEmpty($chapters);

        foreach ($chapters as $chapter) {
            $this->assertSame('draft', $chapter->status);
            $this->assertTrue((bool) $chapter->placeholder, 'chapters must be marked as placeholders');
        }
    }

    public function test_the_outline_says_it_is_awaiting_review(): void
    {
        // A student should not read an unreviewed draft as a finished syllabus.
        $course = $this->institutionCourse();
        app(InstitutionCourseOutlineScaffolder::class)->scaffoldForCourse($course->id);

        $description = DB::table('course_outlines')->where('course_id', $course->id)->value('description');

        $this->assertStringContainsString('draft', strtolower($description));
        $this->assertStringContainsString('not been approved', strtolower($description));
    }

    public function test_it_is_idempotent_and_never_overwrites_a_coordinator(): void
    {
        // Re-running must not duplicate rows or discard work in progress.
        $course = $this->institutionCourse();
        $scaffolder = app(InstitutionCourseOutlineScaffolder::class);

        $scaffolder->scaffoldForCourse($course->id);
        $scaffolder->scaffoldForCourse($course->id);

        $this->assertSame(1, DB::table('course_outlines')->where('course_id', $course->id)->count());
        $this->assertSame(10, DB::table('course_chapters')->where('course_id', $course->id)->count());

        // A coordinator's edit survives.
        DB::table('course_outlines')->where('course_id', $course->id)
            ->update(['description' => 'Written by the school.']);

        $scaffolder->scaffoldForCourse($course->id);

        $this->assertSame(
            'Written by the school.',
            DB::table('course_outlines')->where('course_id', $course->id)->value('description')
        );
    }

    public function test_it_only_touches_courses_the_school_added(): void
    {
        // The national catalogue is not this service's business.
        $nationalId = DB::table('courses')->insertGetId([
            'code' => 'COS101', 'normalized_code' => 'COS101', 'title' => 'Introduction to Computing Sciences',
            'slug' => 'cos101', 'credit_units' => 3, 'scope' => 'national', 'source_type' => 'nuc_ccmas',
            'verification_status' => 'verified', 'status' => 'active', 'is_active' => 1, 'is_external' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->institutionCourse();

        $result = app(InstitutionCourseOutlineScaffolder::class)->scaffoldPending();

        $this->assertSame(1, $result['scaffolded']);
        $this->assertSame(0, DB::table('course_outlines')->where('course_id', $nationalId)->count());
    }
}
