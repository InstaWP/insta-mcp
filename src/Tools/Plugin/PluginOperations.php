<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tools\Plugin;

use InstaWP\MCP\PHP\Tools\AbstractTool;
use InstaWP\MCP\PHP\Exceptions\ToolException;

/**
 * Tool to manage plugin operations (install, activate, deactivate, update, delete)
 *
 * Handles five actions:
 * - install: Install plugin from wordpress.org, URL, or ZIP
 * - activate: Activate a plugin
 * - deactivate: Deactivate a plugin
 * - update: Update plugin to latest version
 * - delete: Delete plugin files
 */
class PluginOperations extends AbstractTool
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'plugin_operations';
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Manage plugin operations: install from WordPress.org/URL/ZIP, activate, deactivate, update, or delete plugins. '
            . 'Use action parameter to specify the operation.';
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
                ['in' => ['install', 'activate', 'deactivate', 'update', 'delete']]
            ],
            'plugin' => [
                'required',
                'string'
            ],

            // For 'install' action
            'source' => [
                'optional',
                'string',
                ['in' => ['wordpress.org', 'url', 'zip']]
            ],
            'source_data' => [
                'optional',
                'string'
            ],
            'activate_after_install' => [
                'optional',
                'bool'
            ],
            'check_dependencies' => [
                'optional',
                'bool'
            ],

            // For 'activate' action
            'network_wide' => [
                'optional',
                'bool'
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function doExecute(array $parameters): array
    {
        // All operations are blocked by safe mode
        $this->checkSafeMode('Plugin operations');

        $action = $parameters['action'];

        return match ($action) {
            'install' => $this->installPlugin($parameters),
            'activate' => $this->activatePlugin($parameters),
            'deactivate' => $this->deactivatePlugin($parameters),
            'update' => $this->updatePlugin($parameters),
            'delete' => $this->deletePlugin($parameters),
            default => throw ToolException::invalidInput("Invalid action: {$action}")
        };
    }

    /**
     * Install plugin from various sources
     */
    private function installPlugin(array $parameters): array
    {
        $plugin = $parameters['plugin'];
        $source = $parameters['source'] ?? 'wordpress.org';
        $sourceData = $parameters['source_data'] ?? null;
        $activateAfter = $parameters['activate_after_install'] ?? false;
        $checkDeps = $parameters['check_dependencies'] ?? true;

        // Install based on source
        $result = match ($source) {
            'wordpress.org' => $this->wp->installPluginFromSlug($plugin),
            'url' => $this->wp->installPluginFromUrl($sourceData ?? ''),
            'zip' => $this->wp->installPluginFromZip($sourceData ?? ''),
            default => throw ToolException::invalidInput("Invalid source: {$source}")
        };

        if (is_wp_error($result)) {
            throw ToolException::wordpressError($result->get_error_message());
        }

        // Get the installed plugin file
        $pluginFile = $this->wp->findPluginFile($plugin);

        if (!$pluginFile) {
            throw ToolException::notFound("Plugin installed but file not found: {$plugin}");
        }

        // Check dependencies if requested
        $depsResult = ['met' => true];
        if ($checkDeps) {
            $depsResult = $this->checkDependencies($pluginFile);
            if (!$depsResult['met']) {
                return $this->success([
                    'plugin' => $plugin,
                    'plugin_file' => $pluginFile,
                    'installed' => true,
                    'activated' => false,
                    'dependencies_met' => false,
                    'dependency_errors' => $depsResult,
                    'message' => 'Plugin installed but dependencies not met',
                ]);
            }
        }

        // Activate if requested
        $activated = false;
        if ($activateAfter) {
            $networkWide = $parameters['network_wide'] ?? false;
            $activateResult = $this->wp->activatePlugin($pluginFile, $networkWide);

            if (is_wp_error($activateResult)) {
                return $this->success([
                    'plugin' => $plugin,
                    'plugin_file' => $pluginFile,
                    'installed' => true,
                    'activated' => false,
                    'dependencies_met' => $depsResult['met'],
                    'activation_error' => $activateResult->get_error_message(),
                    'message' => 'Plugin installed but activation failed',
                ]);
            }

            $activated = true;
        }

        $pluginData = $this->wp->getPluginData($pluginFile);

        return $this->success([
            'plugin' => $plugin,
            'plugin_file' => $pluginFile,
            'version' => $pluginData['Version'] ?? '',
            'installed' => true,
            'activated' => $activated,
            'dependencies_met' => $depsResult['met'],
            'message' => $activated ? 'Plugin installed and activated successfully' : 'Plugin installed successfully',
        ]);
    }

    /**
     * Activate a plugin
     */
    private function activatePlugin(array $parameters): array
    {
        $plugin = $parameters['plugin'];
        $networkWide = $parameters['network_wide'] ?? false;

        // Find plugin file
        $pluginFile = $this->wp->findPluginFile($plugin);

        if (!$pluginFile) {
            throw ToolException::notFound("Plugin not found: {$plugin}");
        }

        // Check if already active
        if ($this->wp->isPluginActive($pluginFile)) {
            return $this->success([
                'plugin' => $pluginFile,
                'status' => 'active',
                'message' => 'Plugin is already active',
            ]);
        }

        // Activate plugin
        $result = $this->wp->activatePlugin($pluginFile, $networkWide);

        if (is_wp_error($result)) {
            throw ToolException::wordpressError($result->get_error_message());
        }

        return $this->success([
            'plugin' => $pluginFile,
            'status' => 'active',
            'message' => 'Plugin activated successfully',
        ]);
    }

    /**
     * Deactivate a plugin
     */
    private function deactivatePlugin(array $parameters): array
    {
        $plugin = $parameters['plugin'];

        // Find plugin file
        $pluginFile = $this->wp->findPluginFile($plugin);

        if (!$pluginFile) {
            throw ToolException::notFound("Plugin not found: {$plugin}");
        }

        // Check if already inactive
        if ($this->wp->isPluginInactive($pluginFile)) {
            return $this->success([
                'plugin' => $pluginFile,
                'status' => 'inactive',
                'message' => 'Plugin is already inactive',
            ]);
        }

        // Deactivate plugin
        $this->wp->deactivatePlugin($pluginFile);

        return $this->success([
            'plugin' => $pluginFile,
            'status' => 'inactive',
            'message' => 'Plugin deactivated successfully',
        ]);
    }

    /**
     * Update plugin to latest version
     */
    private function updatePlugin(array $parameters): array
    {
        $plugin = $parameters['plugin'];

        // Find plugin file
        $pluginFile = $this->wp->findPluginFile($plugin);

        if (!$pluginFile) {
            throw ToolException::notFound("Plugin not found: {$plugin}");
        }

        // Get current version
        $pluginData = $this->wp->getPluginData($pluginFile);
        $oldVersion = $pluginData['Version'] ?? '';

        // Check if update is available
        if (!$this->wp->hasPluginUpdate($pluginFile)) {
            return $this->success([
                'plugin' => $pluginFile,
                'version' => $oldVersion,
                'message' => 'Plugin is already up to date',
            ]);
        }

        // Upgrade plugin
        $result = $this->wp->upgradePlugin($pluginFile);

        if (is_wp_error($result)) {
            throw ToolException::wordpressError($result->get_error_message());
        }

        // Get new version
        $newPluginData = $this->wp->getPluginData($pluginFile);
        $newVersion = $newPluginData['Version'] ?? '';

        return $this->success([
            'plugin' => $pluginFile,
            'old_version' => $oldVersion,
            'new_version' => $newVersion,
            'message' => 'Plugin updated successfully',
        ]);
    }

    /**
     * Delete a plugin
     */
    private function deletePlugin(array $parameters): array
    {
        $plugin = $parameters['plugin'];

        // Find plugin file
        $pluginFile = $this->wp->findPluginFile($plugin);

        if (!$pluginFile) {
            throw ToolException::notFound("Plugin not found: {$plugin}");
        }

        // Check if plugin is active
        if ($this->wp->isPluginActive($pluginFile)) {
            throw ToolException::invalidInput("Cannot delete active plugin. Deactivate it first: {$pluginFile}");
        }

        // Delete plugin
        $result = $this->wp->deletePlugin($pluginFile);

        if (is_wp_error($result)) {
            throw ToolException::wordpressError($result->get_error_message());
        }

        return $this->success([
            'plugin' => $plugin,
            'deleted' => true,
            'message' => 'Plugin deleted successfully',
        ]);
    }

    /**
     * Check plugin dependencies (PHP and WordPress versions)
     */
    private function checkDependencies(string $pluginFile): array
    {
        $pluginData = $this->wp->getPluginData($pluginFile);

        $requiresPhp = $pluginData['RequiresPHP'] ?? '';
        $requiresWp = $pluginData['RequiresWP'] ?? '';

        $phpMet = empty($requiresPhp) || version_compare(PHP_VERSION, $requiresPhp, '>=');
        $wpMet = empty($requiresWp) || version_compare(get_bloginfo('version'), $requiresWp, '>=');

        return [
            'met' => $phpMet && $wpMet,
            'php' => [
                'required' => $requiresPhp ?: 'none',
                'current' => PHP_VERSION,
                'met' => $phpMet,
            ],
            'wordpress' => [
                'required' => $requiresWp ?: 'none',
                'current' => get_bloginfo('version'),
                'met' => $wpMet,
            ],
        ];
    }
}
