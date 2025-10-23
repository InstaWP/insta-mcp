<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Services;

/**
 * Service wrapper for WordPress functions
 *
 * Provides a testable interface for WordPress operations by wrapping
 * global functions. This allows for easy mocking in unit tests.
 */
class WordPressService
{
    /**
     * Get posts with arguments
     *
     * @param array<string, mixed> $args Query arguments
     * @return array<int, \WP_Post>
     */
    public function getPosts(array $args = []): array
    {
        return get_posts($args);
    }

    /**
     * Get a single post by ID
     *
     * @param int $postId The post ID
     * @return \WP_Post|null
     */
    public function getPost(int $postId): ?\WP_Post
    {
        $post = get_post($postId);
        return $post instanceof \WP_Post ? $post : null;
    }

    /**
     * Insert a new post
     *
     * @param array<string, mixed> $postData Post data
     * @return int|\WP_Error Post ID on success, WP_Error on failure
     */
    public function insertPost(array $postData): int|\WP_Error
    {
        return wp_insert_post($postData, true);
    }

    /**
     * Update an existing post
     *
     * @param array<string, mixed> $postData Post data (must include ID)
     * @return int|\WP_Error Post ID on success, WP_Error on failure
     */
    public function updatePost(array $postData): int|\WP_Error
    {
        return wp_update_post($postData, true);
    }

    /**
     * Delete a post
     *
     * @param int $postId The post ID
     * @param bool $forceDelete Whether to permanently delete
     * @return \WP_Post|false|null
     */
    public function deletePost(int $postId, bool $forceDelete = false): \WP_Post|false|null
    {
        return wp_delete_post($postId, $forceDelete);
    }

    /**
     * Get permalink for a post
     *
     * @param int $postId The post ID
     * @return string|false
     */
    public function getPermalink(int $postId): string|false
    {
        return get_permalink($postId);
    }

    /**
     * Get edit post link
     *
     * @param int $postId The post ID
     * @param string $context The context (display|raw)
     * @return string|null
     */
    public function getEditPostLink(int $postId, string $context = 'display'): ?string
    {
        return get_edit_post_link($postId, $context);
    }

    /**
     * Get post types
     *
     * @param array<string, mixed> $args Arguments
     * @return array<string, \WP_Post_Type>
     */
    public function getPostTypes(array $args = []): array
    {
        return get_post_types($args, 'objects');
    }

    /**
     * Check if post type exists
     *
     * @param string $postType The post type slug
     * @return bool
     */
    public function postTypeExists(string $postType): bool
    {
        return post_type_exists($postType);
    }

    /**
     * Get author display name
     *
     * @param int $authorId The author ID
     * @return string
     */
    public function getAuthorName(int $authorId): string
    {
        return get_the_author_meta('display_name', $authorId);
    }

    /**
     * Trim words from content
     *
     * @param string $text The text to trim
     * @param int $numWords Number of words
     * @param string|null $more What to append if trimmed
     * @return string
     */
    public function trimWords(string $text, int $numWords = 55, ?string $more = null): string
    {
        return wp_trim_words($text, $numWords, $more);
    }

    /**
     * Check if value is a WP_Error
     *
     * @param mixed $thing The value to check
     * @return bool
     */
    public function isError(mixed $thing): bool
    {
        return is_wp_error($thing);
    }

    /**
     * Get site info
     *
     * @param string $show The info to retrieve
     * @return string
     */
    public function getBlogInfo(string $show): string
    {
        return get_bloginfo($show);
    }

    /**
     * Count posts by type and status
     *
     * @param string $type Post type
     * @return object Object with counts by status
     */
    public function countPosts(string $type = 'post'): object
    {
        return wp_count_posts($type);
    }

