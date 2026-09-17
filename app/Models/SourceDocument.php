<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SourceDocument extends Model
{
    protected $table = 'source_documents';
    protected $fillable = ['title','source_url','document_version','retrieval_date','file_path','file_hash','status','document_type','extraction_notes','import_batch_id'];
}
