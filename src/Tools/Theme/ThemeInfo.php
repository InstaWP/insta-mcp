<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tools\Theme;

use InstaWP\MCP\PHP\Tools\AbstractTool;
use InstaWP\MCP\PHP\Exceptions\ToolException;

/**
 * Tool to get theme information, search WordPress.org, and list installed themes
 *
 * Handles three actions:
 * - search: Search WordPress.org theme repository
 * - list: List all installed themes with status
 * - get: Get detailed info about specific theme
 */
class ThemeInfo extends AbstractTool
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'theme_info';
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Get theme information, search WordPress.org repository, or list installed themes. '
            . 'Use action parameter to specify: search, list, or get.';
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
                ['in' => ['search', 'list', 'get']]
            ],

            // For 'search' action
            'query' => [
                'optional',
                'string'
            ],
            'page' => [
                'optional',
                'int',
                ['min' => 1]
            ],
            'per_page' => [
                'optional',
                'int',
                ['min' => 1],
                ['max' => 100]
            ],
            'tag' => [
                'optional',
                'string'
            ],

            // For 'get' action
            'theme' => [
                'optional',
                'string'
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function doExecute(array $parameters): array
    {
        $action = $parameters['action'];

        return match ($action) {
            'search' => $this->searchThemes($parameters),
            'list' => $this->listThemes($parameters),
            'get' => $this->getTheme($parameters),
            default => throw ToolException::invalidInput("Invalid action: {$action}")
        };
    }

    /**
     * Search WordPress.org theme repository
     */
    private function searchThemes(array $parameters): array
    {
        $args = [
            'search' => $parameters['query'] ?? '',
            'page' => $parameters['page'] ?? 1,
            'per_page' => $parameters['per_page'] ?? 24,
        ];

        if (isset($parameters['tag'])) {
            $args['tag'] = $parameters['tag'];
        }

        $result = $this->wp->searchThemesApi($args);

        if (is_wp_error($result)) {
            throw ToolException::wordpressError($result->get_error_message());
        }

        // Format response
        $themes = array_map(function ($theme) {
            return [
                'name' => $theme['name'] ?? $theme->name ?? '',
                'slug' => $theme['slug'] ?? $theme->slug ?? '',
                'version' => $theme['version'] ?? $theme->version ?? '',
                'author' => strip_tags($theme['author'] ?? $theme->author ?? ''),
                'rating' => $theme['rating'] ?? $theme->rating ?? 0,
                'num_ratings' => $theme['num_ratings'] ?? $theme->num_ratings ?? 0,
                'downloaded' => $theme['downloaded'] ?? $theme->downloaded ?? 0,
                'preview_url' => $theme['preview_url'] ?? $theme->preview_url ?? '',
                'screenshot_url' => $theme['screenshot_url'] ?? $theme->screenshot_url ?? '',
                'requires' => $theme['requires'] ?? $theme->requires ?? '',
                'requires_php' => $theme['requires_php'] ?? $theme->requires_php ?? '',
                'last_updated' => $theme['last_updated'] ?? $theme->last_updated ?? '',
            ];
        }, $result->themes ?? []);

        return $this->success([
            'themes' => $themes,
            'info' => [
                'page' => $result->info['page'] ?? 1,
                'pages' => $result->info['pages'] ?? 1,
                'results' => $result->info['results'] ?? 0,
            ],
        ]);
    }

    /**
     * List all installed themes with status
     */
    private function listThemes(array $parameters): array
    {
        $themes = $this->wp->getThemes();
        $currentTheme = $this->wp->getCurrentTheme();
        $formattedThemes = [];

        foreach ($themes as $stylesheet => $theme) {
            $isActive = ($stylesheet === $currentTheme->get_stylesheet());
            $hasUpdate = $this->wp->hasThemeUpdate($stylesheet);

            $formattedThemes[] = [
                'name' => $theme->get('Name'),
                'stylesheet' => $stylesheet,
                'version' => $theme->get('Version'),
                'status' => $isActive ? 'active' : 'inactive',
                'update_available' => $hasUpdate,
                'description' => $theme->get('Description'),
                'author' => strip_tags($theme->get('Author')),
                'author_uri' => $theme->get('AuthorURI'),
                'theme_uri' => $theme->get('ThemeURI'),
                'is_child_theme' => !empty($theme->parent()),
                'parent_theme' => $theme->parent() ? $theme->parent()->get('Name') : null,
                'screenshot' => $theme->get_screenshot(),
            ];
        }

        return $this->success([
            'themes' => $formattedThemes,
            'count' => [
                'all' => count($formattedThemes),
                'active' => 1, // Only one theme can be active
            ],
        ]);
    }

    /**
     * Get detailed info about specific theme
     */
    private function getTheme(array $parameters): array
    {
        if (!isset($parameters['theme'])) {
            throw ToolException::invalidInput('Theme parameter is required for get action');
        }

        $themeSlug = $parameters['theme'];
        $theme = $this->wp->getTheme($themeSlug);

        if (!$theme || !$theme->exists()) {
            throw ToolException::notFound("Theme not found: {$themeSlug}");
        }

        $currentTheme = $this->wp->getCurrentTheme();
        $isActive = ($theme->get_stylesheet() === $currentTheme->get_stylesheet());
        $hasUpdate = $this->wp->hasThemeUpdate($theme->get_stylesheet());
        $latestVersion = $this->wp->getThemeLatestVersion($theme->get_stylesheet());

        $data = [
            'name' => $theme->get('Name'),
            'stylesheet' => $theme->get_stylesheet(),
            'version' => $theme->get('Version'),
            'status' => $isActive ? 'active' : 'inactive',
            'update_available' => $hasUpdate,
            'latest_version' => $latestVersion,
            'description' => $theme->get('Description'),
            'author' => strip_tags($theme->get('Author')),
            'author_uri' => $theme->get('AuthorURI'),
            'theme_uri' => $theme->get('ThemeURI'),
            'requires_wp' => $theme->get('RequiresWP'),
            'requires_php' => $theme->get('RequiresPHP'),
            'is_child_theme' => !empty($theme->parent()),
            'screenshot' => $theme->get_screenshot(),
        ];

        // Add parent theme info if it's a child theme
        if ($theme->parent()) {
            $data['parent_theme'] = [
                'name' => $theme->parent()->get('Name'),
                'stylesheet' => $theme->parent()->get_stylesheet(),
                'version' => $theme->parent()->get('Version'),
            ];
        } else {
            $data['parent_theme'] = null;
        }

        return $this->success($data);
    }
}
