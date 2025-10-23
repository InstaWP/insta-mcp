<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tools\Taxonomy;

use InstaWP\MCP\PHP\Tools\AbstractTool;
use InstaWP\MCP\PHP\Exceptions\ToolException;

/**
 * Tool to get a single taxonomy
 *
 * Retrieves detailed information about a specific taxonomy
 */
class GetTaxonomy extends AbstractTool
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'get_taxonomy';
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Get detailed information about a specific taxonomy by name.';
    }

    /**
     * {@inheritdoc}
     */
    public function getSchema(): array
    {
        return [
            'taxonomy' => [
                'required',
                'string',
                ['notEmpty' => true]
            ]
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function doExecute(array $parameters): array
    {
        $taxonomyName = $parameters['taxonomy'];

        // Get taxonomy
        $taxonomy = $this->wp->getTaxonomy($taxonomyName);

        if ($taxonomy === false) {
            throw ToolException::wordpressError(
                "Taxonomy '{$taxonomyName}' not found"
            );
        }

        return $this->success([
            'name' => $taxonomy->name,
            'label' => $taxonomy->label,
            'labels' => (array) $taxonomy->labels,
            'description' => $taxonomy->description,
            'public' => $taxonomy->public,
            'publicly_queryable' => $taxonomy->publicly_queryable,
            'hierarchical' => $taxonomy->hierarchical,
            'show_ui' => $taxonomy->show_ui,
            'show_in_menu' => $taxonomy->show_in_menu,
            'show_in_nav_menus' => $taxonomy->show_in_nav_menus,
            'show_in_rest' => $taxonomy->show_in_rest,
            'rest_base' => $taxonomy->rest_base,
            'rest_controller_class' => $taxonomy->rest_controller_class,
            'show_tagcloud' => $taxonomy->show_tagcloud,
            'show_in_quick_edit' => $taxonomy->show_in_quick_edit,
            'show_admin_column' => $taxonomy->show_admin_column,
            'meta_box_cb' => is_callable($taxonomy->meta_box_cb)
                ? 'callable'
                : $taxonomy->meta_box_cb,
            'object_types' => $taxonomy->object_type ?? [],
            'capabilities' => (array) $taxonomy->cap,
            'rewrite' => $taxonomy->rewrite,
            'query_var' => $taxonomy->query_var,
        ], 'Taxonomy retrieved successfully');
    }
}
