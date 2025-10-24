<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tools\Plugin;

use InstaWP\MCP\PHP\Tools\AbstractTool;
use InstaWP\MCP\PHP\Exceptions\ToolException;

/**
 * Tool to get plugin information, search WordPress.org, and list installed plugins
 *
 * Handles three actions:
 * - search: Search WordPress.org plugin repository
 * - list: List all installed plugins with status
 * - get: Get detailed info about specific plugin
 */
class PluginInfo extends AbstractTool
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'plugin_info';
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Get plugin information, search WordPress.org repository, or list installed plugins. '
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
            'author' => [
                'optional',
                'string'
            ],
            'tag' => [
                'optional',
                'string'
            ],

            // For 'list' action
            'status' => [
                'optional',
                'string',
                ['in' => ['all', 'active', 'inactive', 'update_available']]
            ],

            // For 'get' action
            'plugin' => [
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
            'search' => $this->searchPlugins($parameters),
            'list' => $this->listPlugins($parameters),
            'get' => $this->getPlugin($parameters),
            default => throw ToolException::invalidInput("Invalid action: {$action}")
        };
    }

    /**
     * Search WordPress.org plugin repository
     */
    private function searchPlugins(array $parameters): array
    {
        $args = [
            'search' => $parameters['query'] ?? '',
            'page' => $parameters['page'] ?? 1,
            'per_page' => $parameters['per_page'] ?? 24,
        ];

        if (isset($parameters['author'])) {
            $args['author'] = $parameters['author'];
        }

        if (isset($parameters['tag'])) {
            $args['tag'] = $parameters['tag'];
        }

        $result = $this->wp->searchPluginsApi($args);

        if (is_wp_error($result)) {
            throw ToolException::wordpressError($result->get_error_message());
        }

        // Format response
        $plugins = array_map(function ($plugin) {
            return [
                'name' => $plugin['name'] ?? $plugin->name ?? '',
                'slug' => $plugin['slug'] ?? $plugin->slug ?? '',
                'version' => $plugin['version'] ?? $plugin->version ?? '',
                'author' => strip_tags($plugin['author'] ?? $plugin->author ?? ''),
                'rating' => $plugin['rating'] ?? $plugin->rating ?? 0,
                'num_ratings' => $plugin['num_ratings'] ?? $plugin->num_ratings ?? 0,
                'downloaded' => $plugin['downloaded'] ?? $plugin->downloaded ?? 0,
                'short_description' => $plugin['short_description'] ?? $plugin->short_description ?? '',
                'requires' => $plugin['requires'] ?? $plugin->requires ?? '',
                'requires_php' => $plugin['requires_php'] ?? $plugin->requires_php ?? '',
                'last_updated' => $plugin['last_updated'] ?? $plugin->last_updated ?? '',
            ];
        }, $result->plugins ?? []);

        return $this->success([
            'plugins' => $plugins,
            'info' => [
                'page' => $result->info['page'] ?? 1,
                'pages' => $result->info['pages'] ?? 1,
                'results' => $result->info['results'] ?? 0,
            ],
        ]);
    }

    /**
     * List all installed plugins with status
     */
    private function listPlugins(array $parameters): array
    {
        $status = $parameters['status'] ?? 'all';

        $plugins = $this->wp->getPlugins();
        $formattedPlugins = [];
        $counts = [
            'all' => 0,
            'active' => 0,
            'inactive' => 0,
            'update_available' => 0,
        ];

        foreach ($plugins as $pluginFile => $pluginData) {
            $isActive = $this->wp->isPluginActive($pluginFile);
            $hasUpdate = $this->wp->hasPluginUpdate($pluginFile);

            $counts['all']++;
            if ($isActive) {
                $counts['active']++;
            } else {
                $counts['inactive']++;
            }
            if ($hasUpdate) {
                $counts['update_available']++;
            }

            // Filter by status
            if ($status !== 'all') {
                if ($status === 'active' && !$isActive) {
                    continue;
                }
                if ($status === 'inactive' && $isActive) {
                    continue;
                }
                if ($status === 'update_available' && !$hasUpdate) {
                    continue;
                }
            }

            $formattedPlugins[] = [
                'name' => $pluginData['Name'],
                'plugin_file' => $pluginFile,
                'version' => $pluginData['Version'],
                'status' => $isActive ? 'active' : 'inactive',
                'update_available' => $hasUpdate,
                'network_active' => is_plugin_active_for_network($pluginFile),
                'description' => $pluginData['Description'],
                'author' => strip_tags($pluginData['Author'] ?? ''),
                'author_uri' => $pluginData['AuthorURI'] ?? '',
            ];
        }

        return $this->success([
            'plugins' => $formattedPlugins,
            'count' => $counts,
        ]);
    }

    /**
     * Get detailed info about specific plugin
     */
    private function getPlugin(array $parameters): array
    {
        if (!isset($parameters['plugin'])) {
            throw ToolException::invalidInput('Plugin parameter is required for get action');
        }

        $plugin = $parameters['plugin'];

        // Check if it's a plugin file path or just slug
        $pluginFile = $this->wp->findPluginFile($plugin);

        if (!$pluginFile) {
            throw ToolException::notFound("Plugin not found: {$plugin}");
        }

        $pluginData = $this->wp->getPluginData($pluginFile);
        $isActive = $this->wp->isPluginActive($pluginFile);
        $hasUpdate = $this->wp->hasPluginUpdate($pluginFile);
        $latestVersion = $this->wp->getPluginLatestVersion($pluginFile);

        return $this->success([
            'name' => $pluginData['Name'],
            'plugin_file' => $pluginFile,
            'version' => $pluginData['Version'],
            'status' => $isActive ? 'active' : 'inactive',
            'update_available' => $hasUpdate,
            'latest_version' => $latestVersion,
            'description' => $pluginData['Description'],
            'author' => strip_tags($pluginData['Author'] ?? ''),
            'plugin_uri' => $pluginData['PluginURI'] ?? '',
            'requires_wp' => $pluginData['RequiresWP'] ?? '',
            'requires_php' => $pluginData['RequiresPHP'] ?? '',
            'network_only' => $pluginData['Network'] ?? false,
        ]);
    }
}
