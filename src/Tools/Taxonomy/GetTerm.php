<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tools\Taxonomy;

use InstaWP\MCP\PHP\Tools\AbstractTool;
use InstaWP\MCP\PHP\Exceptions\ToolException;

/**
 * Tool to get a single term
 *
 * Retrieves detailed information about a specific term
 */
class GetTerm extends AbstractTool
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'get_term';
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Get detailed information about a specific term by ID or slug.';
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
            'term_id' => [
                'optional',
                'int',
                ['min' => 1]
            ],
            'slug' => [
                'optional',
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
        $taxonomy = $parameters['taxonomy'];

        // Verify taxonomy exists
        if (!$this->wp->taxonomyExists($taxonomy)) {
            throw ToolException::wordpressError(
                "Taxonomy '{$taxonomy}' does not exist"
            );
        }

        // Get term by ID or slug
        $term = null;
        if (isset($parameters['term_id'])) {
            $term = $this->wp->getTerm($parameters['term_id'], $taxonomy);
        } elseif (isset($parameters['slug'])) {
            $term = $this->wp->getTermBy('slug', $parameters['slug'], $taxonomy);
        } else {
            throw ToolException::validationFailed(
                'Either term_id or slug must be provided',
                ['term' => 'Either term_id or slug is required']
            );
        }

        if (!$term || $this->wp->isError($term)) {
            $identifier = $parameters['term_id'] ?? $parameters['slug'];
            throw ToolException::wordpressError(
                "Term '{$identifier}' not found in taxonomy '{$taxonomy}'"
            );
        }

        return $this->success([
            'id' => $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
            'description' => $term->description,
            'taxonomy' => $term->taxonomy,
            'parent' => $term->parent,
            'count' => $term->count,
        ], 'Term retrieved successfully');
    }
}
