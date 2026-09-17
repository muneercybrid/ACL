<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class PlatformOrientation extends Model
{
    protected $table = 'platform_orientations';
    protected $fillable = ['user_id','completed','quiz_score','completed_at'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
