<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    public static function write(string $action, string $module, ?Model $subject = null, ?array $before = null, ?array $after = null): void
    {
        $user = auth()->user();
        ActivityLog::create([
            'user_id' => $user?->id, 'role' => $user?->role, 'action' => $action, 'module' => $module,
            'subject_type' => $subject ? $subject::class : null, 'subject_id' => $subject?->getKey(),
            'before' => $before, 'after' => $after, 'ip_address' => request()?->ip(), 'user_agent' => request()?->userAgent(),
        ]);
    }
}
