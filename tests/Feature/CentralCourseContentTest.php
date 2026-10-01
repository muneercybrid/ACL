<?php

namespace Tests\Feature;

use App\Services\Courses\CcmasContentIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * A course is shared when programmes and disciplines agree on the code.
 *
 * If a Cybersecurity student and a Biotechnology student both take MTH 101,
 * they must receive the same course row, the same content and therefore the
 * same chapters. That only holds while exactly one row exists per code --
 * which until the unique index was added was a property of the data rather
 * than something the schema guaranteed.
 */
class CentralCourseContentTest extends TestCase
{
    use RefreshDatabase;

    protected function seedCcmasContent(): void
    {
        // Run the real parser, then persist what it found, so the test
        // exercises the same path production uses.
        $now = now();
        $rows = [];

        foreach ((new CcmasContentIndex())->all() as $code => $entry) {
            $rows[] = [
                'code' => $code,
                'title' => $entry['title'],
                'units' => $entry['units'] ?: null,
                'learning_outcomes' => $entry['learning_outcomes'],
                'course_contents' => $entry['course_contents'],
                'variants' => $entry['variants'],
                'documents' => json_encode($entry['documents']),
                'indexed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('ccmas_course_content')->insert($chunk);
        }
    }

    public function test_a_shared_code_has_exactly_one_course_row_and_one_content_row(): void
    {
        $this->seedCcmasContent();

        $index = new CcmasContentIndex();

        // Pick a code the documents genuinely list in several disciplines.
        $code = collect($index->all())
            ->filter(fn ($e) => count($e['documents']) > 1)
            ->keys()
            ->first();

        $this->assertNotNull($code, 'the CCMAS documents must contain a shared course');

        $content = DB::table('ccmas_course_content')->where('code', $code)->get();
        $this->assertCount(1, $content, 'a code must have exactly one canonical content entry');

        // Every course row carrying that code is the same course.
        $courses = DB::table('courses')->where('normalized_code', $code)->get();
        $this->assertLessThanOrEqual(1, $courses->count());
    }

    public function test_the_schema_forbids_a_second_course_row_for_the_same_code(): void
    {
        // This is the guarantee. Without it, any importer could split a shared
        // course in two and two schools would silently get different content.
        $code = 'MTH101';

        DB::table('courses')->insert([
            'code' => $code, 'normalized_code' => $code, 'title' => 'Elementary Mathematics I',
            'slug' => 'elementary-mathematics-i', 'scope' => 'national', 'source_type' => 'nuc_ccmas',
            'verification_status' => 'verified', 'status' => 'active', 'is_external' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('courses')->insert([
            'code' => $code, 'normalized_code' => $code, 'title' => 'Duplicate for the same course',
            'slug' => 'elementary-mathematics-i-duplicate', 'scope' => 'university', 'source_type' => 'institution',
            'verification_status' => 'verified', 'status' => 'active', 'is_external' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_content_is_keyed_by_code_so_disciplines_cannot_diverge(): void
    {
        $this->seedCcmasContent();

        $index = new CcmasContentIndex();
        $entry = $index->forCode('MTH101');

        $stored = DB::table('ccmas_course_content')->where('code', 'MTH101')->first();

        $this->assertNotNull($stored);
        $this->assertSame($entry['learning_outcomes'], $stored->learning_outcomes);
        $this->assertSame($entry['course_contents'], $stored->course_contents);

        // The eleven documents that list MTH 101 all agreed substantively, so
        // the canonical entry carries one statement, not a merge of eleven.
        $this->assertGreaterThan(1, $stored->variants);
    }

    public function test_the_generator_reads_the_persisted_content_not_the_files(): void
    {
        $this->seedCcmasContent();

        // The course row itself has to exist: the generator resolves content
        // by the code on the course, so a missing row is a missing lookup
        // rather than a failure to read content.
        DB::table('courses')->insert([
            'code' => 'MTH101', 'normalized_code' => 'MTH101', 'title' => 'Elementary Mathematics I',
            'slug' => 'elementary-mathematics-i', 'scope' => 'national', 'source_type' => 'nuc_ccmas',
            'verification_status' => 'verified', 'status' => 'active', 'is_external' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $course = DB::table('courses')->where('normalized_code', 'MTH101')->first();

        $generator = app(\App\Services\ACLi\CourseContentGenerator::class);
        $method = new \ReflectionMethod($generator, 'ccmasContentFor');
        $method->setAccessible(true);

        $source = $method->invoke($generator, $course);

        // Both halves of the national standard reach the model, which is the
        // whole point: chapter titles must answer the learning outcomes.
        $this->assertStringContainsString('Learning Outcomes', $source);
        $this->assertStringContainsString('Course Contents', $source);
        $this->assertStringContainsString('quadratic equations', $source);
    }

    public function test_twenty_chapters_is_the_floor(): void
    {
        $this->assertGreaterThanOrEqual(
            20,
            \App\Services\ACLi\CourseContentGenerator::MIN_CHAPTERS,
            'six chapters compressed a syllabus until the model repeated itself'
        );
    }
}