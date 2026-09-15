<?php

namespace App\Models\Curriculum;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Programme extends Model
{
    protected $table = 'programmes';
    
    protected $fillable = [
        'name', 
        'slug', 
        'nuc_discipline_id', 
        'status'
    ];

    public function nucDiscipline(): BelongsTo
    {
        return $this->belongsTo(NucDiscipline::class, 'nuc_discipline_id');
    }

    public function curriculumVersions(): HasMany
    {
        return $this->hasMany(CurriculumVersion::class);
    }
}
