<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class Institution extends Model {
    use HasFactory;
    protected $fillable = ['name','normalized_name','ownership','state','established_year','website','nuc_source_ref','institution_status','onboarding_status','slug','import_batch','synchronized_at'];
    protected $casts = ['established_year'=>'integer','synchronized_at'=>'datetime'];
}
