<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tools;

/**
 * Interface for all MCP tools
 *
 * Defines the contract that all tools must implement to be
 * registered with the MCP server
 */
interface ToolInterface
{
    /**
     * Get the tool name
     *
     * @return string The unique identifier for this tool
     */
    public function getName(): string;

    /**
     * Get the tool description
     *
     * @return string A human-readable description of what this tool does
     */
    public function getDescription(): string;

    /**
     * Get the validation schema for tool parameters
     *
     * Returns an array defining the validation rules for each parameter.
     * Format: ['param_name' => ['rule1', 'rule2', ...]]
     *
     * @return array<string, array<int, mixed>>
     */
    public function getSchema(): array;

    /**
     * Get the JSON Schema for tool input
     *
     * Returns a JSON Schema object defining the tool's input parameters
     * for MCP client validation
     *
     * @return array<string, mixed> JSON Schema object
     */
    public function getInputSchema(): array;

    /**
     * Execute the tool with given parameters
     *
     * @param array<string, mixed> $parameters The validated parameters
     * @return array<string, mixed> The result of the tool execution
     * @throws \Exception If execution fails
     */
    public function execute(array $parameters): array;

    /**
     * Get the required OAuth scope for this tool
     *
     * Returns the minimum scope required to execute this tool.
     * Scopes: mcp:read, mcp:write, mcp:delete, mcp:admin
     *
     * @return string The required scope
     */
    public function getRequiredScope(): string;
}
