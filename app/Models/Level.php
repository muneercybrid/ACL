<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Level extends Model
{
    use HasFactory;

    protected $fillable = ['academic_program_id', 'name', 'code', 'sequence', 'is_active'];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function academicProgram(): BelongsTo
    {
        return $this->belongsTo(AcademicProgram::class);
    }
}
