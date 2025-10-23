<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tools\Content;

use InstaWP\MCP\PHP\Tools\AbstractTool;
use InstaWP\MCP\PHP\Exceptions\ToolException;

/**
 * Tool to list content of any type (posts, pages, custom post types)
 *
 * Implements the unified content architecture pattern where a single tool
 * handles all content types through a content_type parameter
 */
class ListContent extends AbstractTool
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'list_content';
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'List content of any type (posts, pages, custom post types). '
            . 'Supports filtering, sorting, and pagination.';
    }

    /**
     * {@inheritdoc}
     */
    public function getSchema(): array
    {
        return [
            'content_type' => [
                'required',
                'string',
                ['notEmpty' => true]
            ],
            'status' => [
                'optional',
                'string',
                ['in' => ['publish', 'draft', 'pending', 'private', 'trash', 'any']]
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
                ['in' => ['date', 'title', 'modified', 'author', 'ID']]
            ],
            'order' => [
                'optional',
                'string',
                ['in' => ['ASC', 'DESC']]
            ],
            'author' => [
                'optional',
                'int'
            ],
            'search' => [
                'optional',
                'string'
            ]
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function doExecute(array $parameters): array
    {
        $contentType = $parameters['content_type'];

        // Verify post type exists
        if (!$this->wp->postTypeExists($contentType)) {
            throw ToolException::wordpressError(
                "Content type '{$contentType}' does not exist"
            );
        }

        // Build query arguments
        $args = [
            'post_type' => $contentType,
            'post_status' => $parameters['status'] ?? 'publish',
            'posts_per_page' => $parameters['per_page'] ?? 10,
            'paged' => $parameters['page'] ?? 1,
            'orderby' => $parameters['orderby'] ?? 'date',
            'order' => $parameters['order'] ?? 'DESC',
        ];

        // Add optional filters
        if (isset($parameters['author'])) {
            $args['author'] = $parameters['author'];
        }

        if (isset($parameters['search']) && !empty($parameters['search'])) {
            $args['s'] = $parameters['search'];
        }

        // Fetch posts
        $posts = $this->wp->getPosts($args);

        // Format results
        $formattedPosts = array_map(
            fn(\WP_Post $post) => $this->formatPost($post),
            $posts
        );

        return $this->success([
            'content_type' => $contentType,
            'count' => count($formattedPosts),
            'page' => $args['paged'],
            'per_page' => $args['posts_per_page'],
            'items' => $formattedPosts,
        ]);
    }

    /**
     * Format a post for output
     *
     * @param \WP_Post $post The post to format
     * @return array<string, mixed>
     */
    private function formatPost(\WP_Post $post): array
    {
        return [
            'id' => $post->ID,
            'title' => $post->post_title,
            'slug' => $post->post_name,
            'status' => $post->post_status,
            'type' => $post->post_type,
            'date' => $post->post_date,
            'modified' => $post->post_modified,
            'author' => [
                'id' => $post->post_author,
                'name' => $this->wp->getAuthorName((int)$post->post_author),
            ],
            'excerpt' => $this->wp->trimWords($post->post_content, 20),
            'url' => $this->wp->getPermalink($post->ID),
            'edit_url' => $this->wp->getEditPostLink($post->ID, 'raw'),
        ];
    }
}
