<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Exceptions;

/**
 * Exception thrown by tools during execution
 */
class ToolException extends \Exception
{
    /**
     * Create a validation error exception
     *
     * @param string $message The error message
     * @param array<string, mixed> $errors The validation errors
     * @return self
     */
    public static function validationFailed(string $message, array $errors): self
    {
        $exception = new self($message);
        $exception->errors = $errors;
        return $exception;
    }

    /**
     * Create a WordPress error exception
     *
     * @param string $message The error message
     * @param int $code Error code
     * @return self
     */
    public static function wordpressError(string $message, int $code = 0): self
    {
        return new self("WordPress Error: {$message}", $code);
    }

    /**
     * Create a safe mode violation exception
     *
     * @param string $operation The operation that was blocked
     * @return self
     */
    public static function safeModeViolation(string $operation): self
    {
        $exception = new self(
            "Operation blocked: Safe mode is enabled. {$operation} is not allowed."
        );
        $exception->errors = ['safe_mode' => 'enabled'];
        return $exception;
    }

    /**
     * Validation errors (if any)
     *
     * @var array<string, mixed>
     */
    private array $errors = [];

    /**
     * Get validation errors
     *
     * @return array<string, mixed>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
