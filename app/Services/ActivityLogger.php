<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    /**
     * Activity logs are useful for accountability, but must never become a
     * second storage location for credentials or one-way activation secrets.
     * Keep this filter centralized so new call sites cannot accidentally log
     * a sensitive field through a model's toArray() output.
     *
     * @var list<string>
     */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'remember_token',
        'activation_code_hash',
        'client_secret',
        'google_client_secret',
    ];

    public static function write(string $action, string $module, ?Model $subject = null, ?array $before = null, ?array $after = null): void
    {
        $user = auth()->user();
        ActivityLog::create([
            'user_id' => $user?->id, 'role' => $user?->role, 'action' => $action, 'module' => $module,
            'subject_type' => $subject ? $subject::class : null, 'subject_id' => $subject?->getKey(),
            'before' => self::sanitize($before), 'after' => self::sanitize($after),
            'ip_address' => request()?->ip(), 'user_agent' => request()?->userAgent(),
        ]);
    }

    /**
     * @param  array<string|int, mixed>|null  $data
     * @return array<string|int, mixed>|null
     */
    private static function sanitize(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        $sanitized = [];
        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), self::SENSITIVE_KEYS, true)) {
                continue;
            }

            $sanitized[$key] = is_array($value) ? self::sanitize($value) : $value;
        }

        return $sanitized;
    }
}
