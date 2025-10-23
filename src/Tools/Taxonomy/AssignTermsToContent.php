<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tools\Taxonomy;

use InstaWP\MCP\PHP\Tools\AbstractTool;
use InstaWP\MCP\PHP\Exceptions\ToolException;

/**
 * Tool to assign terms to content
 *
 * Assigns one or more terms to a post or other content type
 */
class AssignTermsToContent extends AbstractTool
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'assign_terms_to_content';
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Assign terms to content. Can replace or append to existing terms.';
    }

    /**
     * {@inheritdoc}
     */
    public function getSchema(): array
    {
        return [
            'content_id' => [
                'required',
                'int',
                ['min' => 1]
            ],
            'taxonomy' => [
                'required',
                'string',
                ['notEmpty' => true]
            ],
            'term_ids' => [
                'required',
                'array'
            ],
            'append' => [
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
        $contentId = $parameters['content_id'];
        $taxonomy = $parameters['taxonomy'];
        $termIds = $parameters['term_ids'];
        $append = $parameters['append'] ?? false;

        // Verify content exists
        $post = $this->wp->getPost($contentId);
        if ($post === null) {
            throw ToolException::wordpressError(
                "Content with ID {$contentId} not found"
            );
        }

        // Verify taxonomy exists
        if (!$this->wp->taxonomyExists($taxonomy)) {
            throw ToolException::wordpressError(
                "Taxonomy '{$taxonomy}' does not exist"
            );
        }

        // Set terms
        $result = $this->wp->setObjectTerms($contentId, $termIds, $taxonomy, $append);

        if ($this->wp->isError($result) || $result === false) {
            $message = $this->wp->isError($result)
                ? $result->get_error_message()
                : "Failed to assign terms";
            throw ToolException::wordpressError(
                "Failed to assign terms: " . $message
            );
        }

        // Get assigned terms
        $assignedTerms = $this->wp->getObjectTerms($contentId, $taxonomy);
        if ($this->wp->isError($assignedTerms)) {
            $assignedTerms = [];
        }

        $formattedTerms = array_map(function ($term) {
            return [
                'id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
            ];
        }, $assignedTerms);

        return $this->success([
            'content_id' => $contentId,
            'taxonomy' => $taxonomy,
            'assigned_term_ids' => $result,
            'terms' => $formattedTerms,
            'operation' => $append ? 'appended' : 'replaced',
        ], 'Terms assigned successfully');
    }
}
