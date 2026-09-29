<?php

namespace Tests\Feature;

use App\Services\ChapterScaffolder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A placeholder is an empty slot, not an empty chapter.
 *
 * The distinction is the whole point: a scaffolded chapter is a task waiting
 * to be done, while a chapter whose content was written and then lost is a
 * bug. If they look the same in the data, neither can be triaged.
 */
class ChapterScaffolderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_empty_marked_placeholders_for_a_course(): void
    {
        $courseId = $this->makeCourse('TEST101');

        (new ChapterScaffolder)->scaffold(6, 0, 0, 10);

        $rows = DB::table('course_chapters')->where('course_id', $courseId)->get();

        $this->assertCount(6, $rows, 'expected 6 placeholders');

        foreach ($rows as $row) {
            $this->assertTrue((bool) $row->placeholder, 'must be flagged as a placeholder');
            $this->assertNull($row->introduction, 'a placeholder must have no body');
            $this->assertNull($row->summary);
            $this->assertSame('draft', $row->status, 'nothing may be published');
        }
    }

    public function test_scaffolding_twice_does_not_duplicate_chapters(): void
    {
        $courseId = $this->makeCourse('TEST102');

        $scaffolder = new ChapterScaffolder;
        $scaffolder->scaffold(5, 0, 0, 10);
        $second = $scaffolder->scaffold(5, 0, 0, 10);

        $this->assertSame(0, $second['created'], 'a second run must not duplicate');
        $this->assertCount(5, DB::table('course_chapters')->where('course_id', $courseId)->get());
    }

    public function test_positions_are_unique_per_course(): void
    {
        $courseId = $this->makeCourse('TEST103');

        (new ChapterScaffolder)->scaffold(8, 0, 0, 10);

        $positions = DB::table('course_chapters')
            ->where('course_id', $courseId)
            ->orderBy('position')
            ->pluck('position');

        $this->assertSame(
            $positions->unique()->count(),
            $positions->count(),
            'positions must be unique, or the (course_id, position) constraint will reject a later insert'
        );
    }

    /**
     * courses carries NOT NULL columns with no default, such as normalized_code
     * and normalized_title. Building a complete row beats hand-writing a
     * subset and discovering a different missing column each time.
     */
    private function makeCourse(string $code): int
    {
        return DB::table('courses')->insertGetId([
            'code' => $code,
            'normalized_code' => $code,
            'slug' => strtolower($code),
            'title' => 'Test Course ' . $code,
            'normalized_title' => 'test course ' . strtolower($code),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
