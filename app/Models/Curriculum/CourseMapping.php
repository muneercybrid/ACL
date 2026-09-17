<?php
namespace App\Models\Curriculum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CourseMapping extends Model
{
    protected $table = 'course_mappings';
    protected $fillable = ['nuc_course_id','institution_course_id','institution_id','mapping_type','source_document_id','verification_status','notes'];
    public function nucCourse(): BelongsTo { return $this->belongsTo(\App\Models\Course::class, 'nuc_course_id'); }
    public function institutionCourse(): BelongsTo { return $this->belongsTo(\App\Models\Course::class, 'institution_course_id'); }
}
