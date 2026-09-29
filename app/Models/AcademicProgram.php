<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Curriculum\Programme;

class AcademicProgram extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_id',
        'name',
        'slug',
        'code',
        'degree_type',
        'duration_years',
        'description',
        'is_active',
        // The two columns that make an offering an offering: which organization
        // runs it, and which catalogue programme it is a running of.
        //
        // They were missing here, which is worse than it sounds: Eloquent
        // discards non-fillable attributes silently rather than raising, so an
        // offering could be created that belonged to no organization and
        // referenced no catalogue programme. That is precisely the disconnected
        // state this model exists to prevent, and it produced no error to notice.
        'organization_id',
        'nuc_programme_id',
    ];

    protected function casts(): array
    {
        return [
            'duration_years' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function levels(): HasMany
    {
        return $this->hasMany(Level::class);
    }

    /**
     * The NUC programme this academic programme corresponds to.
     *
     * `nuc_programme_id` existed on the table with no relation defined, so the
     * link from an institution's programme to its NUC counterpart was only
     * reachable by raw query. This is the hop that carries the NUC discipline
     * down to an institution: academic_programs -> programmes -> nuc_disciplines.
     */
    public function nucProgramme(): BelongsTo
    {
        return $this->belongsTo(Programme::class, 'nuc_programme_id');
    }
}
