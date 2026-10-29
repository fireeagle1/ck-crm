<?php

namespace App\Mcp\Tools;

use App\Mcp\Dto\CustomerAppDto;
use App\Models\CustomerApp;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * MCP write tool backing `update_app` (scope `apps:write`, handler key
 * `apps.update`).
 *
 * Writes back the editable fields of a Web App (CustomerApp): the
 * customer-facing `display_name` and `install_url`, plus the technical
 * metadata `repo_url`, `hosting_location`, `codebase_info`. Billing and status
 * live on the linked Service and are never touched here, exactly as in the
 * admin UI
 * ({@see \App\Http\Controllers\Admin\CustomerAppController::update()}).
 *
 * Only the fields present in the arguments are updated, so the connector can
 * patch a single field without blanking the others. Validation mirrors the
 * admin controller's inline rules.
 *
 * No-mutation-on-failure is enforced two ways:
 *
 *  - If no app matches the supplied `id`, the tool returns a structured
 *    not-found error BEFORE any write, so nothing is persisted.
 *  - If the supplied fields fail validation, a {@see ValidationException} is
 *    thrown before the model is touched; the tool catches it and returns a
 *    structured field-level error, leaving the record unchanged.
 *
 * The error envelope mirrors the transport's schema-validation error shape
 * (`{ error: { code, message, fields? } }`) so the connector sees a consistent
 * shape whether the failure is caught at the schema gate or here.
 */
final class UpdateCustomerAppTool
{
    /**
     * Error code for a reference to an app that does not exist, aligned with
     * the JSON-RPC "invalid params" code used by the transport for bad input.
     */
    private const ERR_NOT_FOUND = -32602;

    /**
     * Error code for update fields that fail validation, aligned with the
     * JSON-RPC "invalid params" code.
     */
    private const ERR_VALIDATION = -32602;

    /**
     * The fields a connector may write back. Everything else (including the
     * `id` selector and the linked service identity) is ignored.
     *
     * @var array<int, string>
     */
    private const EDITABLE = ['display_name', 'install_url', 'repo_url', 'hosting_location', 'codebase_info'];

    /**
     * Handle an `update_app` tool call.
     *
     * Expects the validated `params.arguments` map: a required integer `id`
     * identifying the Web App, plus any subset of the editable fields.
     *
     * @param  array<string, mixed>  $arguments  The validated tool arguments.
     * @return array<string, mixed> A CustomerApp DTO on success, or a structured
     *                              error ({ error: { code, message, fields? } })
     *                              on a missing record or validation failure —
     *                              in which case NO mutation is performed.
     */
    public function handle(array $arguments): array
    {
        $id = (int) ($arguments['id'] ?? 0);

        $app = CustomerApp::query()->with('service')->whereKey($id)->first();

        // No-mutation-on-failure: an unknown app is rejected before any write.
        if ($app === null) {
            return $this->notFoundError($id);
        }

        // Keep only the recognised editable fields that were actually supplied,
        // so a missing field is left untouched rather than nulled.
        $data = array_intersect_key($arguments, array_flip(self::EDITABLE));

        if ($data === []) {
            return [
                'error' => [
                    'code' => self::ERR_VALIDATION,
                    'message' => 'No updatable fields were provided for "update_app".',
                    'fields' => [
                        'display_name' => ['Provide at least one of display_name, install_url, repo_url, hosting_location, or codebase_info.'],
                    ],
                ],
            ];
        }

        try {
            $validated = $this->validate($data);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        }

        $app->update($validated);

        return CustomerAppDto::fromModel($app->fresh('service'));
    }

    /**
     * Validate the supplied fields against the same rules the admin UI applies.
     * Only the keys present are validated (`sometimes`), so a partial update
     * stays partial.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validate(array $data): array
    {
        return Validator::make($data, [
            'display_name' => 'sometimes|nullable|string|max:255',
            'install_url' => 'sometimes|nullable|url|max:255',
            'repo_url' => 'sometimes|nullable|url|max:255',
            'hosting_location' => 'sometimes|nullable|string|max:255',
            'codebase_info' => 'sometimes|nullable|string',
        ])->validate();
    }

    /**
     * A structured not-found error for a missing app. No mutation occurs.
     *
     * @return array<string, mixed>
     */
    private function notFoundError(int $id): array
    {
        return [
            'error' => [
                'code' => self::ERR_NOT_FOUND,
                'message' => sprintf('Web app %d does not exist.', $id),
                'fields' => [
                    'id' => ['The selected id does not exist.'],
                ],
            ],
        ];
    }

    /**
     * A structured field-level error from a failed validation. Naming the
     * offending fields matches the transport's schema-validation error shape.
     *
     * @return array<string, mixed>
     */
    private function validationError(ValidationException $e): array
    {
        return [
            'error' => [
                'code' => self::ERR_VALIDATION,
                'message' => 'Invalid arguments for "update_app".',
                'fields' => $e->errors(),
            ],
        ];
    }
}
