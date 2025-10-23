<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tools\Content;

use InstaWP\MCP\PHP\Tools\AbstractTool;
use InstaWP\MCP\PHP\Exceptions\ToolException;

/**
 * Tool to create new content of any type
 *
 * Creates posts, pages, or custom post types with flexible configuration
 */
class CreateContent extends AbstractTool
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'create_content';
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Create new content of any type (post, page, custom post type). '
            . 'Supports setting title, content, status, author, and more.';
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
            'title' => [
                'required',
                'string',
                ['notEmpty' => true],
                ['maxLength' => 200]
            ],
            'content' => [
                'required',
                'string'
            ],
            'status' => [
                'optional',
                'string',
                ['in' => ['publish', 'draft', 'pending', 'private', 'future']]
            ],
            'author_id' => [
                'optional',
                'int',
                ['min' => 1]
            ],
            'excerpt' => [
                'optional',
                'string'
            ],
            'slug' => [
                'optional',
                'string'
            ],
            'parent_id' => [
                'optional',
                'int',
                ['min' => 0]
            ],
            'menu_order' => [
                'optional',
                'int'
            ],
            'comment_status' => [
                'optional',
                'string',
                ['in' => ['open', 'closed']]
            ],
            'ping_status' => [
                'optional',
                'string',
                ['in' => ['open', 'closed']]
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

        // Build post data
        $postData = [
            'post_type' => $contentType,
            'post_title' => $parameters['title'],
            'post_content' => $parameters['content'],
            'post_status' => $parameters['status'] ?? 'draft',
        ];

        // Add optional fields
        if (isset($parameters['author_id'])) {
            $postData['post_author'] = $parameters['author_id'];
        }

        if (isset($parameters['excerpt'])) {
            $postData['post_excerpt'] = $parameters['excerpt'];
        }

        if (isset($parameters['slug'])) {
            $postData['post_name'] = $parameters['slug'];
        }

        if (isset($parameters['parent_id'])) {
            $postData['post_parent'] = $parameters['parent_id'];
        }

        if (isset($parameters['menu_order'])) {
            $postData['menu_order'] = $parameters['menu_order'];
        }

        if (isset($parameters['comment_status'])) {
            $postData['comment_status'] = $parameters['comment_status'];
        }

        if (isset($parameters['ping_status'])) {
            $postData['ping_status'] = $parameters['ping_status'];
        }

        // Insert the post
        $postId = $this->wp->insertPost($postData);

        if ($this->wp->isError($postId)) {
            throw ToolException::wordpressError(
                "Failed to create content: " . $postId->get_error_message()
            );
        }

        // Fetch the created post
        $post = $this->wp->getPost($postId);

        return $this->success([
            'id' => $postId,
            'title' => $post->post_title,
            'slug' => $post->post_name,
            'type' => $post->post_type,
            'status' => $post->post_status,
            'url' => $this->wp->getPermalink($postId),
            'edit_url' => $this->wp->getEditPostLink($postId, 'raw'),
        ], "Content created successfully");
    }
}
