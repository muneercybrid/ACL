<?php

namespace App\Services\Curriculum;

use App\Services\ACLi\ProviderManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Imports uploaded material into the course content catalogue.
 *
 * Every import is a NUMBERED UPDATE, never an overwrite. `course_chapters`
 * carries a `version` column, and each import creates the next version of the
 * affected chapters rather than mutating what is already there.
 *
 * That is deliberate. Content in a catalogue is read by students who may be
 * mid-way through a chapter, and it may be under human review. Replacing it
 * in place destroys the review trail and silently changes what a student was
 * shown last week. Creating version N+1 keeps every prior state readable, and
 * the review record in `content_reviews` points at who did what.
 *
 * Nothing here publishes. New versions land as drafts, per AGENTS.md section 7.
 */
class MaterialImporter
{
    public function __construct(
        private readonly MaterialExtractor $extractor,
        private readonly ProviderManager $providers,
    ) {
    }

    /**
     * Extracts an uploaded file and turns it into a draft chapter.
     *
     * @return array{chapter_id: int, version: int, words: int, headings: int}
     */
    public function importAsChapter(
        string $absolutePath,
        string $originalName,
        int $courseId,
        ?string $title = null,
        bool $expandWithAcLi = true,
        bool $apply = true,
    ): array {
        $this->assertMayAuthor($courseId);

        $extracted = $this->extractor->extract($absolutePath, $originalName);

        $title ??= $extracted['headings'][0]
            ?? pathinfo($originalName, PATHINFO_FILENAME);

        $body = $extracted['text'];

        if ($expandWithAcLi && $body !== '') {
            $body = $this->expand($body, $title, $courseId) ?? $body;
        }

        if (! $apply) {
            return [
                'chapter_id' => 0,
                'version' => $this->nextVersion($courseId, $title),
                'words' => str_word_count($body),
                'headings' => count($extracted['headings']),
            ];
        }

        $version = $this->nextVersion($courseId, $title);

        $chapterId = DB::table('course_chapters')->insertGetId([
            'course_id' => $courseId,
            'position' => $this->nextPosition($courseId),
            'title' => $title,
            'slug' => \Illuminate\Support\Str::slug($title).'-v'.$version,
            'introduction' => mb_substr($body, 0, 20000),
            'summary' => $this->section($body, 'Summary') ?? $this->firstLines($body),
            'key_takeaways' => $this->section($body, 'Key Takeaways'),
            'further_reading' => null,
            'status' => 'draft',
            'generated_by' => null,
            'version' => $version,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'chapter_id' => $chapterId,
            'version' => $version,
            'words' => str_word_count($body),
            'headings' => count($extracted['headings']),
        ];
    }

    /**
     * The authorization check for authoring.
     *
     * A student can read the catalogue and ask ACLi questions about it. Only a
     * superadmin or a level coordinator appointed to that programme may write
     * to it. This is server-side: the fact that a course id is known, or that a
     * form posts to a valid route, is not authorization.
     */
    public function assertMayAuthor(int $courseId): void
    {
        $user = Auth::user();

        if (! $user) {
            throw new RuntimeException('Unauthenticated.');
        }

        if (method_exists($user, 'isSuperadmin') && $user->isSuperadmin()) {
            return;
        }

        $isCoordinator = DB::table('role_assignments as ra')
            ->join('roles as r', 'r.id', '=', 'ra.role_id')
            ->join('academic_programs as ap', 'ap.id', '=', 'ra.entity_id')
            ->where('r.slug', 'level.coordinator')
            ->where('ra.entity_type', \App\Models\Curriculum\Programme::class)
            ->where('ra.user_id', $user->id)
            ->where('ap.nuc_programme_id', $courseId)
            ->exists();

        if (! $isCoordinator) {
            throw new RuntimeException(
                'Only a superadmin or a level coordinator for this programme may change its content.'
            );
        }
    }

    /**
     * Asks ACLi to expand imported material into teaching prose.
     *
     * The extracted text is the source of truth and is always what gets stored;
     * ACLi only rewrites it when it returns something usable, so a provider
     * failure degrades to a plain import rather than losing the material.
     */
    private function expand(string $body, string $title, int $courseId): ?string
    {
        $course = DB::table('courses')->where('id', $courseId)->first();

        try {
            $response = $this->providers->chat(new \App\Services\ACLi\DTO\AIRequest(
                model: 'auto',
                messages: [
                    ['role' => 'system', 'content' => 'You are a senior Nigerian academic. You reply with the formatted text only, no preamble, no markdown fences.'],
                    ['role' => 'user', 'content' => "Rewrite the following extracted material as clear teaching text for the chapter \"{$title}\""
                        . ($course ? ' in course ' . $course->code . ' ' . $course->title : '')
                        . ".\n\nKeep every fact from the source. Do not invent new content. "
                        .'End with a section headed "## Key Takeaways" containing 3-5 bullet lines. '
                        .'Keep it under 1200 words.'
                        ."\n\n---\n" . mb_substr($body, 0, 6000)],
                ],
                maxTokens: 4000,
            ))->content;

            $clean = trim(preg_replace('/^```[a-z]*\s*|\s*```$/i', '', $response) ?? $response);

            return $clean !== '' ? $clean : null;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('ACLi expansion failed; storing raw material', [
                'course_id' => $courseId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function nextVersion(int $courseId, string $title): int
    {
        $slug = \Illuminate\Support\Str::slug($title);

        $current = DB::table('course_chapters')
            ->where('course_id', $courseId)
            ->where('slug', 'like', $slug.'-v%')
            ->max('version');

        return ((int) $current) + 1;
    }

    private function nextPosition(int $courseId): int
    {
        return ((int) DB::table('course_chapters')->where('course_id', $courseId)->max('position')) + 1;
    }

    private function section(string $body, string $heading): ?string
    {
        $pattern = '/##\s*' . preg_quote($heading, '/') . '\s*\n(.*?)(?=\n##|\z)/si';

        if (! preg_match($pattern, $body, $m)) {
            return null;
        }

        return trim($m[1]);
    }

    private function firstLines(string $body): string
    {
        $paragraphs = preg_split('/\n{2,}/', trim($body)) ?: [];

        return trim(implode("\n\n", array_slice($paragraphs, 0, 2)));
    }
}
