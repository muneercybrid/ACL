<?php

namespace App\Models\Curriculum;

use App\Services\ProgrammeCatalogue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Programme extends Model
{
    protected $table = 'programmes';

    protected $fillable = [
        'name',
        'normalized_name',
        'code',
        'slug',
        'nuc_discipline_id',
        'degree_type',
        'duration_years',
        'scope',
        'verification_status',
        'source_type',
        'source_document',
        'source_url',
        'date_verified',
        'status',
    ];

    protected $casts = [
        'date_verified' => 'date',
    ];

    /**
     * A programme is a national catalogue entry, not something an organization
     * owns. This relation therefore belongs to the organization that *offers*
     * the programme, and is the link that stops every university inventing its
     * own copy of "Computer Science".
     */
    public function nucDiscipline(): BelongsTo
    {
        return $this->belongsTo(NucDiscipline::class, 'nuc_discipline_id');
    }

    public function curriculumVersions(): HasMany
    {
        return $this->hasMany(CurriculumVersion::class);
    }

    /** The organizations currently offering this programme. */
    public function offerings(): HasMany
    {
        return $this->hasMany(\App\Models\AcademicProgram::class, 'nuc_programme_id');
    }

    /**
     * Fill the normalized name automatically so the catalogue key is never
     * left blank by a caller that forgets it.
     */
    protected static function booted(): void
    {
        static::saving(function (self $programme) {
            if (blank($programme->normalized_name) && filled($programme->name)) {
                $programme->normalized_name = ProgrammeCatalogue::normalize($programme->name);
            }

            if (blank($programme->code) && filled($programme->name)) {
                $programme->code = ProgrammeCatalogue::deriveCode($programme->name);
            }
        });
    }
}
