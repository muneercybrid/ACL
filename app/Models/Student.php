<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'acl_student_id',
        'verification_method',
        'verification_status',
        'nationality',
        'state',
        'local_government',
        'lga',
        'region',
        'admission_year',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Institution records (organization memberships) for the student's
     * user account, used to resolve programme and institutional scope.
     */
    public function institutionRecords(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class, 'user_id', 'user_id');
    }
}