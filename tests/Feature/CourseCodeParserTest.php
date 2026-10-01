<?php

namespace Tests\Feature;

use App\Services\Courses\CourseCodeParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A course code states its level and semester, and the convention is worth
 * pinning down because semester placement drives which courses a student sees.
 *
 * Nigerian universities number courses by level and semester in one three-digit
 * block: the leading digit is the level, the parity of the trailing digit is the
 * semester. The National Open University of Nigeria documents it as a policy
 * rather than a local habit.
 *
 * These tests encode the convention, and equally the two places it is known to
 * be wrong -- a course whose stored level contradicts its own code, and a course
 * whose documented semester contradicts parity. Neither may be silently
 * smoothed over: the first is left alone for a human to resolve, the second
 * records the divergence while still honouring the document.
 */
class CourseCodeParserTest extends TestCase
{
    use RefreshDatabase;

    protected CourseCodeParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new CourseCodeParser();
    }

    public function test_it_reads_level_from_the_leading_digit(): void
    {
        $this->assertSame(100, $this->parser->level('COS 101'));
        $this->assertSame(200, $this->parser->level('COS 201'));
        $this->assertSame(300, $this->parser->level('CYB 301'));
        $this->assertSame(400, $this->parser->level('CYB 401'));
        $this->assertSame(500, $this->parser->level('CSC 501'));
        $this->assertSame(600, $this->parser->level('CSC 601'));
    }

    public function test_it_reads_semester_from_trailing_digit_parity(): void
    {
        $this->assertSame(1, $this->parser->semester('COS 101'), 'odd trailing digit is first semester');
        $this->assertSame(2, $this->parser->semester('COS 102'), 'even trailing digit is second semester');
        $this->assertSame(1, $this->parser->semester('PHY 107'));
        $this->assertSame(2, $this->parser->semester('PHY 108'));
    }

    public function test_it_handles_the_registered_course_registration_form(): void
    {
        // The exact codes from the Northwest University Kano form.
        $this->assertSame([100, 1], [$this->parser->level('COS101'), $this->parser->semester('COS101')]);
        $this->assertSame([100, 2], [$this->parser->level('GST112'), $this->parser->semester('GST112')]);
        $this->assertSame([100, 1], [$this->parser->level('MTH101'), $this->parser->semester('MTH101')]);
        $this->assertSame([100, 2], [$this->parser->level('PHY102'), $this->parser->semester('PHY102')]);
    }

    public function test_it_ignores_separators_and_case(): void
    {
        $expected = $this->parser->normalize('COS 101');
        $this->assertSame($expected, $this->parser->normalize('COS101'));
        $this->assertSame($expected, $this->parser->normalize('cos101'));
        $this->assertSame($expected, $this->parser->normalize('COS-101'));
    }

    public function test_it_normalizes_institution_prefixed_codes(): void
    {
        $this->assertSame('NUKCYB101', $this->parser->normalize('NUK-CYB 101'));
        $this->assertSame([100, 1], [$this->parser->level('NUK-CYB101'), $this->parser->semester('NUK-CYB101')]);
    }

    public function test_it_returns_null_for_codes_it_cannot_read(): void
    {
        $this->assertNull($this->parser->parse('COS'));
        $this->assertNull($this->parser->parse('101'));
        $this->assertNull($this->parser->normalize(''));
    }

    public function test_a_documented_semester_outranks_the_parity_convention(): void
    {
        // MTH 103 is placed in second semester by the form, though its code ends
        // in 3. The form wins, and the divergence is reported rather than hidden.
        $resolved = $this->parser->resolveSemester('MTH 103', 2);

        $this->assertSame(2, $resolved['semester'], 'the documented semester must stand');
        $this->assertTrue($resolved['conflicts_with_parity'], 'the divergence must be visible');
        $this->assertSame(1, $resolved['parity_predicted'], 'the convention prediction is retained for review');
        $this->assertSame('course_registration_form', $resolved['source']);
    }

    public function test_a_documented_semester_matching_parity_is_not_flagged(): void
    {
        $resolved = $this->parser->resolveSemester('COS 101', 1);

        $this->assertFalse($resolved['conflicts_with_parity']);
        $this->assertSame('course_registration_form', $resolved['source']);
    }

    public function test_without_a_document_it_falls_back_to_the_convention(): void
    {
        $resolved = $this->parser->resolveSemester('CYB 401', null);

        $this->assertSame(1, $resolved['semester']);
        $this->assertSame('code_parity', $resolved['source']);
    }

    public function test_the_catalogue_collapses_duplicate_programme_rows(): void
    {
        // The central catalogue stores one row per programme, so a shared course
        // appears many times -- "GST 111" spans 177 rows in the real corpus.
        // A dropdown must offer it once, or a coordinator adds the same course
        // twice under two spellings and the semesters start to disagree.
        //
        // Fixtures are inserted rather than read from the production import,
        // which the test database does not carry, so the duplication collapsed
        // here is the duplication this test itself creates.
        foreach (['B.Sc. Accounting', 'B.Sc. Accounting', 'B.Sc. Zoology', 'B.Sc. Zoology'] as $programme) {
            DB::table('ccmas_courses')->insert([
                'course_code' => 'GST 111',
                'title' => 'Communication in English',
                'credit_units' => 2,
                'level' => 100,
                'programme_title' => $programme,
                'source_document' => 'ccmas-test.pdf',
                'source_file' => 'ccmas-test.pdf',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $catalogue = $this->parser->catalogue(null, 100);

        $this->assertNotEmpty($catalogue);

        $codes = array_column($catalogue, 'code');
        $this->assertSame(
            count($codes),
            count(array_unique($codes)),
            'the catalogue must contain each course once, not once per programme row'
        );

        $this->assertSame(
            1,
            count(array_filter($codes, fn ($code) => $code === 'GST111')),
            'four programme rows for one course must collapse to a single entry'
        );

        $entry = collect($catalogue)->firstWhere('code', 'GST111');
        $this->assertSame(1, $entry['semester'], 'odd trailing digit places it in first semester');
        $this->assertSame(100, $entry['level']);
        $this->assertSame('ccmas', $entry['source']);
    }
}
