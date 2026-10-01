<?php

namespace Tests\Feature;

use App\Services\Courses\CcmasContentIndex;
use Tests\TestCase;

/**
 * The seventeen CCMAS documents already carry Learning Outcomes and Course
 * Contents for every course. None of it was reaching the generator: the
 * documents write codes with a space ("MTH 101") while the database stores
 * them without ("MTH101"), so the existing extraction found almost nothing and
 * chapters were written from the course title alone.
 *
 * These tests read the real documents rather than fixtures, because the bugs
 * that mattered were all about the shape of the real text -- the spacing, the
 * table-of-contents hits, the form feeds between PDF pages.
 */
class CcmasContentIndexTest extends TestCase
{
    protected CcmasContentIndex $index;

    protected function setUp(): void
    {
        parent::setUp();

        $this->index = new CcmasContentIndex();
    }

    public function test_it_finds_a_shared_course_across_every_discipline_that_lists_it(): void
    {
        // The premise of central generation: MTH 101 is the same course whether
        // a cybersecurity or a biotechnology student meets it. It is listed in
        // eleven of the seventeen documents.
        $entry = $this->index->forCode('MTH101');

        $this->assertNotNull($entry, 'MTH101 must be findable by its unspaced code');
        // The documents themselves spell this "Mathematic"; the index reports
        // what the source says rather than correcting it, because quietly
        // editing the national curriculum is not this service's decision.
        $this->assertStringContainsString('Mathematic', $entry['title']);
        $this->assertGreaterThan(10, count($entry['documents']));
    }

    public function test_the_spaced_form_in_the_documents_resolves_too(): void
    {
        $this->assertNotNull($this->index->forCode('MTH 101'));
        $this->assertSame(
            $this->index->forCode('MTH101')['title'],
            $this->index->forCode('MTH 101')['title']
        );
    }

    public function test_it_extracts_the_numbered_learning_outcomes(): void
    {
        $entry = $this->index->forCode('MTH101');

        // Five outcomes, each numbered. Without these the generator has no
        // statement of what the course must teach.
        $this->assertMatchesRegularExpression('/1\..*quadratic/s', $entry['learning_outcomes']);
        $this->assertMatchesRegularExpression('/\b5\./', $entry['learning_outcomes']);
    }

    public function test_it_extracts_the_course_contents(): void
    {
        $entry = $this->index->forCode('MTH101');

        $this->assertMatchesRegularExpression('/Venn diagrams/i', $entry['course_contents']);
        $this->assertMatchesRegularExpression('/binomial theorem/i', $entry['course_contents']);
    }

    public function test_a_section_never_runs_into_the_next_course(): void
    {
        // The failure this guards is a page break arriving as a form feed
        // rather than a newline: a course's contents ran on into the next
        // course's header and the model was handed two courses at once.
        foreach (['MTH101', 'PHY101', 'COS101', 'GST111'] as $code) {
            $entry = $this->index->forCode($code);

            $this->assertNotNull($entry, "{$code} must be parseable");

            foreach (['learning_outcomes', 'course_contents'] as $field) {
                $this->assertDoesNotMatchRegularExpression(
                    '/(?:^|\n)[A-Z]{2,4}\s?\d{3}\s*:/',
                    $entry[$field],
                    "{$code}.{$field} leaked into the next course"
                );
            }
        }
    }

    public function test_units_are_a_short_token_not_the_rest_of_the_entry(): void
    {
        foreach (['MTH101', 'COS101', 'BCH201'] as $code) {
            $entry = $this->index->forCode($code);

            if ($entry === null) {
                continue;
            }

            $this->assertLessThan(60, strlen($entry['units']), "{$code} units ran on");
        }
    }

    public function test_a_shared_course_resolves_to_one_canonical_entry(): void
    {
        // Consolidating the variants is what makes central generation sound,
        // so the same code must always return the same text.
        $first = $this->index->forCode('GST111');

        $fresh = (new CcmasContentIndex())->forCode('GST111');

        $this->assertSame($first['learning_outcomes'], $fresh['learning_outcomes']);
        $this->assertSame($first['course_contents'], $fresh['course_contents']);
        $this->assertGreaterThan(5, count($first['documents']), 'GST111 is listed in most documents');
    }

    public function test_it_covers_the_bulk_of_the_catalogue(): void
    {
        // Not every code in a course catalogue appears in a CCMAS document, so
        // this is a floor rather than an equality.
        $this->assertGreaterThan(4000, $this->index->count());
    }
}