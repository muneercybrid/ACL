<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\EntitlementService;
use Illuminate\Console\Command;

class SyncInstitutionalEnrollments extends Command
{
    protected $signature = 'acl:sync-enrollments {--user= : Sync only a specific user ID}';

    protected $description = 'Grant students free access to their institutionally entitled course offerings';

    public function handle(EntitlementService $service): int
    {
        $query = User::whereHas('organizationMemberships', function ($q) {
            $q->where('membership_type', 'student')->where('status', 'active');
        });

        if ($userId = $this->option('user')) {
            $query->whereKey($userId);
        }

        $totalEnrollments = 0;
        $processedStudents = 0;

        // chunkById keeps memory usage flat even with 50,000+ students
        $query->chunkById(100, function ($students) use ($service, &$totalEnrollments, &$processedStudents) {
            foreach ($students as $student) {
                $totalEnrollments += $service->syncInstitutionalEnrollments($student);
                $processedStudents++;
            }
        });

        $this->info("Processed {$processedStudents} students, created {$totalEnrollments} new enrollments.");

        return self::SUCCESS;
    }
}
