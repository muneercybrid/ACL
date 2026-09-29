<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RoleAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'role_id',
        'entity_type',
        'entity_id',
        // The scope within the entity, when narrower than the entity itself —
        // a level of a programme, for a level coordinator. Without these the
        // role assignment would say "coordinator of Computer Science" and could
        // not express "of 100 level only".
        'scope_type',
        'scope_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function entity(): MorphTo
    {
        return $this->morphTo();
    }
}
