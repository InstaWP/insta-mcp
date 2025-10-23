<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tools\Taxonomy;

use InstaWP\MCP\PHP\Tools\AbstractTool;
use InstaWP\MCP\PHP\Exceptions\ToolException;

/**
 * Tool to delete a term
 *
 * Permanently deletes a term from a taxonomy
 */
class DeleteTerm extends AbstractTool
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'delete_term';
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Delete a term from a taxonomy permanently.';
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
            ]
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function doExecute(array $parameters): array
    {
        // Check if safe mode is enabled
        $this->checkSafeMode('Deleting terms');

        $termId = $parameters['term_id'];
        $taxonomy = $parameters['taxonomy'];

        // Verify taxonomy exists
        if (!$this->wp->taxonomyExists($taxonomy)) {
            throw ToolException::wordpressError(
                "Taxonomy '{$taxonomy}' does not exist"
            );
        }

        // Get term info before deleting
        $term = $this->wp->getTerm($termId, $taxonomy);
        if (!$term || $this->wp->isError($term)) {
            throw ToolException::wordpressError(
                "Term with ID {$termId} not found in taxonomy '{$taxonomy}'"
            );
        }

        $termInfo = [
            'id' => $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
            'taxonomy' => $term->taxonomy,
        ];

        // Delete term
        $result = $this->wp->deleteTerm($termId, $taxonomy);

        if ($this->wp->isError($result) || $result === false) {
            $message = $this->wp->isError($result)
                ? $result->get_error_message()
                : "Failed to delete term";
            throw ToolException::wordpressError(
                "Failed to delete term: " . $message
            );
        }

        return $this->success($termInfo, 'Term deleted successfully');
    }
}
