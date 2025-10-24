<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tools\Theme;

use InstaWP\MCP\PHP\Tools\AbstractTool;
use InstaWP\MCP\PHP\Exceptions\ToolException;

/**
 * Tool to manage theme operations (install, activate, update, delete)
 *
 * Handles four actions:
 * - install: Install theme from wordpress.org, URL, or ZIP
 * - activate: Switch to theme (activate it)
 * - update: Update theme to latest version
 * - delete: Delete theme files (cannot delete active theme)
 */
class ThemeOperations extends AbstractTool
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'theme_operations';
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Manage theme operations: install from WordPress.org/URL/ZIP, activate (switch to), update, or delete themes. '
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
                ['in' => ['install', 'activate', 'update', 'delete']]
            ],
            'theme' => [
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
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function doExecute(array $parameters): array
    {
        // All operations are blocked by safe mode
        $this->checkSafeMode('Theme operations');

        $action = $parameters['action'];

        return match ($action) {
            'install' => $this->installTheme($parameters),
            'activate' => $this->activateTheme($parameters),
            'update' => $this->updateTheme($parameters),
            'delete' => $this->deleteTheme($parameters),
            default => throw ToolException::invalidInput("Invalid action: {$action}")
        };
    }

    /**
     * Install theme from various sources
     */
    private function installTheme(array $parameters): array
    {
        $theme = $parameters['theme'];
        $source = $parameters['source'] ?? 'wordpress.org';
        $sourceData = $parameters['source_data'] ?? null;
        $activateAfter = $parameters['activate_after_install'] ?? false;
        $checkDeps = $parameters['check_dependencies'] ?? true;

        // Install based on source
        $result = match ($source) {
            'wordpress.org' => $this->wp->installThemeFromSlug($theme),
            'url' => $this->wp->installThemeFromUrl($sourceData ?? ''),
            'zip' => $this->wp->installThemeFromZip($sourceData ?? ''),
            default => throw ToolException::invalidInput("Invalid source: {$source}")
        };

        if (is_wp_error($result)) {
            throw ToolException::wordpressError($result->get_error_message());
        }

        // Get the installed theme
        $themeObj = $this->wp->getTheme($theme);

        if (!$themeObj || !$themeObj->exists()) {
            throw ToolException::notFound("Theme installed but not found: {$theme}");
        }

        // Check dependencies if requested
        $depsResult = ['met' => true];
        if ($checkDeps) {
            $depsResult = $this->checkDependencies($themeObj);
            if (!$depsResult['met']) {
                return $this->success([
                    'theme' => $theme,
                    'stylesheet' => $themeObj->get_stylesheet(),
                    'installed' => true,
                    'activated' => false,
                    'dependencies_met' => false,
                    'dependency_errors' => $depsResult,
                    'message' => 'Theme installed but dependencies not met',
                ]);
            }
        }

        // Activate if requested
        $activated = false;
        if ($activateAfter) {
            $this->wp->switchTheme($themeObj->get_stylesheet());
            $activated = true;
        }

        return $this->success([
            'theme' => $theme,
            'stylesheet' => $themeObj->get_stylesheet(),
            'version' => $themeObj->get('Version'),
            'installed' => true,
            'activated' => $activated,
            'dependencies_met' => $depsResult['met'],
            'message' => $activated ? 'Theme installed and activated successfully' : 'Theme installed successfully',
        ]);
    }

    /**
     * Activate a theme (switch to it)
     */
    private function activateTheme(array $parameters): array
    {
        $theme = $parameters['theme'];

        // Get theme object
        $themeObj = $this->wp->getTheme($theme);

        if (!$themeObj || !$themeObj->exists()) {
            throw ToolException::notFound("Theme not found: {$theme}");
        }

        // Check if already active
        $currentTheme = $this->wp->getCurrentTheme();
        if ($themeObj->get_stylesheet() === $currentTheme->get_stylesheet()) {
            return $this->success([
                'theme' => $themeObj->get_stylesheet(),
                'status' => 'active',
                'message' => 'Theme is already active',
            ]);
        }

        // Check for parent theme if it's a child theme
        if ($themeObj->parent()) {
            $parentTheme = $themeObj->parent();
            if (!$parentTheme->exists()) {
                throw ToolException::invalidInput(
                    "Child theme requires parent theme '{$parentTheme->get('Name')}' which is not installed"
                );
            }
        }

        // Switch to theme
        $this->wp->switchTheme($themeObj->get_stylesheet());

        return $this->success([
            'theme' => $themeObj->get_stylesheet(),
            'status' => 'active',
            'message' => 'Theme activated successfully',
        ]);
    }

    /**
     * Update theme to latest version
     */
    private function updateTheme(array $parameters): array
    {
        $theme = $parameters['theme'];

        // Get theme object
        $themeObj = $this->wp->getTheme($theme);

        if (!$themeObj || !$themeObj->exists()) {
            throw ToolException::notFound("Theme not found: {$theme}");
        }

        $stylesheet = $themeObj->get_stylesheet();
        $oldVersion = $themeObj->get('Version');

        // Check if update is available
        if (!$this->wp->hasThemeUpdate($stylesheet)) {
            return $this->success([
                'theme' => $stylesheet,
                'version' => $oldVersion,
                'message' => 'Theme is already up to date',
            ]);
        }

        // Upgrade theme
        $result = $this->wp->upgradeTheme($stylesheet);

        if (is_wp_error($result)) {
            throw ToolException::wordpressError($result->get_error_message());
        }

        // Get new version
        $newThemeObj = $this->wp->getTheme($stylesheet);
        $newVersion = $newThemeObj->get('Version');

        return $this->success([
            'theme' => $stylesheet,
            'old_version' => $oldVersion,
            'new_version' => $newVersion,
            'message' => 'Theme updated successfully',
        ]);
    }

    /**
     * Delete a theme
     */
    private function deleteTheme(array $parameters): array
    {
        $theme = $parameters['theme'];

        // Get theme object
        $themeObj = $this->wp->getTheme($theme);

        if (!$themeObj || !$themeObj->exists()) {
            throw ToolException::notFound("Theme not found: {$theme}");
        }

        $stylesheet = $themeObj->get_stylesheet();

        // Check if theme is active
        $currentTheme = $this->wp->getCurrentTheme();
        if ($stylesheet === $currentTheme->get_stylesheet()) {
            throw ToolException::invalidInput("Cannot delete active theme. Switch to another theme first: {$stylesheet}");
        }

        // Delete theme
        $result = $this->wp->deleteTheme($stylesheet);

        if (is_wp_error($result)) {
            throw ToolException::wordpressError($result->get_error_message());
        }

        return $this->success([
            'theme' => $theme,
            'deleted' => true,
            'message' => 'Theme deleted successfully',
        ]);
    }

    /**
     * Check theme dependencies (PHP and WordPress versions)
     */
    private function checkDependencies(\WP_Theme $theme): array
    {
        $requiresPhp = $theme->get('RequiresPHP');
        $requiresWp = $theme->get('RequiresWP');

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
