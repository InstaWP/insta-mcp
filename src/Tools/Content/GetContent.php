<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tools\Content;

use InstaWP\MCP\PHP\Tools\AbstractTool;
use InstaWP\MCP\PHP\Exceptions\ToolException;

/**
 * Tool to get a single piece of content by ID
 *
 * Retrieves detailed information about a specific content item
 * (post, page, custom post type) by its ID
 */
class GetContent extends AbstractTool
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'get_content';
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Get detailed information about a specific piece of content by ID. '
            . 'Works with any content type (posts, pages, custom post types).';
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
            'content_type' => [
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
        $expectedType = $parameters['content_type'] ?? null;

        // Fetch the post
        $post = $this->wp->getPost($contentId);

        if ($post === null) {
            throw ToolException::wordpressError(
                "Content with ID {$contentId} not found"
            );
        }

        // Verify content type if specified
        if ($expectedType !== null && $post->post_type !== $expectedType) {
            throw ToolException::wordpressError(
                "Content with ID {$contentId} is of type '{$post->post_type}', not '{$expectedType}'"
            );
        }

        // Format detailed response
        return $this->success($this->formatPost($post));
    }

    /**
     * Format a post with all details
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
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'status' => $post->post_status,
            'type' => $post->post_type,
            'date' => $post->post_date,
            'modified' => $post->post_modified,
            'author' => [
                'id' => $post->post_author,
                'name' => $this->wp->getAuthorName((int)$post->post_author),
            ],
            'parent' => $post->post_parent,
            'menu_order' => $post->menu_order,
            'comment_status' => $post->comment_status,
            'ping_status' => $post->ping_status,
            'url' => $this->wp->getPermalink($post->ID),
            'edit_url' => $this->wp->getEditPostLink($post->ID, 'raw'),
        ];
    }
}
