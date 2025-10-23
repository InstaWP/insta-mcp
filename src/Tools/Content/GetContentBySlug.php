<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tools\Content;

use InstaWP\MCP\PHP\Tools\AbstractTool;
use InstaWP\MCP\PHP\Exceptions\ToolException;

/**
 * Tool to search for content by slug
 *
 * Searches for content by slug across one or more content types
 */
class GetContentBySlug extends AbstractTool
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'get_content_by_slug';
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Searches for content by slug across one or more content types.';
    }

    /**
     * {@inheritdoc}
     */
    public function getSchema(): array
    {
        return [
            'slug' => [
                'required',
                'string',
                ['notEmpty' => true]
            ],
            'content_types' => [
                'optional',
                'array'
            ]
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function doExecute(array $parameters): array
    {
        $slug = $parameters['slug'];
        $contentTypes = $parameters['content_types'] ?? ['post', 'page'];

        // Ensure content_types is an array
        if (!is_array($contentTypes)) {
            $contentTypes = [$contentTypes];
        }

        // Search across specified content types
        foreach ($contentTypes as $contentType) {
            // Verify content type exists
            if (!$this->wp->postTypeExists($contentType)) {
                continue; // Skip invalid content types
            }

            // Search for content with this slug
            $args = [
                'name' => $slug,
                'post_type' => $contentType,
                'post_status' => 'any',
                'posts_per_page' => 1,
            ];

            $posts = $this->wp->getPosts($args);

            if (!empty($posts)) {
                $post = $posts[0];

                return $this->success([
                    'found' => true,
                    'content_type' => $contentType,
                    'content' => $this->formatPost($post),
                ], 'Content found successfully');
            }
        }

        // If we get here, no content was found
        $searchedTypes = implode(', ', $contentTypes);
        throw ToolException::wordpressError(
            "No content found with slug '{$slug}' in content types: {$searchedTypes}"
        );
    }

    /**
     * Format a post for output
     *
     * @param \WP_Post $post
     * @return array<string, mixed>
     */
    private function formatPost(\WP_Post $post): array
    {
        return [
            'id' => $post->ID,
            'title' => $post->post_title,
            'slug' => $post->post_name,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'status' => $post->post_status,
            'type' => $post->post_type,
            'date' => $post->post_date,
            'modified' => $post->post_modified,
            'author' => $this->wp->getAuthorName((int) $post->post_author),
            'parent' => $post->post_parent,
            'url' => $this->wp->getPermalink($post->ID),
            'edit_url' => $this->wp->getEditPostLink($post->ID, 'raw'),
        ];
    }
}