    /**
     * Get taxonomies
     *
     * @param array<string, mixed> $args Arguments
     * @param string $output Output type (names|objects)
     * @return array<string, \WP_Taxonomy>|array<int, string>
     */
    public function getTaxonomies(array $args = [], string $output = 'objects'): array
    {
        return get_taxonomies($args, $output);
    }

    /**
     * Get a single taxonomy
     *
     * @param string $taxonomy The taxonomy name
     * @return \WP_Taxonomy|false
     */
    public function getTaxonomy(string $taxonomy): \WP_Taxonomy|false
    {
        return get_taxonomy($taxonomy);
    }

    /**
     * Check if taxonomy exists
     *
     * @param string $taxonomy The taxonomy name
     * @return bool
     */
    public function taxonomyExists(string $taxonomy): bool
    {
        return taxonomy_exists($taxonomy);
    }

    /**
     * Get terms
     *
     * @param array<string, mixed>|string $args Taxonomy name or array of arguments
     * @return array<int, \WP_Term>|\WP_Error
     */
    public function getTerms(array|string $args): array|\WP_Error
    {
        return get_terms($args);
    }

    /**
     * Get a single term by ID
     *
     * @param int $termId The term ID
     * @param string $taxonomy The taxonomy name
     * @return \WP_Term|null|\WP_Error
     */
    public function getTerm(int $termId, string $taxonomy = ''): \WP_Term|null|\WP_Error
    {
        $term = get_term($termId, $taxonomy);
        return $term instanceof \WP_Term ? $term : $term;
    }

    /**
     * Get term by field
     *
     * @param string $field Field to search by (slug|name|id|term_taxonomy_id)
     * @param string|int $value The value to search for
     * @param string $taxonomy The taxonomy name
     * @return \WP_Term|false
     */
    public function getTermBy(string $field, string|int $value, string $taxonomy): \WP_Term|false
    {
        return get_term_by($field, $value, $taxonomy);
    }

    /**
     * Insert a new term
     *
     * @param string $term The term name
     * @param string $taxonomy The taxonomy name
     * @param array<string, mixed> $args Additional arguments
     * @return array<string, int>|\WP_Error Array with term_id and term_taxonomy_id or WP_Error
     */
    public function insertTerm(string $term, string $taxonomy, array $args = []): array|\WP_Error
    {
        return wp_insert_term($term, $taxonomy, $args);
    }

    /**
     * Update an existing term
     *
     * @param int $termId The term ID
     * @param string $taxonomy The taxonomy name
     * @param array<string, mixed> $args Arguments to update
     * @return array<string, int>|\WP_Error
     */
    public function updateTerm(int $termId, string $taxonomy, array $args = []): array|\WP_Error
    {
        return wp_update_term($termId, $taxonomy, $args);
    }

    /**
     * Delete a term
     *
     * @param int $termId The term ID
     * @param string $taxonomy The taxonomy name
     * @param array<string, mixed> $args Additional arguments
     * @return bool|int|\WP_Error
     */
    public function deleteTerm(int $termId, string $taxonomy, array $args = []): bool|int|\WP_Error
    {
        return wp_delete_term($termId, $taxonomy, $args);
    }

    /**
     * Set terms for a post
     *
     * @param int $postId The post ID
     * @param array<int>|string $terms Term IDs, slugs, or names
     * @param string $taxonomy The taxonomy name
     * @param bool $append Whether to append or replace existing terms
     * @return array<int>|false|\WP_Error
     */
    public function setObjectTerms(int $postId, array|string $terms, string $taxonomy, bool $append = false): array|false|\WP_Error
    {
        return wp_set_object_terms($postId, $terms, $taxonomy, $append);
    }

    /**
     * Get terms for a post
     *
     * @param int $postId The post ID
     * @param string $taxonomy The taxonomy name
     * @return array<int, \WP_Term>|\WP_Error
     */
    public function getObjectTerms(int $postId, string $taxonomy): array|\WP_Error
    {
        return wp_get_object_terms($postId, $taxonomy);
    }
}
