<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tools\Taxonomy;

use InstaWP\MCP\PHP\Tools\AbstractTool;
use InstaWP\MCP\PHP\Exceptions\ToolException;

/**
 * Tool to get all taxonomy terms assigned to content
 *
 * Retrieves all terms from one or more taxonomies assigned to a specific content item
 */
class GetContentTerms extends AbstractTool
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'get_content_terms';
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Gets all taxonomy terms assigned to content of any type.';
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
        $contentId = $parameters['content_id'];
        $taxonomy = $parameters['taxonomy'] ?? null;

        // Verify content exists
        $post = $this->wp->getPost($contentId);
        if ($post === null) {
            throw ToolException::wordpressError(
                "Content with ID {$contentId} not found"
            );
        }

        $result = [
            'content_id' => $contentId,
            'content_type' => $post->post_type,
            'terms' => [],
        ];

        // If specific taxonomy requested
        if ($taxonomy !== null) {
            if (!$this->wp->taxonomyExists($taxonomy)) {
                throw ToolException::wordpressError(
                    "Taxonomy '{$taxonomy}' does not exist"
                );
            }

            $terms = $this->wp->getObjectTerms($contentId, $taxonomy);

            if ($this->wp->isError($terms)) {
                throw ToolException::wordpressError(
                    "Failed to get terms: " . $terms->get_error_message()
                );
            }

            $result['terms'][$taxonomy] = $this->formatTerms($terms);
        } else {
            // Get all taxonomies for this content type
            $taxonomies = get_object_taxonomies($post->post_type, 'objects');

            foreach ($taxonomies as $taxonomySlug => $taxonomyObject) {
                $terms = $this->wp->getObjectTerms($contentId, $taxonomySlug);

                if (!$this->wp->isError($terms) && !empty($terms)) {
                    $result['terms'][$taxonomySlug] = $this->formatTerms($terms);
                }
            }
        }

        return $this->success($result, 'Content terms retrieved successfully');
    }

    /**
     * Format terms for output
     *
     * @param array<int, \WP_Term> $terms
     * @return array<int, array<string, mixed>>
     */
    private function formatTerms(array $terms): array
    {
        return array_map(function ($term) {
            return [
                'id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'description' => $term->description,
                'parent' => $term->parent,
                'count' => $term->count,
            ];
        }, $terms);
    }
}
