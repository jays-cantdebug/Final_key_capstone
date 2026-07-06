<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Central write path for every audit log entry, used by both
 * AuditableObserver (model lifecycle events) and the auth event
 * listeners (Login/Logout/Login Lockout).
 */
class AuditLogService
{
    public function __construct(private readonly Request $request)
    {
    }

    /**
     * Record an audit log entry.
     *
     * @param array<string, mixed>|null $oldValues
     * @param array<string, mixed>|null $newValues
     */
    public function record(
        string $module,
        string $action,
        ?int $recordId,
        ?array $oldValues,
        ?array $newValues,
        ?int $userId = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'user_id' => $userId ?? Auth::id(),
            'module' => $module,
            'action' => $action,
            'record_id' => $recordId,
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }

    /**
     * Paginate audit logs, most recent first, with optional filters.
     *
     * @param array<string, mixed> $filters
     */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return AuditLog::query()
            ->with('user')
            ->when($filters['module'] ?? null, fn ($query, $value) => $query->where('module', $value))
            ->when($filters['action'] ?? null, fn ($query, $value) => $query->where('action', $value))
            ->when($filters['date_from'] ?? null, fn ($query, $value) => $query->whereDate('created_at', '>=', $value))
            ->when($filters['date_to'] ?? null, fn ($query, $value) => $query->whereDate('created_at', '<=', $value))
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return array<int, string>
     */
    public function distinctModules(): array
    {
        return AuditLog::query()->distinct()->orderBy('module')->pluck('module')->all();
    }

    /**
     * @return array<int, string>
     */
    public function distinctActions(): array
    {
        return AuditLog::query()->distinct()->orderBy('action')->pluck('action')->all();
    }
}
