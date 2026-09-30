<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Curriculum\CcmasCourse;
use App\Models\OrganizationCourseCode;
use App\Services\Courses\CentralCourseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Course identity is central; the code is the school's.
 *
 * The point of the change these tests cover is that two universities running
 * "Introduction to Malware and Social Engineering" — one as NUK-CYB101, the
 * other as BUK-CSC 111 — end up sharing one course and one set of content,
 * rather than each holding their own private copy. Everything else here
 * protects that from being undone by the ambiguity in the source data.
 */
class CentralCourseSharingTest extends TestCase
{
    use RefreshDatabase;

    private function central(): CentralCourseService
    {
        return app(CentralCourseService::class);
    }

    /**
     * An organization. slug is NOT NULL, so every fixture needs one.
     */
    private function school(string $name): \App\Models\Organization
    {
        return \App\Models\Organization::create([
            'name' => $name, 'slug' => str($name)->slug(), 'is_active' => true,
        ]);
    }

    private function ccmas(string $code = 'COS 101', string $title = 'Introduction to Malware and Social Engineering'): CcmasCourse
    {
        return CcmasCourse::create([
            'source_document' => 'NUC CCMAS 2023',
            'source_file' => 'computing.txt',
            'source_line' => 1,
            'discipline_code' => 'CSC',
            'course_code' => $code,
            'title' => $title,
            'credit_units' => 3,
            'level' => 100,
            'is_active' => true,
        ]);
    }

    public function test_a_ccmas_course_becomes_one_central_course(): void
    {
        $central = $this->central();
        $ccmas = $this->ccmas();

        $course = $central->centralForCcmas($ccmas);

        $this->assertSame('Introduction to Malware and Social Engineering', $course->title);
        $this->assertSame($ccmas->id, $course->ccmas_course_id);
    }

    public function test_two_schools_adding_the_same_ccmas_course_share_one_course(): void
    {
        // The line that makes sharing work. Without the recorded link, each
        // school would mint its own central course and the two would never
        // share content.
        $central = $this->central();
        $ccmas = $this->ccmas();

        $first = $central->centralForCcmas($ccmas);
        $second = $central->centralForCcmas($ccmas->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Course::where('ccmas_course_id', $ccmas->id)->count());
    }

    public function test_different_codes_for_the_same_content_still_share(): void
    {
        // The user's example, reduced to two institutions. Different codes, one
        // course: NUK's code is NUK's business and BUK's is BUK's.
        $central = $this->central();

        $nuk = $this->school('Northwest University Kano');
        $buk = $this->school('Bayero University Kano');

        $course = $central->centralForCcmas($this->ccmas('CSC 111', 'Introduction to Malware and Social Engineering'));

        $central->registerLocalCode($nuk, $course, 'NUK-CYB101');
        $central->registerLocalCode($buk, $course, 'BUK-CSC 111');

        $this->assertSame(2, OrganizationCourseCode::where('course_id', $course->id)->count());

        // Two local codes, one central course. That is the whole claim: the
        // schools differ in what they call it and agree on what it is.
        $this->assertSame(
            1,
            OrganizationCourseCode::whereIn('local_code', ['NUK-CYB101', 'BUK-CSC 111'])
                ->distinct()
                ->count('course_id')
        );
        $this->assertSame('NUK-CYB101', $central->localCodeFor($course->id, $nuk->id));
        $this->assertSame('BUK-CSC 111', $central->localCodeFor($course->id, $buk->id));
        $this->assertNotSame(
            $central->localCodeFor($course->id, $nuk->id),
            $central->localCodeFor($course->id, $buk->id),
            'Each school keeps its own code for the one shared course.'
        );
    }

    public function test_a_school_may_not_reuse_its_code_for_a_different_course(): void
    {
        $central = $this->central();
        $nuk = $this->school('Northwest University Kano');

        $central->registerLocalCode($nuk, $central->centralForCcmas($this->ccmas()), 'NUK-CYB101');

        $this->expectException(\RuntimeException::class);
        $central->registerLocalCode(
            $nuk,
            $central->centralForCcmas($this->ccmas('CSC 211', 'Network Security')),
            'NUK-CYB101'
        );
    }

