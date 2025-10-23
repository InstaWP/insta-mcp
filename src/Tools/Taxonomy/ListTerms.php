<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tools\Taxonomy;

use InstaWP\MCP\PHP\Tools\AbstractTool;
use InstaWP\MCP\PHP\Exceptions\ToolException;

/**
 * Tool to list terms in a taxonomy
 *
 * Lists terms with filtering, pagination, and search capabilities
 */
class ListTerms extends AbstractTool
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'list_terms';
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'List terms in a taxonomy with filtering and pagination options.';
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
            ],
            'hide_empty' => [
                'optional',
                'bool'
            ],
            'parent' => [
                'optional',
                'int',
                ['min' => 0]
            ],
            'search' => [
                'optional',
                'string'
            ],
            'per_page' => [
                'optional',
                'int',
                ['min' => 1],
                ['max' => 100]
            ],
            'page' => [
                'optional',
                'int',
                ['min' => 1]
            ],
            'orderby' => [
                'optional',
                'string',
                ['in' => ['name', 'slug', 'term_id', 'count', 'term_order']]
            ],
            'order' => [
                'optional',
                'string',
                ['in' => ['asc', 'desc']]
            ]
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function doExecute(array $parameters): array
    {
        $taxonomy = $parameters['taxonomy'];

        // Verify taxonomy exists
        if (!$this->wp->taxonomyExists($taxonomy)) {
            throw ToolException::wordpressError(
                "Taxonomy '{$taxonomy}' does not exist"
            );
        }

        // Build query arguments
        $args = [
            'taxonomy' => $taxonomy,
            'hide_empty' => $parameters['hide_empty'] ?? false,
        ];

        if (isset($parameters['parent'])) {
            $args['parent'] = $parameters['parent'];
        }

        if (isset($parameters['search'])) {
            $args['search'] = $parameters['search'];
        }

        if (isset($parameters['per_page'])) {
            $args['number'] = $parameters['per_page'];
        }

        if (isset($parameters['page']) && isset($args['number'])) {
            $args['offset'] = ($parameters['page'] - 1) * $args['number'];
        }

        if (isset($parameters['orderby'])) {
            $args['orderby'] = $parameters['orderby'];
        }

        if (isset($parameters['order'])) {
            $args['order'] = strtoupper($parameters['order']);
        }

        // Get terms
        $terms = $this->wp->getTerms($args);

        if ($this->wp->isError($terms)) {
            throw ToolException::wordpressError(
                "Failed to retrieve terms: " . $terms->get_error_message()
            );
        }

        // Format terms
        $formattedTerms = array_map(function ($term) {
            return $this->formatTerm($term);
        }, $terms);

        return $this->success([
            'taxonomy' => $taxonomy,
            'terms' => $formattedTerms,
            'count' => count($formattedTerms),
            'page' => $parameters['page'] ?? 1,
            'per_page' => $parameters['per_page'] ?? count($formattedTerms),
        ], 'Terms retrieved successfully');
    }

    /**
     * Format a term for output
     *
     * @param \WP_Term $term The term to format
     * @return array<string, mixed>
     */
    private function formatTerm(\WP_Term $term): array
    {
        return [
            'id' => $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
            'description' => $term->description,
            'taxonomy' => $term->taxonomy,
            'parent' => $term->parent,
            'count' => $term->count,
        ];
    }
}
