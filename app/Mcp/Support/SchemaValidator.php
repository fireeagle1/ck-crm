<?php

namespace App\Mcp\Support;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Support\Facades\Validator;

/**
 * Validates a `tools/call` argument map against a tool's JSON Schema.
 *
 * The {@see \App\Mcp\Registry\ToolRegistry} describes each tool's parameters as
 * a JSON Schema object node (`{ type: object, properties: {...}, required: [...] }`).
 * This helper derives an equivalent set of Laravel validation rules from that
 * schema and runs the arguments through Laravel's validator, so the transport
 * can enforce — at minimum — required fields, scalar types, and enum
 * membership before dispatching a tool.
 *
 * It intentionally covers the schema features the registry actually uses
 * (object with typed scalar properties, `required`, `enum`, numeric
 * `minimum`/`maximum`). Deeper per-domain business rules (e.g. a `company_id`
 * must exist) remain the responsibility of the concrete write tools wired in
 * later tasks; this is the transport-level schema gate.
 */
final class SchemaValidator
{
    /**
     * Build (but do not run) a Laravel validator for the given arguments and
     * JSON Schema. Returning the validator lets callers decide how to render
     * failures (e.g. into a JSON-RPC error naming the invalid fields).
     *
     * @param  array<string, mixed>  $arguments  The `params.arguments` map.
     * @param  array<string, mixed>  $schema     The tool's JSON Schema object node.
     */
    public static function make(array $arguments, array $schema): ValidatorContract
    {
        return Validator::make($arguments, self::rulesFromSchema($schema));
    }

    /**
     * Derive Laravel validation rules from a JSON Schema object node.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, array<int, string>>
     */
    public static function rulesFromSchema(array $schema): array
    {
        $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
        $required = is_array($schema['required'] ?? null) ? $schema['required'] : [];
        $required = array_map('strval', $required);

        $rules = [];

        foreach ($properties as $field => $property) {
            if (! is_array($property)) {
                continue;
            }

            $fieldRules = [];

            // Required vs optional. Optional fields are only validated when
            // present, so callers may omit them entirely.
            $fieldRules[] = in_array((string) $field, $required, true) ? 'required' : 'sometimes';

            // Type mapping. JSON Schema scalar types -> Laravel rules.
            foreach (self::typeRules((string) ($property['type'] ?? '')) as $rule) {
                $fieldRules[] = $rule;
            }

            // Enum membership.
            if (isset($property['enum']) && is_array($property['enum']) && $property['enum'] !== []) {
                $fieldRules[] = 'in:'.implode(',', array_map('strval', $property['enum']));
            }

            // Numeric bounds (used by page / per_page).
            if (array_key_exists('minimum', $property) && is_numeric($property['minimum'])) {
                $fieldRules[] = 'min:'.$property['minimum'];
            }
            if (array_key_exists('maximum', $property) && is_numeric($property['maximum'])) {
                $fieldRules[] = 'max:'.$property['maximum'];
            }

            $rules[(string) $field] = $fieldRules;
        }

        return $rules;
    }

    /**
     * Map a JSON Schema scalar type to Laravel type rules.
     *
     * @return array<int, string>
     */
    private static function typeRules(string $type): array
    {
        return match ($type) {
            'integer' => ['integer'],
            'number' => ['numeric'],
            'boolean' => ['boolean'],
            'string' => ['string'],
            'array' => ['array'],
            'object' => ['array'],
            default => [],
        };
    }
}
