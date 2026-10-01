<?php

namespace Tests\Feature;

use App\Services\StudentDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A verified student must resolve to their programme and see its courses.
 *
 * The dashboard was empty for a student verified for "Cyber Security" because
 * name normalization left the two sides in different shapes: the degree prefix
 * was stripped from "B.Sc Cybersecurity", leaving "cybersecurity", while the
 * verified programme "Cyber Security" kept its space. str_contains() does not
 * match across that space, so the programme resolved to null and the course
 * list came back empty even with a fully published curriculum.
 *
 * These tests pin the normalization, because the failure is silent -- it looks
 * exactly like "this student has no courses" rather than like a broken lookup.
 */
class StudentProgrammeResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function normalise(string $name): string
    {
        $method = new \ReflectionMethod(StudentDashboardService::class, 'normaliseName');
        $method->setAccessible(true);

        return $method->invoke(app(StudentDashboardService::class), $name);
    }

    public function test_a_verified_programme_and_its_formal_name_normalise_together(): void
    {
        // The exact pair that failed: JAMB says "Cyber Security", the programme
        // list says "B.Sc Cybersecurity".
        $this->assertSame(
            $this->normalise('B.Sc Cybersecurity'),
            $this->normalise('Cyber Security'),
            'a verified programme name and the formal programme name must normalize to the same token'
        );
    }

    public function test_it_strips_the_degree_prefix_consistently(): void
    {
        $this->assertSame('cybersecurity', $this->normalise('B.Sc Cybersecurity'));
        $this->assertSame('cybersecurity', $this->normalise('B.Sc. Cybersecurity'));
        $this->assertSame('cybersecurity', $this->normalise('b.sc  Cybersecurity'));
    }

    public function test_it_keeps_the_discipline_and_drops_only_separators(): void
    {
        $this->assertSame('computerscience', $this->normalise('B.Sc Computer Science'));
        $this->assertSame('electricalengineering', $this->normalise('B.Eng. Electrical Engineering'));
    }

    public function test_different_subjects_do_not_collide(): void
    {
        // Removing spaces must not make every science programme match every
        // other one.
        $this->assertNotSame($this->normalise('B.Sc Cybersecurity'), $this->normalise('B.Sc Zoology'));
        $this->assertNotSame($this->normalise('B.Sc Accounting'), $this->normalise('B.Sc Computer Science'));
    }
}
