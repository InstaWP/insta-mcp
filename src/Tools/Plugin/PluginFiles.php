<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tools\Plugin;

use InstaWP\MCP\PHP\Tools\AbstractTool;
use InstaWP\MCP\PHP\Exceptions\ToolException;

/**
 * Tool to manage plugin file operations (read, write)
 *
 * Handles two actions:
 * - read: Read plugin file contents with security validation
 * - write: Write plugin file contents with automatic backup
 */
class PluginFiles extends AbstractTool
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'plugin_files';
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Manage plugin file operations: read or write plugin files with security validation. '
            . 'Supports path traversal prevention, extension whitelisting, and automatic backups for writes. '
            . 'Respects DISALLOW_FILE_EDIT and DISALLOW_FILE_MODS constants.';
    }

    /**
     * {@inheritdoc}
     */
    public function getRequiredScope(): string
    {
        return 'mcp:admin';
    }

    /**
     * {@inheritdoc}
     */
    public function getSchema(): array
    {
        return [
            'action' => [
                'required',
                'string',
                ['in' => ['read', 'write']]
            ],
            'plugin' => [
                'required',
                'string',
                ['description' => 'Plugin identifier (slug or plugin-dir/plugin.php)']
            ],
            'file_path' => [
                'required',
                'string',
                ['description' => 'Relative path within plugin (e.g., includes/config.php)']
            ],
            'content' => [
                'optional',
                'string',
                ['description' => 'File content for write action']
            ],
            'create_backup' => [
                'optional',
                ['description' => 'Create backup before writing (default: true)']
            ]
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function doExecute(array $parameters): array
    {
        // Get action
        $action = $parameters['action'];

        // Route to specific action
        return match ($action) {
            'read' => $this->readFile($parameters),
            'write' => $this->writeFile($parameters),
            default => throw ToolException::invalidInput("Invalid action: {$action}")
        };
    }

    /**
     * Read plugin file
     *
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    private function readFile(array $parameters): array
    {
        $plugin = $parameters['plugin'];
        $filePath = $parameters['file_path'];

        // Find plugin file if slug provided
        $pluginFile = $this->getPluginFile($plugin, $filePath);

        // Read file
        $result = $this->wp->readPluginFile($pluginFile, $filePath);

        if (!$result['success']) {
            throw ToolException::wordpressError($result['error'] ?? 'Failed to read file');
        }

        return $this->success([
            'plugin' => $pluginFile,
            'file_path' => $filePath,
            'content' => $result['content'],
            'size' => strlen($result['content'])
        ]);
    }

    /**
     * Write plugin file with backup
     *
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    private function writeFile(array $parameters): array
    {
        $this->checkSafeMode('Write plugin file');

        $plugin = $parameters['plugin'];
        $filePath = $parameters['file_path'];
        $content = $parameters['content'] ?? null;

        // Handle create_backup parameter (may come as string "true"/"false" or boolean)
        $createBackup = true; // default
        if (isset($parameters['create_backup'])) {
            if (is_bool($parameters['create_backup'])) {
                $createBackup = $parameters['create_backup'];
            } elseif (is_string($parameters['create_backup'])) {
                $createBackup = filter_var($parameters['create_backup'], FILTER_VALIDATE_BOOLEAN);
            }
        }

        if ($content === null) {
            throw ToolException::invalidInput('content parameter is required for write action');
        }

        // Find plugin file if slug provided (pass filePath to help construct path for new plugins)
        $pluginFile = $this->getPluginFile($plugin, $filePath);

        // Write file
        $result = $this->wp->writePluginFile($pluginFile, $filePath, $content, $createBackup);

        if (!$result['success']) {
            throw ToolException::wordpressError($result['error'] ?? 'Failed to write file');
        }

        $response = [
            'plugin' => $pluginFile,
            'file_path' => $filePath,
            'size' => strlen($content),
            'backup_created' => isset($result['backup_path'])
        ];

        if (isset($result['backup_path'])) {
            $response['backup_path'] = $result['backup_path'];
        }

        return $this->success($response);
    }

    /**
     * Get plugin file from plugin identifier
     *
     * @param string $plugin Plugin slug or plugin file
     * @param string|null $filePath Optional file path to help construct plugin file for new plugins
     * @return string Plugin file
     */
    private function getPluginFile(string $plugin, ?string $filePath = null): string
    {
        // If it looks like a plugin file (contains / or .php), use it directly
        if (strpos($plugin, '/') !== false || strpos($plugin, '.php') !== false) {
            return $plugin;
        }

        // Otherwise, try to find it by slug
        $pluginFile = $this->wp->findPluginFile($plugin);

        // If plugin not found and we're creating a new file, construct the plugin file path
        if (!$pluginFile && $filePath !== null) {
            // For new plugins, construct: plugin-slug/file-path
            return $plugin . '/' . $plugin . '.php';
        }

        if (!$pluginFile) {
            throw ToolException::invalidInput("Plugin not found: {$plugin}. For new plugins, use format 'plugin-dir/plugin-file.php' as the plugin parameter.");
        }

        return $pluginFile;
    }
}
