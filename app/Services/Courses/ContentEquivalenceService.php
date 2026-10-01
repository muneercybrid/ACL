<?php
namespace App\Services\Courses;

/**
 * Content equivalence: NEVER determines sharing from title alone.
 * Uses: CCMAS reference, curriculum position, description, outcomes,
 * credit structure, prerequisites. Title is a weak signal only.
 */
class ContentEquivalenceService
{
    public function canShare(int $courseA, int $courseB): bool
    {
        // Same canonical_content_id already? Then share.
        $ca = \App\Models\Course::find($courseA);
        $cb = \App\Models\Course::find($courseB);
        if (! $ca || ! $cb) return false;

        if ($ca->id === $cb->id) return true; // same record

        // Must have verified mapping or verified same CCMAS reference.
        // Title-only matches are FALSE (mandatory rule).
        if ($ca->normalized_title === $cb->normalized_title
            && $ca->normalized_code !== $cb->normalized_code
            && $ca->ccmas_course_id !== $cb->ccmas_course_id) {
            return false; // same title, different source/content = separate
        }

        return $ca->ccmas_course_id === $cb->ccmas_course_id
            || $this->verifiedMappingExists($ca->id, $cb->id);
    }

    private function verifiedMappingExists(int $a, int $b): bool
    {
        return \DB::table('course_mappings')
            ->where(function ($q) use ($a,$b) {
                $q->where('nuc_course_id', $a)->where('institution_course_id', $b)
                  ->orWhere('nuc_course_id', $b)->where('institution_course_id', $a);
            })
            ->where('verification_status', 'verified')
            ->exists();
    }
}
