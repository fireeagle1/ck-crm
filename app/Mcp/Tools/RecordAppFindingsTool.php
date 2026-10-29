<?php

namespace App\Mcp\Tools;

use App\Exceptions\InvalidFindingsPayloadException;
use App\Exceptions\UnknownCustomerAppException;
use App\Services\FindingsIngestService;

/**
 * MCP write tool backing `record_app_findings` (scope `apps:write`, handler key
 * `apps.record_findings`).
 *
 * This is the tool-call front door to {@see FindingsIngestService} — the same
 * storage half used by the REST findings endpoint
 * ({@see \App\Http\Controllers\Api\FindingsController}). It lets ChatGPT, after
 * reviewing a customer web app's repository, write back the full set of
 * packages (name + version) and security findings (severity + description +
 * status) it discovered, as a first-class MCP tool rather than a raw REST POST.
 *
 * Replace semantics (Req 2.3, 2.5, 3.2): a call bulk-replaces the app's stored
 * packages and findings in a single transaction, so the connector must send the
 * COMPLETE current set every time, not a delta. Re-sending the same payload is
 * idempotent.
 *
 * No-mutation-on-failure, mirroring {@see UpdateCustomerAppTool}:
 *
 *  - If no app matches `app_id`, the underlying service throws
 *    {@see UnknownCustomerAppException} before any write; this tool catches it
 *    and returns a structured not-found error, so nothing is persisted.
 *  - If the payload fails the service's allow-list validation, it throws
 *    {@see InvalidFindingsPayloadException} before any write; this tool catches
 *    it and returns a structured field-level error, leaving the records
 *    unchanged.
 *
 * The error envelope mirrors {@see UpdateCustomerAppTool}'s shape
 * (`{ error: { code, message, fields? } }`) so the connector sees a consistent,
 * actionable error whether a write tool rejects input here or at the transport
 * schema gate. A successful call returns a counts-only summary; technical
 * detail (package names, descriptions, repo URLs) is never echoed back
 * (Req 3.2, 3.7).
 *
 * The handler is exposed through {@see self::handle()} with the dispatcher's
 * `fn (array $arguments): array` shape and bound to the registry handler key
 * `apps.record_findings` in {@see \App\Providers\McpServiceProvider}; this class
 * does not register itself.
 *
 * @see \App\Services\FindingsIngestService
 * @see \App\Mcp\Registry\ToolRegistry
 */
final class RecordAppFindingsTool
{
    /**
     * Error code for a reference to an app that does not exist, aligned with
     * the JSON-RPC "invalid params" code the transport and sibling write tools
     * use for bad input.
     */
    private const ERR_NOT_FOUND = -32602;

    /**
     * Error code for a payload that fails allow-list validation, aligned with
     * the JSON-RPC "invalid params" code.
     */
    private const ERR_VALIDATION = -32602;

    public function __construct(
        private readonly FindingsIngestService $ingestService,
    ) {
    }

    /**
     * Handle a `record_app_findings` tool call.
     *
     * Expects the validated `params.arguments` map: a required integer `app_id`
     * identifying the Web App, plus `packages` and `findings` arrays. Either
     * array may be empty (an empty `findings` array with packages records a
     * clean review); both default to empty when omitted.
     *
     * @param  array<string, mixed>  $arguments  The validated tool arguments.
     * @return array<string, mixed> A counts-only success summary, or a structured
     *                              error ({ error: { code, message, fields? } })
     *                              on an unknown app or invalid payload — in which
     *                              case NO mutation is performed.
     */
    public function handle(array $arguments): array
    {
        $appId = (int) ($arguments['app_id'] ?? 0);

        $packages = array_values((array) ($arguments['packages'] ?? []));
        $findings = array_values((array) ($arguments['findings'] ?? []));

        try {
            $counts = $this->ingestService->ingest((string) $appId, $packages, $findings);
        } catch (UnknownCustomerAppException $e) {
            return $this->notFoundError($appId);
        } catch (InvalidFindingsPayloadException $e) {
            return $this->validationError($e);
        }

        return [
            'app_id' => $appId,
            'packages' => $counts['packages'],
            'findings' => $counts['findings'],
        ];
    }

    /**
     * A structured not-found error for a missing app. No mutation occurs.
     *
     * @return array<string, mixed>
     */
    private function notFoundError(int $appId): array
    {
        return [
            'error' => [
                'code' => self::ERR_NOT_FOUND,
                'message' => sprintf('Web app %d does not exist.', $appId),
                'fields' => [
                    'app_id' => ['The selected app_id does not exist.'],
                ],
            ],
        ];
    }

    /**
     * A structured field-level error from a failed payload validation. The
     * service collects every offending field; they are surfaced under a single
     * `payload` key so the connector can correct and resend the whole set.
     *
     * @return array<string, mixed>
     */
    private function validationError(InvalidFindingsPayloadException $e): array
    {
        return [
            'error' => [
                'code' => self::ERR_VALIDATION,
                'message' => 'Invalid arguments for "record_app_findings".',
                'fields' => [
                    'payload' => $e->errors(),
                ],
            ],
        ];
    }
}
