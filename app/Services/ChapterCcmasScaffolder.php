<?php
namespace App\Services;

use App\Models\Curriculum\CcmasCourse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates empty chapter placeholders aligned with CCMAS learning outcomes.
 * Reads the source .txt files for outcome sections; creates chapter records
 * with titles derived from those outcomes. No body content — placeholders only.
 */
class ChapterCcmasScaffolder
{
    public function scaffoldForCcmas(int $limit = 0, int $chunk = 50): array
    {
        $created = 0;
        $skipped = 0;
        $done = 0;

        $courses = DB::table('ccmas_courses')
            ->where('status', 'imported')
            ->limit($limit > 0 ? $limit : 10000)
            ->get();

        foreach ($courses as $ccmas) {
            $courseId = DB::table('courses')
                ->where('ccmas_course_id', $ccmas->id)
                ->value('id');

            if (! $courseId) {
                // Skip courses without canonical content links.
                // This protects against broken references (same design as builder).
                continue;
            }

            $titles = $this->extractOutcomeTitles($ccmas);

            if ($titles === []) {
                $titles = ['Course Orientation and Learning Outcomes'];
            }

            $highest = (int) DB::table('course_chapters')
                ->where('course_id', $courseId)
                ->max('position');

            $existing = DB::table('course_chapters')
                ->where('course_id', $courseId)
                ->pluck('slug')
                ->flip()
                ->all();

            $position = $highest;
            $rows = [];

            foreach ($titles as $title) {
                $position++;
                $slug = Str::slug($title);
                if ($slug === '') $slug = 'outcome-' . $position;

                if (isset($existing[$slug])) {
                    $skipped++;
                    continue;
                }

                $rows[] = [
                    'course_id' => $courseId,
                    'position' => $position,
                    'title' => $title,
                    'slug' => $slug,
                    'introduction' => null,
                    'summary' => null,
                    'key_takeaways' => null,
                    'further_reading' => null,
                    'status' => 'draft',
                    'placeholder' => true,
                    'version' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $existing[$slug] = true;
            }

            if ($rows !== []) {
                DB::table('course_chapters')->insert(array_chunk($rows, 100)) ? DB::table('course_chapters')->insert($rows) : null;
                // Insert in batches safely
                foreach (array_chunk($rows, 100) as $batch) {
                    DB::table('course_chapters')->insert($batch);
                }
                $created += count($rows);
            }
            $done++;
        }

        return ['courses' => $done, 'created' => $created, 'skipped' => $skipped];
    }

    private function extractOutcomeTitles(CcmasCourse $ccmas): array
    {
        $path = storage_path('app/nuc-ccmas/' . $ccmas->source_file);
        if (! file_exists($path)) return [];

        $content = file_get_contents($path);
        // Extract outcome lines: numbered items after "Learning Outcomes"
        $pattern = '/Learning\s+Outcomes.*?([\d]+\.\s+[A-Z][^\n]+)(?:\n|$)/i';
        // Simplified: split by lines, find numbered outcomes
        $lines = explode("\n", $content);
        $inOutcomes = false;
        $titles = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (str_contains(strtolower($line), 'learning outcomes')) {
                $inOutcomes = true;
                continue;
            }
            if ($inOutcomes) {
                // Stop at next major heading or empty line after outcomes
                if ($line === '' || str_starts_with($line, 'Course Contents') || str_starts_with($line, 'Overview') || str_starts_with($line, '100 Level')) {
                    break;
                }
                // Capture outcome lines like "1. Students will understand..."
                if (preg_match('/^\d+\.\s+(.+)/', $line, $m)) {
                    $text = trim($m[1]);
                    if (strlen($text) > 5 && strlen($text) < 120) {
                        $titles[] = $text;
                    }
                }
            }
        }
        return array_slice(array_unique($titles), 0, 12);
    }
}
