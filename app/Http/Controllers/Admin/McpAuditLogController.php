<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\McpAuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin-only, read-only viewer for the MCP audit trail (mcp_audit_logs).
 *
 * Every MCP `tools/call` and every denied authorization attempt writes exactly
 * one {@see McpAuditLog} row via {@see \App\Mcp\Audit\McpAuditLogger}. This
 * screen surfaces those rows so an administrator can see what actions the
 * ChatGPT MCP connector has taken. It lives inside the admin settings area
 * (auth + verified + EnsureIsAdmin applied by the route group) as its own
 * option beside "MCP Tokens", and is NEVER exposed to the MCP surface itself.
 *
 * The table records only non-sensitive references (token id, identity, tool
 * name, scope, outcome, reason, non-sensitive context) — never token values
 * or request bodies — so the full row is safe to display.
 */
class McpAuditLogController extends Controller
{
    /**
     * List MCP audit entries, newest first, with optional filtering.
     *
     * Supported query filters:
     *  - outcome:   success | denied | error
     *  - tool_name: exact tool name (e.g. get_customer)
     *  - identity:  partial match on the MCP identity (bot email / client name)
     */
    public function index(Request $request): View
    {
        $filters = [
            'outcome'   => $request->string('outcome')->toString(),
            'tool_name' => $request->string('tool_name')->toString(),
            'identity'  => $request->string('identity')->toString(),
        ];

        $validOutcome = in_array($filters['outcome'], ['success', 'denied', 'error'], true);

        $logs = McpAuditLog::query()
            ->when($validOutcome, fn ($query) => $query->where('outcome', $filters['outcome']))
            ->when($filters['tool_name'] !== '', fn ($query) => $query->where('tool_name', $filters['tool_name']))
            ->when($filters['identity'] !== '', fn ($query) => $query->where('mcp_identity', 'like', '%'.$filters['identity'].'%'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        // Distinct tool names actually present, for the filter dropdown.
        $toolNames = McpAuditLog::query()
            ->whereNotNull('tool_name')
            ->distinct()
            ->orderBy('tool_name')
            ->pluck('tool_name');

        return view('admin.mcp.audit', [
            'logs'      => $logs,
            'filters'   => $filters,
            'toolNames' => $toolNames,
        ]);
    }
}
