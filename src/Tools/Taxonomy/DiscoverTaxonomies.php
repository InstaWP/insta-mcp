<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tools\Taxonomy;

use InstaWP\MCP\PHP\Tools\AbstractTool;

/**
 * Tool to discover available taxonomies
 *
 * Lists all registered taxonomies with their settings and capabilities
 */
class DiscoverTaxonomies extends AbstractTool
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'discover_taxonomies';
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Discover all available taxonomies in WordPress. '
            . 'Returns information about categories, tags, and custom taxonomies.';
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
        // Build arguments for get_taxonomies
        $args = [];

        if (isset($parameters['show_ui'])) {
            $args['show_ui'] = $parameters['show_ui'];
        }

        if (isset($parameters['public'])) {
            $args['public'] = $parameters['public'];
        }

        // Get taxonomies
        $taxonomies = $this->wp->getTaxonomies($args);

        $result = [];
        foreach ($taxonomies as $taxonomyName => $taxonomyObject) {
            $result[] = [
                'name' => $taxonomyName,
                'label' => $taxonomyObject->label,
                'labels' => (array) $taxonomyObject->labels,
                'description' => $taxonomyObject->description,
                'public' => $taxonomyObject->public,
                'publicly_queryable' => $taxonomyObject->publicly_queryable,
                'hierarchical' => $taxonomyObject->hierarchical,
                'show_ui' => $taxonomyObject->show_ui,
                'show_in_menu' => $taxonomyObject->show_in_menu,
                'show_in_nav_menus' => $taxonomyObject->show_in_nav_menus,
                'show_in_rest' => $taxonomyObject->show_in_rest,
                'rest_base' => $taxonomyObject->rest_base,
                'show_tagcloud' => $taxonomyObject->show_tagcloud,
                'show_in_quick_edit' => $taxonomyObject->show_in_quick_edit,
                'show_admin_column' => $taxonomyObject->show_admin_column,
                'meta_box_cb' => is_callable($taxonomyObject->meta_box_cb)
                    ? 'callable'
                    : $taxonomyObject->meta_box_cb,
                'object_types' => $taxonomyObject->object_type ?? [],
                'capability_type' => $taxonomyObject->cap->manage_terms ?? 'manage_categories',
                'rewrite' => $taxonomyObject->rewrite,
            ];
        }

        return $this->success([
            'taxonomies' => $result,
            'count' => count($result),
        ], 'Taxonomies discovered successfully');
    }
}
