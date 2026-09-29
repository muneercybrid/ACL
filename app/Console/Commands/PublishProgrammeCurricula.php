<?php

namespace App\Console\Commands;

use App\Models\AcademicProgram;
use App\Services\Curriculum\CurriculumPublisher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PublishProgrammeCurricula extends Command
{
    protected $signature = 'acl:curriculum:publish
                            {--limit=100 : Maximum number of programmes to process per run}
                            {--apply : Apply changes (omit for a dry-run)}';

    protected $description = 'Publish verified curricula for academic programmes';

    public function handle(CurriculumPublisher $publisher): int
    {
        $limit = (int) $this->option('limit');
        $apply = (bool) $this->option('apply');

        $total = AcademicProgram::count();
        $this->info("Total academic programmes: {$total}");

        $published = 0;
        $skipped = 0;
        $lastId = 0;

        $this->info($apply ? 'Running in apply mode.' : 'Running in dry-run mode (use --apply to commit changes).');

        do {
            $batch = DB::table('academic_programs')
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit($limit)
                ->get(['id']);

            if ($batch->isEmpty()) {
                break;
            }

            foreach ($batch as $row) {
                $lastId = (int) $row->id;

                $offering = AcademicProgram::find($row->id);

                if (! $offering) {
                    $skipped++;
                    continue;
                }

                if ($apply) {
                    $result = $publisher->publish($offering);
                    $result ? $published++ : $skipped++;
                } else {
                    $this->line("  [dry-run] Would publish programme ID {$offering->id}: {$offering->name}");
                    $published++;
                }
            }

            if ($batch->count() < $limit) {
                break;
            }
        } while (true);

        $this->info("Done. Published: {$published}, Skipped: {$skipped}.");

        return self::SUCCESS;
    }
}
