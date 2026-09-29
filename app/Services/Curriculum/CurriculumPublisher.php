<?php

namespace App\Services\Curriculum;

use App\Models\AcademicProgram;
use App\Models\Curriculum\CurriculumVersion;

class CurriculumPublisher
{
    /**
     * Publish the curriculum for an academic program by activating
     * the latest verified curriculum version.
     */
    public function publish(AcademicProgram $program): bool
    {
        $version = CurriculumVersion::where('programme_id', $program->id)
            ->where('verification_status', 'verified')
            ->latest()
            ->first();

        if (! $version) {
            return false;
        }

        $version->update(['is_active' => true]);

        return true;
    }
}
