<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tools\Content;

use InstaWP\MCP\PHP\Tools\AbstractTool;
use InstaWP\MCP\PHP\Exceptions\ToolException;

/**
 * Tool to delete content
 *
 * Supports soft delete (move to trash) and permanent deletion
 */
class DeleteContent extends AbstractTool
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'delete_content';
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Delete content by ID. By default moves to trash - '
            . 'set force_delete to true for permanent deletion.';
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
            'force_delete' => [
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
        // Check if safe mode is enabled
        $this->checkSafeMode('Deleting content');

        $contentId = $parameters['content_id'];
        $forceDelete = $parameters['force_delete'] ?? false;

        // Verify content exists and get info before deleting
        $post = $this->wp->getPost($contentId);
        if ($post === null) {
            throw ToolException::wordpressError(
                "Content with ID {$contentId} not found"
            );
        }

        // Store info for response
        $deletedInfo = [
            'id' => $post->ID,
            'title' => $post->post_title,
            'slug' => $post->post_name,
            'type' => $post->post_type,
            'previous_status' => $post->post_status,
        ];

        // Delete the post
        $result = $this->wp->deletePost($contentId, $forceDelete);

        if ($result === false || $result === null) {
            throw ToolException::wordpressError(
                "Failed to delete content with ID {$contentId}"
            );
        }

        $deletedInfo['permanently_deleted'] = $forceDelete;

        if (!$forceDelete) {
            $deletedInfo['current_status'] = 'trash';
        }

        $message = $forceDelete
            ? "Content permanently deleted"
            : "Content moved to trash";

        return $this->success($deletedInfo, $message);
    }
}
