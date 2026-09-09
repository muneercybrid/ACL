<?php

namespace App\Models\ACLi;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Request extends Model
{
    protected $table = 'acli_requests';

    protected $fillable = [
        'user_id',
        'conversation_id',
        'capability_id',
        'provider',
        'model',
        'status',
        'input_tokens',
        'output_tokens',
        'total_tokens',
        'estimated_cost',
        'latency_ms',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'total_tokens' => 'integer',
            'estimated_cost' => 'decimal:6',
            'latency_ms' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function capability(): BelongsTo
    {
        return $this->belongsTo(Capability::class);
    }
}
