<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tools\Content;

use InstaWP\MCP\PHP\Tools\AbstractTool;

/**
 * Tool to discover available content types (post types)
 *
 * Lists all registered post types with their capabilities and settings
 */
class DiscoverContentTypes extends AbstractTool
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'discover_content_types';
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Discover all available content types (post types) in WordPress. '
            . 'Returns information about posts, pages, and custom post types.';
    }

    /**
     * {@inheritdoc}
     */
    public function getSchema(): array
    {
        return [
            'show_ui' => [
                'optional',
                'bool'
            ],
            'public' => [
                'optional',
                'bool'
            ]
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function doExecute(array $parameters): array
    {
        // Build arguments for get_post_types
        $args = [];

        if (isset($parameters['show_ui'])) {
            $args['show_ui'] = $parameters['show_ui'];
        }

        if (isset($parameters['public'])) {
            $args['public'] = $parameters['public'];
        }

        // Get post types
        $postTypes = $this->wp->getPostTypes($args);

        $types = [];
        foreach ($postTypes as $typeName => $typeObject) {
            $counts = $this->wp->countPosts($typeName);

            $types[] = [
                'name' => $typeName,
                'label' => $typeObject->label,
                'labels' => (array) $typeObject->labels,
                'description' => $typeObject->description,
                'public' => $typeObject->public,
                'hierarchical' => $typeObject->hierarchical,
                'show_ui' => $typeObject->show_ui,
                'show_in_menu' => $typeObject->show_in_menu,
                'show_in_nav_menus' => $typeObject->show_in_nav_menus,
                'show_in_admin_bar' => $typeObject->show_in_admin_bar,
                'show_in_rest' => $typeObject->show_in_rest,
                'rest_base' => $typeObject->rest_base,
                'has_archive' => $typeObject->has_archive,
                'can_export' => $typeObject->can_export,
                'menu_icon' => $typeObject->menu_icon ?? null,
                'capability_type' => $typeObject->capability_type,
                'supports' => $typeObject->supports ?? [],
                'taxonomies' => $typeObject->taxonomies ?? [],
                'counts' => [
                    'publish' => $counts->publish ?? 0,
                    'draft' => $counts->draft ?? 0,
                    'pending' => $counts->pending ?? 0,
                    'private' => $counts->private ?? 0,
                    'trash' => $counts->trash ?? 0,
                ],
            ];
        }

        return $this->success([
            'content_types' => $types,
            'count' => count($types),
        ], 'Content types discovered successfully');
    }
}
