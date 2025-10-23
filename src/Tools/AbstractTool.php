<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tools;

use InstaWP\MCP\PHP\Services\ValidationService;
use InstaWP\MCP\PHP\Services\WordPressService;
use InstaWP\MCP\PHP\Exceptions\ToolException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Abstract base class for all MCP tools
 *
 * Provides common functionality like validation, logging, and error handling
 */
abstract class AbstractTool implements ToolInterface
{
    protected WordPressService $wp;
    protected ValidationService $validator;
    protected LoggerInterface $logger;
    protected ?array $userContext = null;

    public function __construct(
        WordPressService $wp,
        ValidationService $validator,
        ?LoggerInterface $logger = null
    ) {
        $this->wp = $wp;
        $this->validator = $validator;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Set user context from authentication
     *
     * @param array{user_id: int, scopes: array, username: string, roles: array} $context
     */
    public function setUserContext(array $context): void
    {
        $this->userContext = $context;
    }

    /**
     * {@inheritdoc}
     */
    abstract public function getName(): string;

    /**
     * {@inheritdoc}
     */
    abstract public function getDescription(): string;

    /**
     * {@inheritdoc}
     */
    abstract public function getSchema(): array;

    /**
     * {@inheritdoc}
     */
    public function getInputSchema(): array
    {
        $schema = $this->getSchema();
        $properties = [];
        $required = [];

        foreach ($schema as $paramName => $rules) {
            $isRequired = in_array('required', $rules, true);
            $isOptional = in_array('optional', $rules, true);

            if ($isRequired) {
                $required[] = $paramName;
            }

            // Determine JSON Schema type from our validation rules
            $type = 'string'; // default
            foreach ($rules as $rule) {
                if ($rule === 'int' || $rule === 'intType') {
                    $type = 'integer';
                } elseif ($rule === 'bool' || $rule === 'boolType') {
                    $type = 'boolean';
                } elseif ($rule === 'array' || $rule === 'arrayType') {
                    $type = 'array';
                } elseif ($rule === 'string' || $rule === 'stringType') {
                    $type = 'string';
                }
            }

            $properties[$paramName] = ['type' => $type];
        }

        $jsonSchema = [
            'type' => 'object',
            'properties' => $properties,
        ];

        if (!empty($required)) {
            $jsonSchema['required'] = $required;
        }

        return $jsonSchema;
    }

    /**
     * Execute the tool with parameter validation
     *
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     * @throws ToolException
     */
    final public function execute(array $parameters): array
    {
        try {
            // Check OAuth scope authorization (if user context is set)
            if ($this->userContext !== null) {
                $this->checkScopeAuthorization();
            }

            // Validate parameters
            $this->validator->validate($parameters, $this->getSchema());

            $this->logger->info("Executing tool: {$this->getName()}", [
                'parameters' => $parameters,
                'user' => $this->userContext['login'] ?? $this->userContext['username'] ?? 'anonymous'
            ]);

            // Execute the tool-specific logic
            $result = $this->doExecute($parameters);

            $this->logger->info("Tool executed successfully: {$this->getName()}");

            return $result;

        } catch (ToolException $e) {
            $this->logger->error("Tool execution failed: {$this->getName()}", [
                'error' => $e->getMessage(),
                'errors' => $e->getErrors()
            ]);
            throw $e;

        } catch (\Exception $e) {
            $this->logger->error("Unexpected error in tool: {$this->getName()}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw ToolException::wordpressError($e->getMessage(), $e->getCode());
        }
    }

    /**
     * Check if user has required OAuth scope for this tool
     *
     * @throws ToolException If user doesn't have required scope
     */
    protected function checkScopeAuthorization(): void
    {
        $requiredScope = $this->getRequiredScope();
        $userScopes = $this->userContext['scopes'] ?? [];

        // Admin scope grants all permissions
        if (in_array('mcp:admin', $userScopes, true)) {
            return;
        }

        // Check if user has the required scope
        if (!in_array($requiredScope, $userScopes, true)) {
            throw ToolException::insufficientPermissions(
                "This tool requires the '{$requiredScope}' scope. You have: " . implode(', ', $userScopes)
            );
        }
    }

    /**
     * Tool-specific execution logic
     *
     * Override this method in concrete tool classes
     *
     * @param array<string, mixed> $parameters Validated parameters
     * @return array<string, mixed> Tool execution result
     * @throws ToolException
     */
    abstract protected function doExecute(array $parameters): array;

    /**
     * Format a successful response
     *
     * @param mixed $data The data to return
     * @param string|null $message Optional success message
     * @return array<string, mixed>
     */
    protected function success(mixed $data, ?string $message = null): array
    {
        $response = [
            'success' => true,
            'data' => $data,
        ];

        if ($message !== null) {
            $response['message'] = $message;
        }

        return $response;
    }

    /**
     * Format an error response
     *
     * @param string $message The error message
     * @param array<string, mixed> $errors Optional error details
     * @return array<string, mixed>
     */
    protected function error(string $message, array $errors = []): array
    {
        return [
            'success' => false,
            'error' => $message,
            'errors' => $errors,
        ];
    }

    /**
     * Check if safe mode is enabled and throw exception if operation is not allowed
     *
     * This method should be called by tools that perform destructive operations
     * (like deletions) to ensure they respect the safe mode setting.
     *
     * @param string $operation Description of the operation being attempted
     * @throws ToolException If safe mode is enabled
     */
    protected function checkSafeMode(string $operation = 'this operation'): void
    {
        if (defined('WP_MCP_SAFE_MODE') && WP_MCP_SAFE_MODE === true) {
            throw ToolException::safeModeViolation($operation);
        }
    }

    /**
     * Default implementation: read-only tools
     *
     * Override this method in tools that require write or delete permissions
     *
     * @return string
     */
    public function getRequiredScope(): string
    {
        return 'mcp:read';
    }
}
