<?php

namespace App\Models\Curriculum;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NucDiscipline extends Model
{
    protected $table = 'nuc_disciplines';
    protected $fillable = ['code', 'name', 'status'];

    public function programmes(): HasMany
    {
        return $this->hasMany(\App\Models\Programme::class, 'nuc_discipline_id');
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Course::class, 'course_disciplines', 'nuc_discipline_id', 'course_id');
    }
}
