<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CourseOrientation extends Model
{
    protected $table = 'course_orientations';
    protected $fillable = ['course_id','user_id','completed','quiz_score','completed_at'];
    public function course(): BelongsTo { return $this->belongsTo(Course::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
