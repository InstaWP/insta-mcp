<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tools\Taxonomy;

use InstaWP\MCP\PHP\Tools\AbstractTool;
use InstaWP\MCP\PHP\Exceptions\ToolException;

/**
 * Tool to create a new term
 *
 * Creates a new term in a specified taxonomy
 */
class CreateTerm extends AbstractTool
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'create_term';
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Create a new term in a taxonomy.';
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
            'name' => [
                'required',
                'string',
                ['notEmpty' => true]
            ],
            'slug' => [
                'optional',
                'string'
            ],
            'description' => [
                'optional',
                'string'
            ],
            'parent' => [
                'optional',
                'int',
                ['min' => 0]
            ]
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function doExecute(array $parameters): array
    {
        $taxonomy = $parameters['taxonomy'];
        $name = $parameters['name'];

        // Verify taxonomy exists
        if (!$this->wp->taxonomyExists($taxonomy)) {
            throw ToolException::wordpressError(
                "Taxonomy '{$taxonomy}' does not exist"
            );
        }

        // Build arguments
        $args = [];

        if (isset($parameters['slug'])) {
            $args['slug'] = $parameters['slug'];
        }

        if (isset($parameters['description'])) {
            $args['description'] = $parameters['description'];
        }

        if (isset($parameters['parent'])) {
            $args['parent'] = $parameters['parent'];
        }

        // Create term
        $result = $this->wp->insertTerm($name, $taxonomy, $args);

        if ($this->wp->isError($result)) {
            throw ToolException::wordpressError(
                "Failed to create term: " . $result->get_error_message()
            );
        }

        // Get the created term
        $term = $this->wp->getTerm($result['term_id'], $taxonomy);

        return $this->success([
            'id' => $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
            'description' => $term->description,
            'taxonomy' => $term->taxonomy,
            'parent' => $term->parent,
            'count' => $term->count,
        ], 'Term created successfully');
    }
}
