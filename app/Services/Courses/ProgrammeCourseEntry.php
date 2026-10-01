<?php

namespace App\Services\Courses;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;

/**
 * One course as the dashboard presents it, from either the national or the
 * institution layer.
 *
 * This exists so a single list can carry courses that live in two different
 * tables with unrelated id sequences. The views already read a curriculum
 * placement with $entry->course->title, so this keeps that shape rather than
 * forcing every template to branch on which layer an entry came from; the
 * discriminator for that is `kind`, and `route_ref` is the only thing a link
 * should ever be built from.
 *
 * It implements ArrayAccess as well, so code that was written against the
 * collection shape keeps working.
 *
 * @implements ArrayAccess<string, mixed>
 */
class ProgrammeCourseEntry implements ArrayAccess, Arrayable
{
    public function __construct(
        public string $kind,
        public string $route_ref,
        public int $id,
        public ?string $code,
        public ?string $title,
        public ?string $description,
        public $credit_units,
        public int $level,
        public int $semester,
        public string $course_type,
        public string $source,
        public bool $is_enrollment_layer,
        public $course = null,
        public $curriculum_course = null,
        public $programme_level_course = null,
        public $current_offering = null,
    ) {}

    public static function fromCurriculumCourse($cc): self
    {
        $course = $cc->course;

        return new self(
            kind: 'national',
            route_ref: 'c'.$cc->id,
            id: (int) $cc->id,
            code: $course?->code,
            title: $course?->title,
            description: $course?->description,
            credit_units: $cc->credit_units ?? $course?->credit_units,
            level: (int) $cc->level,
            semester: (int) $cc->semester,
            course_type: $cc->course_type ?? 'core',
            source: 'ccmas',
            is_enrollment_layer: false,
            course: $course,
            curriculum_course: $cc,
            programme_level_course: null,
            current_offering: $cc->current_offering ?? null,
        );
    }

    public static function fromProgrammeLevelCourse($plc): self
    {
        $course = $plc->course;

        return new self(
            kind: 'institution',
            route_ref: 'p'.$plc->id,
            id: (int) $plc->id,
            code: $plc->course_code ?: $course?->code,
            title: $plc->title ?: $course?->title,
            description: $course?->description,
            credit_units: $plc->credit_units ?? $course?->credit_units,
            level: (int) $plc->level,
            semester: (int) $plc->semester,
            course_type: $plc->course_type ?? 'core',
            source: $plc->source ?? 'institution',
            is_enrollment_layer: true,
            course: $course,
            curriculum_course: null,
            programme_level_course: $plc,
            current_offering: null,
        );
    }

    public function isInstitutionCourse(): bool
    {
        return $this->kind === 'institution';
    }

    /**
     * True when this course came from the national CCMAS layer rather than from
     * something a school added.
     */
    public function isNational(): bool
    {
        return $this->kind === 'national';
    }

    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'route_ref' => $this->route_ref,
            'id' => $this->id,
            'code' => $this->code,
            'title' => $this->title,
            'description' => $this->description,
            'credit_units' => $this->credit_units,
            'level' => $this->level,
            'semester' => $this->semester,
            'course_type' => $this->course_type,
            'source' => $this->source,
            'is_enrollment_layer' => $this->is_enrollment_layer,
            'current_offering' => $this->current_offering,
        ];
    }

    public function offsetExists(mixed $offset): bool
    {
        return property_exists($this, $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return property_exists($this, $offset) ? $this->{$offset} : null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null || ! property_exists($this, $offset)) {
            return;
        }

        $this->{$offset} = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        if ($offset !== null && property_exists($this, $offset)) {
            $this->{$offset} = null;
        }
    }
}
