<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tools\Taxonomy;

use InstaWP\MCP\PHP\Tools\AbstractTool;
use InstaWP\MCP\PHP\Exceptions\ToolException;

/**
 * Tool to update an existing term
 *
 * Updates term properties with partial update support
 */
class UpdateTerm extends AbstractTool
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'update_term';
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Update an existing term. Supports partial updates - only provide fields you want to change.';
    }

    /**
     * {@inheritdoc}
     */
    public function getSchema(): array
    {
        return [
            'term_id' => [
                'required',
                'int',
                ['min' => 1]
            ],
            'taxonomy' => [
                'required',
                'string',
                ['notEmpty' => true]
            ],
            'name' => [
                'optional',
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
        $termId = $parameters['term_id'];
        $taxonomy = $parameters['taxonomy'];

        // Verify taxonomy exists
        if (!$this->wp->taxonomyExists($taxonomy)) {
            throw ToolException::wordpressError(
                "Taxonomy '{$taxonomy}' does not exist"
            );
        }

        // Verify term exists
        $existingTerm = $this->wp->getTerm($termId, $taxonomy);
        if (!$existingTerm || $this->wp->isError($existingTerm)) {
            throw ToolException::wordpressError(
                "Term with ID {$termId} not found in taxonomy '{$taxonomy}'"
            );
        }

        // Build update arguments
        $args = [];

        if (isset($parameters['name'])) {
            $args['name'] = $parameters['name'];
        }

        if (isset($parameters['slug'])) {
            $args['slug'] = $parameters['slug'];
        }

        if (isset($parameters['description'])) {
            $args['description'] = $parameters['description'];
        }

        if (isset($parameters['parent'])) {
            $args['parent'] = $parameters['parent'];
        }

        // Update term
        $result = $this->wp->updateTerm($termId, $taxonomy, $args);

        if ($this->wp->isError($result)) {
            throw ToolException::wordpressError(
                "Failed to update term: " . $result->get_error_message()
            );
        }

        // Get updated term
        $term = $this->wp->getTerm($termId, $taxonomy);

        return $this->success([
            'id' => $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
            'description' => $term->description,
            'taxonomy' => $term->taxonomy,
            'parent' => $term->parent,
            'count' => $term->count,
        ], 'Term updated successfully');
    }
}
