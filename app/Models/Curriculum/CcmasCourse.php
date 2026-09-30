<?php

declare(strict_types=1);

namespace App\Models\Curriculum;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One course as the NUC CCMAS 2023 document states it.
 *
 * This is a catalogue, not a course ACL offers. A level coordinator searching
 * for "Cyber" while populating a programme finds a row here and maps it onto
 * whatever their institution actually runs. Nothing on this model is
 * student-facing, and no row is attached to an institution.
 *
 * @property int $id
 * @property string|null $source_document
 * @property string|null $source_file
 * @property int|null $source_line
 * @property string|null $discipline_code
 * @property int|null $nuc_discipline_id
 * @property string $course_code
 * @property string $title
 * @property float|null $credit_units
 * @property int|null $level
 * @property string|null $programme_title
 * @property string $status
 * @property bool $is_active
 */
class CcmasCourse extends Model
{
    protected $table = 'ccmas_courses';

    protected $fillable = [
        'source_document',
        'source_file',
        'source_line',
        'discipline_code',
        'nuc_discipline_id',
        'course_code',
        'title',
        'credit_units',
        'level',
        'programme_title',
        'status',
        'is_active',
    ];

    protected $casts = [
        'credit_units' => 'float',
        'level' => 'integer',
        'source_line' => 'integer',
        'is_active' => 'boolean',
    ];

    public function discipline(): BelongsTo
    {
        return $this->belongsTo(NucDiscipline::class, 'nuc_discipline_id');
    }

    /** @param  Builder<CcmasCourse>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param  Builder<CcmasCourse>  $query */
    public function scopeForLevel(Builder $query, ?int $level): void
    {
        $query->where('level', $level);
    }

    /** @param  Builder<CcmasCourse>  $query */
    public function scopeForDiscipline(Builder $query, ?string $code): void
    {
        $query->where('discipline_code', $code);
    }

    /**
     * The search a level coordinator actually types: a code or any run of
     * words from the title. Code matching is case-insensitive and tolerant of
     * the space the corpus itself is inconsistent about ("MTH101" and
     * "MTH 101" are the same course).
     *
     * @param  Builder<CcmasCourse>  $query
     */
    public function scopeMatching(Builder $query, string $term): void
    {
        $term = trim($term);

        if ($term === '') {
            return;
        }

        $spaced = mb_strtoupper($term);
        $tight = str_replace(' ', '', $spaced);

        $query->where(function (Builder $inner) use ($spaced, $tight): void {
            $inner->where('course_code', 'like', $spaced)
                ->orWhere('course_code', 'like', $tight)
                ->orWhere('title', 'like', '%'.$term.'%');
        });
    }
}
