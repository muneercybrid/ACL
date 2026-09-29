<?php
namespace App\Services\Institution;
use Illuminate\Database\Connection;

class AuditLogService
{
    // Minimal audit framework (table to be created via ADD migration, not destructive)
    public static function log(string $entityType, ?int $entityId, string $action, ?int $userId, array $changes): void
    {
        \App\Models\AuditLog::create([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'user_id' => $userId,
            'metadata' => $changes,
        ]);
    }
}
