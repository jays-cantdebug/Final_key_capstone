<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\AuditLogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Read-only viewing of the audit trail (Psychometrician only).
 */
class AuditLogController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['module', 'action', 'date_from', 'date_to']);

        return view('audit-logs.index', [
            'logs' => $this->auditLogService->paginate($filters),
            'filters' => $filters,
            'modules' => $this->auditLogService->distinctModules(),
            'actions' => $this->auditLogService->distinctActions(),
        ]);
    }

    public function show(AuditLog $auditLog): View
    {
        $auditLog->load('user');

        return view('audit-logs.show', ['log' => $auditLog]);
    }
}
