<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MissingCourseRequest extends Model
{
    use HasFactory;

    protected $table = 'missing_course_requests';

    protected $fillable = [
        'user_id', 'student_id', 'institution_id', 'faculty_id',
        'department_id', 'programme_id', 'curriculum_version_id',
        'level', 'semester', 'submitted_course_code', 'submitted_title',
        'description', 'status', 'submitted_at', 'reviewed_at',
        'reviewer_id', 'reviewer_notes', 'verified_course_code',
        'verified_title', 'verified_programme_id', 'verified_level',
        'verified_semester', 'source_provenance', 'audit_trail',
    ];

    protected $casts = [
        'status' => 'string',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function programme()
    {
        return $this->belongsTo(Curriculum\Programme::class, 'programme_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
