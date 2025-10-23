<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tools\Content;

use InstaWP\MCP\PHP\Tools\AbstractTool;
use InstaWP\MCP\PHP\Exceptions\ToolException;

/**
 * Tool to update existing content
 *
 * Updates posts, pages, or custom post types with partial or full updates
 */
class UpdateContent extends AbstractTool
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'update_content';
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Update existing content by ID. Supports partial updates - '
            . 'only provide the fields you want to change.';
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
            'title' => [
                'optional',
                'string',
                ['notEmpty' => true],
                ['maxLength' => 200]
            ],
            'content' => [
                'optional',
                'string'
            ],
            'status' => [
                'optional',
                'string',
                ['in' => ['publish', 'draft', 'pending', 'private', 'trash']]
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
        $contentId = $parameters['content_id'];

        // Verify content exists
        $existingPost = $this->wp->getPost($contentId);
        if ($existingPost === null) {
            throw ToolException::wordpressError(
                "Content with ID {$contentId} not found"
            );
        }

        // Build update data
        $updateData = ['ID' => $contentId];

        if (isset($parameters['title'])) {
            $updateData['post_title'] = $parameters['title'];
        }

        if (isset($parameters['content'])) {
            $updateData['post_content'] = $parameters['content'];
        }

        if (isset($parameters['status'])) {
            $updateData['post_status'] = $parameters['status'];
        }

        if (isset($parameters['author_id'])) {
            $updateData['post_author'] = $parameters['author_id'];
        }

        if (isset($parameters['excerpt'])) {
            $updateData['post_excerpt'] = $parameters['excerpt'];
        }

        if (isset($parameters['slug'])) {
            $updateData['post_name'] = $parameters['slug'];
        }

        if (isset($parameters['parent_id'])) {
            $updateData['post_parent'] = $parameters['parent_id'];
        }

        if (isset($parameters['menu_order'])) {
            $updateData['menu_order'] = $parameters['menu_order'];
        }

        if (isset($parameters['comment_status'])) {
            $updateData['comment_status'] = $parameters['comment_status'];
        }

        if (isset($parameters['ping_status'])) {
            $updateData['ping_status'] = $parameters['ping_status'];
        }

        // Update the post
        $result = $this->wp->updatePost($updateData);

        if ($this->wp->isError($result)) {
            throw ToolException::wordpressError(
                "Failed to update content: " . $result->get_error_message()
            );
        }

        // Fetch updated post
        $updatedPost = $this->wp->getPost($contentId);

        return $this->success([
            'id' => $contentId,
            'title' => $updatedPost->post_title,
            'slug' => $updatedPost->post_name,
            'type' => $updatedPost->post_type,
            'status' => $updatedPost->post_status,
            'modified' => $updatedPost->post_modified,
            'url' => $this->wp->getPermalink($contentId),
            'edit_url' => $this->wp->getEditPostLink($contentId, 'raw'),
        ], "Content updated successfully");
    }
}
