<?php

namespace App\Models\Curriculum;

use Illuminate\Database\Eloquent\Model;

class CurriculumVersion extends Model
{
    protected $table = 'curriculum_versions';
    protected $fillable = [
        'programme_id', 'academic_session_id', 'version_label', 'slug', 'scope',
        'verification_status', 'source_type', 'source_document', 'is_active',
    ];
    protected $casts = ['is_active' => 'boolean'];
}
