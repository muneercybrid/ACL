<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A school's own code for a course that lives centrally.
 *
 * The course is shared; the code is not. "Introduction to Malware and Social
 * Engineering" is one row in `courses` with one set of chapters, and this table
 * is how each university says what it calls it — NUK-CYB101 at one, BUK-CSC 111
 * at another. Neither school edits the other's code, and neither can claim it.
 */
class OrganizationCourseCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'course_id',
        'local_code',
        'created_by',
        'updated_by',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * The shared course this local code names.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
