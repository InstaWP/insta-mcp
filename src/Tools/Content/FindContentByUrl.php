<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tools\Content;

use InstaWP\MCP\PHP\Tools\AbstractTool;
use InstaWP\MCP\PHP\Exceptions\ToolException;

/**
 * Tool to find content by URL
 *
 * Finds content by its URL, automatically detecting the content type
 */
class FindContentByUrl extends AbstractTool
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'find_content_by_url';
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Finds content by its URL, automatically detecting the content type, and optionally updates it.';
    }

    /**
     * {@inheritdoc}
     */
    public function getSchema(): array
    {
        return [
            'url' => [
                'required',
                'string',
                ['notEmpty' => true]
            ],
            'update_fields' => [
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
        $url = $parameters['url'];
        $updateFields = $parameters['update_fields'] ?? null;

        // Parse URL to extract slug
        $slug = $this->extractSlugFromUrl($url);

        if (empty($slug)) {
            throw ToolException::validationFailed(
                'Could not extract slug from URL',
                ['url' => 'Invalid URL format']
            );
        }

        // Get priority content types based on URL patterns
        $priorityTypes = $this->guessContentTypesFromUrl($url);

        // Search for content
        $foundContent = null;
        $foundType = null;

        foreach ($priorityTypes as $contentType) {
            if (!$this->wp->postTypeExists($contentType)) {
                continue;
            }

            $args = [
                'name' => $slug,
                'post_type' => $contentType,
                'post_status' => 'any',
                'posts_per_page' => 1,
            ];

            $posts = $this->wp->getPosts($args);

            if (!empty($posts)) {
                $foundContent = $posts[0];
                $foundType = $contentType;
                break;
            }
        }

        if ($foundContent === null) {
            throw ToolException::wordpressError(
                "No content found with URL: {$url}"
            );
        }

        // Update content if requested
        if ($updateFields !== null && !empty($updateFields)) {
            $updateData = ['ID' => $foundContent->ID];

            if (isset($updateFields['title'])) {
                $updateData['post_title'] = $updateFields['title'];
            }
            if (isset($updateFields['content'])) {
                $updateData['post_content'] = $updateFields['content'];
            }
            if (isset($updateFields['status'])) {
                $updateData['post_status'] = $updateFields['status'];
            }

            $result = $this->wp->updatePost($updateData);

            if ($this->wp->isError($result)) {
                throw ToolException::wordpressError(
                    "Failed to update content: " . $result->get_error_message()
                );
            }

            // Fetch updated content
            $foundContent = $this->wp->getPost($foundContent->ID);
        }

        return $this->success([
            'found' => true,
            'content_type' => $foundType,
            'content_id' => $foundContent->ID,
            'original_url' => $url,
            'updated' => $updateFields !== null,
            'content' => $this->formatPost($foundContent),
        ], $updateFields ? 'Content found and updated' : 'Content found successfully');
    }

    /**
     * Extract slug from URL
     *
     * @param string $url
     * @return string
     */
    private function extractSlugFromUrl(string $url): string
    {
        try {
            $parsedUrl = parse_url($url);
            $path = $parsedUrl['path'] ?? '';

            // Remove trailing slash and split path
            $pathParts = array_filter(explode('/', trim($path, '/')));

            // The slug is typically the last part of the URL
            return end($pathParts) ?: '';
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Guess content types from URL patterns
     *
     * @param string $url
     * @return array<int, string>
     */
    private function guessContentTypesFromUrl(string $url): array
    {
        $priorityTypes = [];

        // Common URL patterns to content type mappings
        $pathMappings = [
            'documentation' => ['documentation', 'docs', 'doc'],
            'docs' => ['documentation', 'docs', 'doc'],
            'products' => ['product'],
            'product' => ['product'],
            'portfolio' => ['portfolio', 'project'],
            'services' => ['service'],
            'testimonials' => ['testimonial'],
            'team' => ['team_member', 'staff'],
            'events' => ['event'],
            'courses' => ['course', 'lesson'],
        ];

        // Check URL for patterns
        $urlLower = strtolower($url);
        foreach ($pathMappings as $pattern => $types) {
            if (str_contains($urlLower, $pattern)) {
                $priorityTypes = array_merge($priorityTypes, $types);
            }
        }

        // Always check standard content types as fallback
        $priorityTypes[] = 'post';
        $priorityTypes[] = 'page';

        // Remove duplicates
        return array_unique($priorityTypes);
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
