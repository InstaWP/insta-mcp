<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Services;

use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\ValidationException;
use InstaWP\MCP\PHP\Exceptions\ToolException;

/**
 * Service for validating tool parameters
 *
 * Uses Respect/Validation library to validate parameters against schemas
 */
class ValidationService
{
    /**
     * Validate parameters against a schema
     *
     * @param array<string, mixed> $parameters The parameters to validate
     * @param array<string, array<int, mixed>> $schema The validation schema
     * @throws ToolException If validation fails
     * @return void
     */
    public function validate(array $parameters, array $schema): void
    {
        $errors = [];

        foreach ($schema as $field => $rules) {
            try {
                $validator = $this->buildValidator($rules);
                $value = $parameters[$field] ?? null;

                // Check if field is required
                if ($this->isRequired($rules) && $value === null) {
                    $errors[$field] = "Field '{$field}' is required";
                    continue;
                }

                // Skip validation if optional and not provided
                if (!$this->isRequired($rules) && $value === null) {
                    continue;
                }

                // Validate the value
                $validator->assert($value);

            } catch (ValidationException $e) {
                $errors[$field] = $e->getMessage();
            }
        }

        if (!empty($errors)) {
            throw ToolException::validationFailed('Validation failed', $errors);
        }
    }

    /**
     * Build a validator from rules array
     *
     * @param array<int, mixed> $rules The validation rules
     * @return v
     */
    private function buildValidator(array $rules): v
    {
        $validator = v::alwaysValid();

        foreach ($rules as $rule) {
            if ($rule === 'required') {
                continue; // Handled separately
            }

            if ($rule === 'optional') {
                continue; // Handled separately
            }

            $validator = $this->applyRule($validator, $rule);
        }

        return $validator;
    }

    /**
     * Apply a single validation rule
     *
     * @param v $validator The current validator
     * @param mixed $rule The rule to apply
     * @return v
     */
    private function applyRule(v $validator, mixed $rule): v
    {
        if (is_string($rule)) {
            return match ($rule) {
                'string' => $validator->stringType(),
                'int' => $validator->intType(),
                'integer' => $validator->intType(),
                'float' => $validator->floatType(),
                'bool' => $validator->boolType(),
                'boolean' => $validator->boolType(),
                'array' => $validator->arrayType(),
                'email' => $validator->email(),
                'url' => $validator->url(),
                default => $validator,
            };
        }

        if (is_array($rule)) {
            foreach ($rule as $key => $value) {
                $validator = match ($key) {
                    'min' => $validator->min($value),
                    'max' => $validator->max($value),
                    'minLength' => $validator->length($value, null),
                    'maxLength' => $validator->length(null, $value),
                    'in' => $validator->in($value),
                    'notEmpty' => $validator->notEmpty(),
                    default => $validator,
                };
            }
        }

        return $validator;
    }

    /**
     * Check if a field is required
     *
     * @param array<int, mixed> $rules The validation rules
     * @return bool
     */
    private function isRequired(array $rules): bool
    {
        return in_array('required', $rules, true);
    }
}
