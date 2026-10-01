<?php

namespace Tests\Feature;

use Tests\TestCase;
use ReflectionMethod;

/**
 * A chapter plan came back with both "History and Evolution of Computing
 * Systems" and "History and Evolution of Computing" and both were stored, so
 * a student opened a course and found the same topic listed twice.
 *
 * The original guard compared slug equality, which those two titles do not
 * share. These tests pin the behaviour that actually matters: no two chapters
 * of one course describe the same chapter.
 */
class ChapterTitleDeduplicationTest extends TestCase
{
    /**
     * @param  array<int, string>  $seen
     */
    private function isDuplicate(string $title, array $seen): bool
    {
        $generator = new \App\Services\ACLi\CourseContentGenerator(
            app(\App\Services\ACLi\ProviderManager::class)
        );

        $method = new ReflectionMethod($generator, 'isNearDuplicate');
        $method->setAccessible(true);

        return $method->invoke($generator, $title, $seen);
    }

    public function test_it_catches_a_title_differing_only_by_a_trailing_word(): void
    {
        // The exact pair that shipped as a duplicate.
        $this->assertTrue($this->isDuplicate(
            'History and Evolution of Computing',
            ['History and Evolution of Computing Systems']
        ));
    }

    public function test_it_catches_word_order_differences(): void
    {
        $this->assertTrue($this->isDuplicate(
            'Computer Hardware and Organization',
            ['Hardware and Computer Organization']
        ));
    }

    public function test_genuinely_different_chapters_are_not_rejected(): void
    {
        // Overlapping on a stop word only. These are real separate chapters
        // and rejecting the second would leave a course short of content.
        $this->assertFalse($this->isDuplicate(
            'Introduction to Cyber Security',
            ['Introduction to Data Protection Regulations']
        ));

        $this->assertFalse($this->isDuplicate(
            'Data Representation and Number Systems',
            ['Networks, Internet, and Societal Impact']
        ));
    }

    public function test_a_course_may_have_several_introductory_chapters(): void
    {
        // "Course Orientation" and "Course Overview" share only stop words.
        $this->assertFalse($this->isDuplicate('Course Overview', ['Course Orientation']));
    }

    public function test_an_identical_title_is_always_a_duplicate(): void
    {
        // Even with a single significant word, the same title twice is the
        // same chapter twice.
        $this->assertTrue($this->isDuplicate('Overview', ['Overview']));
    }

    public function test_different_short_titles_are_kept(): void
    {
        // One shared word between two different one-word titles is not
        // evidence they are the same chapter.
        $this->assertFalse($this->isDuplicate('Encoding', ['Overview']));
    }
}