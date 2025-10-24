<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tools\Theme;

use InstaWP\MCP\PHP\Tools\AbstractTool;
use InstaWP\MCP\PHP\Exceptions\ToolException;

/**
 * Tool to manage theme file operations (read, write)
 *
 * Handles two actions:
 * - read: Read theme file contents with security validation
 * - write: Write theme file contents with automatic backup
 */
class ThemeFiles extends AbstractTool
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'theme_files';
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Manage theme file operations: read or write theme files with security validation. '
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
            'theme' => [
                'required',
                'string',
                ['description' => 'Theme stylesheet (directory name)']
            ],
            'file_path' => [
                'required',
                'string',
                ['description' => 'Relative path within theme (e.g., style.css, functions.php)']
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
     * Read theme file
     *
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    private function readFile(array $parameters): array
    {
        $theme = $parameters['theme'];
        $filePath = $parameters['file_path'];

        // Verify theme exists
        $themeObj = wp_get_theme($theme);
        if (!$themeObj->exists()) {
            throw ToolException::invalidInput("Theme not found: {$theme}");
        }

        // Read file
        $result = $this->wp->readThemeFile($theme, $filePath);

        if (!$result['success']) {
            throw ToolException::wordpressError($result['error'] ?? 'Failed to read file');
        }

        return $this->success([
            'theme' => $theme,
            'theme_name' => $themeObj->get('Name'),
            'file_path' => $filePath,
            'content' => $result['content'],
            'size' => strlen($result['content'])
        ]);
    }

    /**
     * Write theme file with backup
     *
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    private function writeFile(array $parameters): array
    {
        $this->checkSafeMode('Write theme file');

        $theme = $parameters['theme'];
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

        // Verify theme exists
        $themeObj = wp_get_theme($theme);
        if (!$themeObj->exists()) {
            throw ToolException::invalidInput("Theme not found: {$theme}");
        }

        // Write file
        $result = $this->wp->writeThemeFile($theme, $filePath, $content, $createBackup);

        if (!$result['success']) {
            throw ToolException::wordpressError($result['error'] ?? 'Failed to write file');
        }

        $response = [
            'theme' => $theme,
            'theme_name' => $themeObj->get('Name'),
            'file_path' => $filePath,
            'size' => strlen($content),
            'backup_created' => isset($result['backup_path'])
        ];

        if (isset($result['backup_path'])) {
            $response['backup_path'] = $result['backup_path'];
        }

        return $this->success($response);
    }
}
