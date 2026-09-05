<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'title', 'slug', 'credit_units', 'description', 'is_active'];

    protected function casts(): array
    {
        return [
            'credit_units' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function offerings(): HasMany
    {
        return $this->hasMany(CourseOffering::class);
    }
}