    public function test_registering_the_same_code_for_the_same_course_twice_is_harmless(): void
    {
        $central = $this->central();
        $nuk = $this->school('Northwest University Kano');
        $course = $central->centralForCcmas($this->ccmas());

        $central->registerLocalCode($nuk, $course, 'NUK-CYB101');
        $central->registerLocalCode($nuk, $course, 'NUK-CYB101');

        $this->assertSame(1, OrganizationCourseCode::where('course_id', $course->id)->count());
    }

    public function test_the_same_code_may_be_used_at_two_different_schools(): void
    {
        // Codes are only unique within a school, which is what allows a school
        // to rename a course to match a national code without colliding with
        // anyone else doing the same.
        $central = $this->central();

        $a = $this->school('School A');
        $b = $this->school('School B');
        $course = $central->centralForCcmas($this->ccmas());

        $central->registerLocalCode($a, $course, 'CSC 111');
        $central->registerLocalCode($b, $course, 'CSC 111');

        $this->assertSame(2, OrganizationCourseCode::where('local_code', 'CSC 111')->count());
    }

    public function test_a_new_course_can_be_created_for_a_gap_in_the_catalogue(): void
    {
        $course = $this->central()->createCentral('Local Industrial Training', 'NUK-IT 305', 2);

        $this->assertSame('Local Industrial Training', $course->title);
        $this->assertNull($course->ccmas_course_id);
        $this->assertSame('institution', $course->source_type);
    }

    public function test_a_manually_added_course_is_findable_by_another_school(): void
    {
        // The reason manual entry is worth having: the second school finds the
        // first school's course instead of writing it again.
        $central = $this->central();
        $central->createCentral('Local Industrial Training', 'NUK-IT 305', 2);

        $found = $central->search('Local Industrial Training');

        $this->assertCount(1, $found);
        $this->assertSame('Local Industrial Training', $found->first()->title);
    }

    public function test_search_reports_how_many_schools_already_share_a_course(): void
    {
        $central = $this->central();
        $course = $central->centralForCcmas($this->ccmas());

        foreach (['School A', 'School B', 'School C'] as $name) {
            $org = $this->school($name);
            $central->registerLocalCode($org, $course, 'CSC 111');
        }

        $this->assertSame(3, $central->usageCount($course->id));
        $this->assertSame(3, $central->search('Malware')->first()->usage_count);
    }

    public function test_search_shows_the_searching_schools_own_code(): void
    {
        $central = $this->central();
        $nuk = $this->school('Northwest University Kano');
        $course = $central->centralForCcmas($this->ccmas());
        $central->registerLocalCode($nuk, $course, 'NUK-CYB101');

        $this->assertSame('NUK-CYB101', $central->search('Malware', $nuk->id)->first()->local_code);
        $this->assertNull($central->search('Malware', $this->school('Other')->id)->first()->local_code);
    }

    public function test_suggestions_match_across_cosmetic_title_differences(): void
    {
        // Schools punctuate and title-case differently. Those are the same
        // course, and refusing to match them would undo the sharing.
        $central = $this->central();
        $course = $central->createCentral('Introduction to Malware & Social Engineering', 'X1', 3);

        $suggested = $central->suggestFor('introduction to malware and social engineering');

        $this->assertTrue($suggested->contains('id', $course->id));
    }

    public function test_ambiguous_titles_are_suggested_rather_than_merged_automatically(): void
    {
        // 116 NUC titles are indistinguishable from their text alone — the same
        // generic title in two disciplines. Suggesting them is correct; picking
        // for the user would merge courses that are not the same.
        $central = $this->central();
        $central->createCentral('Research Project', 'MCB 491', 6);
        $central->createCentral('Research Project', 'VRP 608', 6);

        $this->assertCount(2, $central->suggestFor('Research Project'));
    }

    public function test_two_courses_may_share_a_title_and_stay_distinct(): void
    {
        $central = $this->central();

        $a = $central->createCentral('General Physics I', 'PHY 101', 3);
        $b = $central->createCentral('General Physics I', 'PHY 201', 3);

        $this->assertNotSame($a->id, $b->id);
        $this->assertSame(2, Course::where('title', 'General Physics I')->count());
    }

    public function test_created_courses_get_distinct_slugs(): void
    {
        $central = $this->central();

        $a = $central->createCentral('Local Industrial Training', 'A1', 2);
        $b = $central->createCentral('Local Industrial Training', 'B1', 2);

        // Two courses with the same title must still be individually
        // addressable, and the slug is how a URL reaches one of them.
        $this->assertNotSame($a->slug, $b->slug);
    }
}
