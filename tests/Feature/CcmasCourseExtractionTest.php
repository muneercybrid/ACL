<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\CcmasCourseExtractor;
use Tests\TestCase;

/**
 * Extraction rules, proved against the real text shape.
 *
 * The fixture is not invented. Every line is copied from
 * storage/app/nuc-ccmas/computing.txt, including the page furniture, the
 * reflowed credit-units column, the Global Course Structure table and the
 * wrapped sentence that used to be read as a course.
 *
 * This lives under tests/Feature because the ACL constitution keeps one test
 * suite; it is a unit test of a pure function wearing that directory.
 */
class CcmasCourseExtractionTest extends TestCase
{
    /** computing.txt lines 3282-4160, damage included. */
    private function fixture(): string
    {
        return <<<'TEXT'
B.Sc. Cybersecurity
Overview
The B.Sc. Cybersecurity programme teaches computing security.

Global Course Structure
100 Level
Course Code

Course Title

Units

Status LH

PH

GST 111

Communication in English

2

C

15

45

Minimum Academic Standards
A programme should have at least three categories of laboratories.

Course Contents and Learning Outcomes
100 Level
GST 111: Communication in English

(2 Units C: LH15; PH 45)

Learning Outcomes
At the end of this course, students should be able to:
1. identify possible sound patterns in English language;
2. list notable language skills;
Course Contents
Sound patterns in English Language (vowels and consonants).
GST 112: Nigerian Peoples and Culture

(2 Units C: LH 30)

Learning Outcomes
3. explain the gradual evolution of Nigeria as a political unit;
MTH 101: Elementary Mathematics I (Algebra and Trigonometry)
C: LH 30)

(2

Units

Learning Outcomes
Computing

60

New

At the end of the course, students should be able to:
4. solve quadratic equations;
MTH102: Elementary Mathematics II
PHY 101: General Physics I (Mechanics)

(2 Units C: LH 30)
PHY 107: General Practical Physics I
(1 Unit C: PH 45)
Learning Outcomes
At the end of the course, students should be able to:
1. conduct measurements of some physical quantities;
Course Contents
This introductory course emphasizes quantitative measurements, the treatment of
measurement errors and graphical analysis. A variety of experimental techniques should be
employed. The experiments include studies of meters, the oscilloscope, mechanical systems,
electrical and mechanical resonant systems, light, heat, viscosity etc., covered in PHY 101 and
PHY 102. However, emphasis should be placed on the basic physical techniques for
observation, measurements, data collection, analysis and deduction.
PHY 108 - General Practical Physics II

(1 Unit C: PH 45)
STA 111: Descriptive Statistics (3 units)
MTH 101: Elementary Mathematics I (Algebra and Trigonometry)
200-Level Courses
CYB 201: Introduction to Cybersecurity and Strategy

(2 Units C: LH 30)
300 Level
CYB 301: Cryptography Techniques, Algorithms and Applications
C: LH 15; PH 45)

(2

Units

Learning Outcomes
CYB 399: SIWES II

(3 Units C: PH 45)
400 Level
CYB 497: Final Year Project I

(3 Units C: PH 135)
Global Course Structure
400 Level
Course Code
CYB 406
Deep and Dark Web Security
2
C
15
Minimum Academic Standards
TEXT;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function courses(): array
    {
        return (new CcmasCourseExtractor())
            ->extract($this->fixture(), 'computing')['courses'];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function byCodeAndTitle(): array
    {
        $indexed = [];

        foreach ($this->courses() as $row) {
            $indexed[$row['course_code'].'|'.$row['title']] = $row;
        }

        return $indexed;
    }

    public function test_it_reads_the_code_and_title_of_a_listed_course(): void
    {
        $rows = $this->courses();

        $this->assertContains('Communication in English', array_column($rows, 'title'));
        $this->assertContains('Nigerian Peoples and Culture', array_column($rows, 'title'));
        $this->assertContains('Introduction to Cybersecurity and Strategy', array_column($rows, 'title'));
    }

    public function test_it_normalises_codes_the_corpus_writes_inconsistently(): void
    {
        // The corpus writes "MTH 101" and "MTH102" for the same course.
        $this->assertContains('MTH 102', array_column($this->courses(), 'course_code'));
        $this->assertNotContains('MTH102', array_column($this->courses(), 'course_code'));
    }

    public function test_it_never_reads_a_learning_outcome_bullet_as_a_course(): void
    {
        foreach ($this->courses() as $row) {
            $this->assertStringStartsNotWith('identify possible', $row['title']);
            $this->assertStringNotContainsString('quadratic equations', $row['title']);
            $this->assertStringNotContainsString('Nigeria as a political unit', $row['title']);
        }

        foreach (array_column($this->courses(), 'course_code') as $code) {
            $this->assertDoesNotMatchRegularExpression('/^\d+$/', $code,
                'A numbered learning-outcome bullet is prose, never a course.');
        }
    }

    public function test_it_never_reads_the_global_course_structure_table(): void
    {
        $codes = array_column($this->courses(), 'course_code');

        // CYB 406 appears in the fixture only inside the linearised course
        // structure table. That table is a different shape and is not read, so
        // the course is absent rather than half-read.
        $this->assertNotContains('CYB 406', $codes);
        $this->assertNotContains('Deep and Dark Web Security', array_column($this->courses(), 'title'));
    }

    public function test_it_ignores_page_headers_footers_and_watermarks(): void
    {
        $codes = array_column($this->courses(), 'course_code');

        $this->assertNotContains('NEW', $codes);
        $this->assertNotContains('60', $codes);
        $this->assertNotContains('COMPUTING', $codes);
    }

    public function test_it_suppresses_a_wrapped_sentence_that_looks_like_a_course(): void
    {
        $junk = array_values(array_filter(
            $this->courses(),
            fn (array $row): bool => str_contains($row['title'], 'However, emphasis')
        ));

        $this->assertCount(0, $junk,
            '"PHY 102. However, emphasis should be placed…" is the tail of the previous sentence, not a course.');

        // The real course in the same level is still there. The fixture declares
        // it as PHY 101; the key below is corrected to match, because asserting
        // PHY 102 could never pass and the test was shipping red.
        $this->assertArrayHasKey('PHY 101|General Physics I (Mechanics)', $this->byCodeAndTitle());
    }

    public function test_level_comes_only_from_an_explicit_heading(): void
    {
        $levels = [];

        foreach ($this->courses() as $row) {
            $levels[$row['course_code']] = $row['level'];
        }

        // GST 111 is a 100-level course. The "111" is not a level and reading
        // it as one is exactly the bug this guards.
        $this->assertSame(100, $levels['GST 111']);

        // STA 111 also sits under 100 in the real corpus. The trailing "111" is
        // a course number, not a level. Asserted once on purpose: an earlier
        // version of this test asserted both 100 and 300 for this same key,
        // which cannot both hold, so it was failing and had not been run.
        $this->assertSame(100, $levels['STA 111']);

        // "200-Level Courses", "300 Level" and "400 Level" are all headings.
        $this->assertSame(200, $levels['CYB 201']);
        $this->assertSame(300, $levels['CYB 301']);
        $this->assertSame(400, $levels['CYB 497']);
    }

    public function test_credit_units_are_read_from_the_source_and_never_invented(): void
    {
        $rows = $this->byCodeAndTitle();

        $this->assertSame(2.0, $rows['GST 111|Communication in English']['credit_units']);
        $this->assertSame(2.0, $rows['GST 112|Nigerian Peoples and Culture']['credit_units']);

        // "(2" then "Units" on separate lines: the reflowed column.
        $this->assertSame(2.0, $rows['MTH 101|Elementary Mathematics I (Algebra and Trigonometry)']['credit_units']);
        $this->assertSame(2.0, $rows['CYB 301|Cryptography Techniques, Algorithms and Applications']['credit_units']);

        // The singular "Unit" appears in the corpus too.
        $this->assertSame(1.0, $rows['PHY 107|General Practical Physics I']['credit_units']);

        // Read out of the title line itself, and stripped from the title.
        $this->assertSame(3.0, $rows['STA 111|Descriptive Statistics']['credit_units']);

        // MTH 102 is listed with no units anywhere near it. It stays null.
        $this->assertNull($rows['MTH 102|Elementary Mathematics II']['credit_units']);
    }

    public function test_a_course_is_attributed_to_the_programme_it_was_listed_under(): void
    {
        $this->assertSame(['B.Sc. Cybersecurity'], array_values(array_unique(
            array_column($this->courses(), 'programme_title')
        )));
    }

    public function test_it_de_duplicates_on_code_and_title_within_a_programme(): void
    {
        $keys = array_map(
            fn (array $row): string => $row['course_code'].'|'.$row['title'],
            $this->courses()
        );

        // MTH 101 is listed twice in the fixture; it is one course.
        $this->assertSame(count($keys), count(array_unique($keys)));
    }

    public function test_provenance_points_back_at_the_source_line(): void
    {
        $lines = explode("\n", $this->fixture());
        $line = null;

        foreach ($this->courses() as $row) {
            if ($row['course_code'] === 'CYB 497') {
                $line = $row['source_line'];
            }
        }

        $this->assertIsInt($line);
        $this->assertSame('CYB 497: Final Year Project I', $lines[$line - 1]);
        $this->assertSame('computing', $this->byCodeAndTitle()['CYB 497|Final Year Project I']['source_document']);
    }

    public function test_a_document_with_no_course_section_yields_nothing(): void
    {
        $result = (new CcmasCourseExtractor())->extract(
            "Contents\nB.Sc. Something ..... 27\nCourse Contents and Learning Outcomes ..... 31\n",
            'computing'
        );

        $this->assertSame([], $result['courses']);
    }

    public function test_a_section_with_no_programme_heading_is_reported_rather_than_guessed(): void
    {
        $result = (new CcmasCourseExtractor())->extract(
            "Course Contents and Learning Outcomes\n100 Level\nGST 111: Communication in English\n",
            'computing'
        );

        $this->assertCount(1, $result['courses']);
        $this->assertNull($result['courses'][0]['programme_title']);
        $this->assertSame('needs_review', $result['courses'][0]['status']);
    }
}
