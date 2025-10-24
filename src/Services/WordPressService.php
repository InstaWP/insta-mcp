<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Services;

use InstaWP\MCP\PHP\Services\SilentUpgraderSkin;

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

    // ========================================
    // Plugin Management Methods
    // ========================================

    /**
     * Initialize WordPress filesystem
     * Required for plugin/theme install/upgrade operations
     */
    private function initializeFilesystem(): void
    {
        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        // Force direct filesystem method to avoid credential requests
        $credentials = [];
        if (defined('FTP_HOST')) {
            $credentials['hostname'] = FTP_HOST;
            $credentials['username'] = FTP_USER;
            $credentials['password'] = FTP_PASS;
        }

        // Initialize with direct method - bypasses FTP/SSH credential requirements
        \WP_Filesystem($credentials, WP_CONTENT_DIR, true);
    }

    /**
     * Get all installed plugins
     *
     * @return array<string, array<string, mixed>> Plugin data keyed by plugin file
     */
    public function getPlugins(): array
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return get_plugins();
    }

    /**
     * Get plugin data (headers) from plugin file
     *
     * @param string $pluginFile Plugin file path relative to plugins directory
     * @return array<string, mixed>
     */
    public function getPluginData(string $pluginFile): array
    {
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $pluginPath = WP_PLUGIN_DIR . '/' . $pluginFile;
        return get_plugin_data($pluginPath, false, false);
    }

    /**
     * Check if a plugin is active
     *
     * @param string $pluginFile Plugin file path relative to plugins directory
     * @return bool
     */
    public function isPluginActive(string $pluginFile): bool
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active($pluginFile);
    }

    /**
     * Check if a plugin is inactive
     *
     * @param string $pluginFile Plugin file path relative to plugins directory
     * @return bool
     */
    public function isPluginInactive(string $pluginFile): bool
    {
        return !$this->isPluginActive($pluginFile);
    }

    /**
     * Check if a plugin has an available update
     *
     * @param string $pluginFile Plugin file path relative to plugins directory
     * @return bool
     */
    public function hasPluginUpdate(string $pluginFile): bool
    {
        $updates = get_site_transient('update_plugins');

        if (!$updates || !isset($updates->response)) {
            return false;
        }

        return isset($updates->response[$pluginFile]);
    }

    /**
     * Get the latest available version for a plugin
     *
     * @param string $pluginFile Plugin file path relative to plugins directory
     * @return string|null
     */
    public function getPluginLatestVersion(string $pluginFile): ?string
    {
        $updates = get_site_transient('update_plugins');

        if (!$updates || !isset($updates->response[$pluginFile])) {
            // No update available, return current version
            $pluginData = $this->getPluginData($pluginFile);
            return $pluginData['Version'] ?? null;
        }

        return $updates->response[$pluginFile]->new_version ?? null;
    }

    /**
     * Find plugin file from slug or partial path
     *
     * @param string $plugin Plugin slug, directory name, or file path
     * @return string|null Plugin file path if found
     */
    public function findPluginFile(string $plugin): ?string
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $allPlugins = get_plugins();

        // Check if it's already a valid plugin file
        if (isset($allPlugins[$plugin])) {
            return $plugin;
        }

        // Try to find by slug or directory
        foreach (array_keys($allPlugins) as $pluginFile) {
            // Check if plugin file starts with the slug
            if (strpos($pluginFile, $plugin . '/') === 0) {
                return $pluginFile;
            }

            // Check if it's a single-file plugin matching the slug
            if ($pluginFile === $plugin . '.php') {
                return $pluginFile;
            }
        }

        return null;
    }

    /**
     * Search WordPress.org plugins API
     *
     * @param array<string, mixed> $args Search arguments
     * @return \stdClass|\WP_Error
     */
    public function searchPluginsApi(array $args): \stdClass|\WP_Error
    {
        if (!function_exists('plugins_api')) {
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        }

        $defaults = [
            'per_page' => 24,
            'page' => 1,
            'fields' => [
                'short_description' => true,
                'icons' => true,
                'reviews' => false,
                'banners' => false,
                'downloaded' => true,
                'active_installs' => true,
                'rating' => true,
                'num_ratings' => true,
            ],
        ];

        $args = array_merge($defaults, $args);

        return plugins_api('query_plugins', $args);
    }

    /**
     * Get plugin info from WordPress.org
     *
     * @param string $slug Plugin slug
     * @return \stdClass|\WP_Error
     */
    public function pluginsApiInfo(string $slug): \stdClass|\WP_Error
    {
        if (!function_exists('plugins_api')) {
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        }

        return plugins_api('plugin_information', [
            'slug' => $slug,
            'fields' => [
                'sections' => true,
                'versions' => true,
                'requires' => true,
                'tested' => true,
            ],
        ]);
    }

    /**
     * Activate a plugin
     *
     * @param string $pluginFile Plugin file path relative to plugins directory
     * @param bool $networkWide Whether to activate network-wide (multisite)
     * @return bool|\WP_Error True on success, WP_Error on failure
     */
    public function activatePlugin(string $pluginFile, bool $networkWide = false): bool|\WP_Error
    {
        if (!function_exists('activate_plugin')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $result = activate_plugin($pluginFile, '', $networkWide);

        // activate_plugin returns null on success, WP_Error on failure
        return $result === null ? true : $result;
    }

    /**
     * Deactivate a plugin
     *
     * @param string $pluginFile Plugin file path relative to plugins directory
     * @return void
     */
    public function deactivatePlugin(string $pluginFile): void
    {
        if (!function_exists('deactivate_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        deactivate_plugins($pluginFile);
    }

    /**
     * Validate a plugin file
     *
     * @param string $pluginFile Plugin file path relative to plugins directory
     * @return bool|\WP_Error
     */
    public function validatePlugin(string $pluginFile): bool|\WP_Error
    {
        if (!function_exists('validate_plugin')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $result = validate_plugin($pluginFile);

        // validate_plugin returns 0 on success, WP_Error on failure
        return $result === 0 ? true : $result;
    }

    /**
     * Delete a plugin
     *
     * @param string $pluginFile Plugin file path relative to plugins directory
     * @return bool|\WP_Error
     */
    public function deletePlugin(string $pluginFile): bool|\WP_Error
    {
        if (!function_exists('delete_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $result = delete_plugins([$pluginFile]);

        // delete_plugins returns true on success, WP_Error or false on failure
        return $result;
    }

    /**
     * Install plugin from WordPress.org by slug
     *
     * @param string $slug Plugin slug
     * @return bool|\WP_Error
     */
    public function installPluginFromSlug(string $slug): bool|\WP_Error
    {
        if (!function_exists('plugins_api')) {
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        }

        if (!class_exists('Plugin_Upgrader')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        }

        // Initialize WordPress filesystem
        $this->initializeFilesystem();

        // Get plugin info
        $api = plugins_api('plugin_information', ['slug' => $slug]);

        if (is_wp_error($api)) {
            return $api;
        }

        // Use quiet skin to avoid output
        $skin = new SilentUpgraderSkin();

        // Install plugin
        $upgrader = new \Plugin_Upgrader($skin);
        $result = $upgrader->install($api->download_link);

        return $result;
    }

    /**
     * Install plugin from external URL
     *
     * @param string $url URL to plugin ZIP file
     * @return bool|\WP_Error
     */
    public function installPluginFromUrl(string $url): bool|\WP_Error
    {
        if (!class_exists('Plugin_Upgrader')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        }

        if (empty($url)) {
            return new \WP_Error('invalid_url', 'URL is required for URL installation');
        }

        $skin = new SilentUpgraderSkin();

        $upgrader = new \Plugin_Upgrader($skin);
        $result = $upgrader->install($url);

        return $result;
    }

    /**
     * Install plugin from base64 encoded ZIP
     *
     * @param string $base64Content Base64 encoded ZIP file content
     * @return bool|\WP_Error
     */
    public function installPluginFromZip(string $base64Content): bool|\WP_Error
    {
        if (!class_exists('Plugin_Upgrader')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        }

        if (empty($base64Content)) {
            return new \WP_Error('invalid_zip', 'ZIP content is required');
        }

        // Decode base64
        $zipContent = base64_decode($base64Content);
        if ($zipContent === false) {
            return new \WP_Error('invalid_base64', 'Invalid base64 content');
        }

        // Save to temp file
        $tempFile = wp_tempnam('plugin-upload.zip');
        file_put_contents($tempFile, $zipContent);

        $skin = new SilentUpgraderSkin();

        // Install from temp file
        $upgrader = new \Plugin_Upgrader($skin);
        $result = $upgrader->install($tempFile);

        // Cleanup
        @unlink($tempFile);

        return $result;
    }

    /**
     * Upgrade/update a plugin to latest version
     *
     * @param string $pluginFile Plugin file path relative to plugins directory
     * @return bool|\WP_Error
     */
    public function upgradePlugin(string $pluginFile): bool|\WP_Error
    {
        if (!class_exists('Plugin_Upgrader')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        }

        $skin = new SilentUpgraderSkin();

        $upgrader = new \Plugin_Upgrader($skin);
        $result = $upgrader->upgrade($pluginFile);

        return $result;
    }

    // ========================================
    // Theme Management Methods
    // ========================================

    /**
     * Get all installed themes
     *
     * @return array<string, \WP_Theme> Theme objects keyed by stylesheet
     */
    public function getThemes(): array
    {
        return wp_get_themes();
    }

    /**
     * Get a specific theme by stylesheet
     *
     * @param string $stylesheet Theme slug/stylesheet
     * @return \WP_Theme|null
     */
    public function getTheme(string $stylesheet): ?\WP_Theme
    {
        $theme = wp_get_theme($stylesheet);

        return $theme->exists() ? $theme : null;
    }

    /**
     * Get the currently active theme
     *
     * @return \WP_Theme
     */
    public function getCurrentTheme(): \WP_Theme
    {
        return wp_get_theme();
    }

    /**
     * Check if a theme has an available update
     *
     * @param string $stylesheet Theme stylesheet
     * @return bool
     */
    public function hasThemeUpdate(string $stylesheet): bool
    {
        $updates = get_site_transient('update_themes');

        if (!$updates || !isset($updates->response)) {
            return false;
        }

        return isset($updates->response[$stylesheet]);
    }

    /**
     * Get the latest available version for a theme
     *
     * @param string $stylesheet Theme stylesheet
     * @return string|null
     */
    public function getThemeLatestVersion(string $stylesheet): ?string
    {
        $updates = get_site_transient('update_themes');

        if (!$updates || !isset($updates->response[$stylesheet])) {
            // No update available, return current version
            $theme = $this->getTheme($stylesheet);
            return $theme ? $theme->get('Version') : null;
        }

        return $updates->response[$stylesheet]['new_version'] ?? null;
    }

    /**
     * Switch to a different theme
     *
     * @param string $stylesheet Theme stylesheet
     * @return void
     */
    public function switchTheme(string $stylesheet): void
    {
        switch_theme($stylesheet);
    }

    /**
     * Delete a theme
     *
     * @param string $stylesheet Theme stylesheet
     * @return bool|\WP_Error
     */
    public function deleteTheme(string $stylesheet): bool|\WP_Error
    {
        if (!function_exists('delete_theme')) {
            require_once ABSPATH . 'wp-admin/includes/theme.php';
        }

        return delete_theme($stylesheet);
    }

    /**
     * Search WordPress.org themes API
     *
     * @param array<string, mixed> $args Search arguments
     * @return \stdClass|\WP_Error
     */
    public function searchThemesApi(array $args): \stdClass|\WP_Error
    {
        if (!function_exists('themes_api')) {
            require_once ABSPATH . 'wp-admin/includes/theme.php';
        }

        $defaults = [
            'per_page' => 24,
            'page' => 1,
            'fields' => [
                'description' => true,
                'sections' => false,
                'rating' => true,
                'ratings' => false,
                'downloaded' => true,
                'screenshot_url' => true,
                'preview_url' => true,
                'last_updated' => true,
                'homepage' => true,
                'tags' => true,
            ],
        ];

        $args = array_merge($defaults, $args);

        return themes_api('query_themes', $args);
    }

    /**
     * Get theme info from WordPress.org
     *
     * @param string $slug Theme slug
     * @return \stdClass|\WP_Error
     */
    public function themesApiInfo(string $slug): \stdClass|\WP_Error
    {
        if (!function_exists('themes_api')) {
            require_once ABSPATH . 'wp-admin/includes/theme.php';
        }

        return themes_api('theme_information', [
            'slug' => $slug,
            'fields' => [
                'sections' => true,
                'versions' => true,
                'requires' => true,
                'tested' => true,
            ],
        ]);
    }

    /**
     * Install theme from WordPress.org by slug
     *
     * @param string $slug Theme slug
     * @return bool|\WP_Error
     */
    public function installThemeFromSlug(string $slug): bool|\WP_Error
    {
        if (!function_exists('themes_api')) {
            require_once ABSPATH . 'wp-admin/includes/theme.php';
        }

        if (!class_exists('Theme_Upgrader')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        }

        // Get theme info
        $api = themes_api('theme_information', ['slug' => $slug]);

        if (is_wp_error($api)) {
            return $api;
        }

        $skin = new SilentUpgraderSkin();

        // Install theme
        $upgrader = new \Theme_Upgrader($skin);
        $result = $upgrader->install($api->download_link);

        return $result;
    }

    /**
     * Install theme from external URL
     *
     * @param string $url URL to theme ZIP file
     * @return bool|\WP_Error
     */
    public function installThemeFromUrl(string $url): bool|\WP_Error
    {
        if (!class_exists('Theme_Upgrader')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        }

        if (empty($url)) {
            return new \WP_Error('invalid_url', 'URL is required for URL installation');
        }

        $skin = new SilentUpgraderSkin();

        $upgrader = new \Theme_Upgrader($skin);
        $result = $upgrader->install($url);

        return $result;
    }

    /**
     * Install theme from base64 encoded ZIP
     *
     * @param string $base64Content Base64 encoded ZIP file content
     * @return bool|\WP_Error
     */
    public function installThemeFromZip(string $base64Content): bool|\WP_Error
    {
        if (!class_exists('Theme_Upgrader')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        }

        if (empty($base64Content)) {
            return new \WP_Error('invalid_zip', 'ZIP content is required');
        }

        // Decode base64
        $zipContent = base64_decode($base64Content);
        if ($zipContent === false) {
            return new \WP_Error('invalid_base64', 'Invalid base64 content');
        }

        // Save to temp file
        $tempFile = wp_tempnam('theme-upload.zip');
        file_put_contents($tempFile, $zipContent);

        $skin = new SilentUpgraderSkin();

        // Install from temp file
        $upgrader = new \Theme_Upgrader($skin);
        $result = $upgrader->install($tempFile);

        // Cleanup
        @unlink($tempFile);

        return $result;
    }

    /**
     * Upgrade/update a theme to latest version
     *
     * @param string $stylesheet Theme stylesheet
     * @return bool|\WP_Error
     */
    public function upgradeTheme(string $stylesheet): bool|\WP_Error
    {
        if (!class_exists('Theme_Upgrader')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        }

        $skin = new SilentUpgraderSkin();

        $upgrader = new \Theme_Upgrader($skin);
        $result = $upgrader->upgrade($stylesheet);

        return $result;
    }

    // ==================== File Operations ====================

    /**
     * Validate plugin file path with security checks
     *
     * @param string $pluginFile Plugin file (e.g., 'plugin-dir/plugin.php')
     * @param string $relativePath Relative path within plugin (e.g., 'includes/config.php')
     * @return array{valid: bool, message: string, full_path?: string}
     */
    public function validatePluginFilePath(string $pluginFile, string $relativePath): array
    {
        // Check if file editing is allowed
        if (defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT) {
            return [
                'valid' => false,
                'message' => 'File editing is disabled via DISALLOW_FILE_EDIT constant'
            ];
        }

        // Check if file modification is allowed
        if (defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS) {
            return [
                'valid' => false,
                'message' => 'File modifications are disabled via DISALLOW_FILE_MODS constant'
            ];
        }

        // Get plugin directory
        $pluginDir = WP_PLUGIN_DIR . '/' . dirname($pluginFile);

        // Note: We don't check if directory exists here because we may be creating a new plugin
        // The directory will be created by wp_mkdir_p() in writePluginFile() if needed

        // Build full path
        $fullPath = $pluginDir . '/' . ltrim($relativePath, '/');

        // For path traversal check, use realpath if file exists, otherwise validate the constructed path
        if (file_exists($fullPath)) {
            $realPath = realpath($fullPath);
            if ($realPath === false || strpos($realPath, realpath($pluginDir)) !== 0) {
                return [
                    'valid' => false,
                    'message' => 'Invalid file path - path traversal detected'
                ];
            }
        } else {
            // For new files, validate the path without requiring directory to exist

            // Check if relative path contains path traversal attempts
            if (strpos($relativePath, '..') !== false) {
                return [
                    'valid' => false,
                    'message' => 'Invalid file path - path traversal detected'
                ];
            }

            // Validate that the constructed path is within WP_PLUGIN_DIR
            $normalizedPath = str_replace('\\', '/', $fullPath);
            $normalizedBaseDir = str_replace('\\', '/', WP_PLUGIN_DIR);

            if (strpos($normalizedPath, $normalizedBaseDir) !== 0) {
                return [
                    'valid' => false,
                    'message' => 'Invalid file path - path outside plugin directory'
                ];
            }

            // Additional check: ensure we're not trying to write to WP_PLUGIN_DIR root
            $pluginDirName = dirname($pluginFile);
            if (empty($pluginDirName) || $pluginDirName === '.') {
                return [
                    'valid' => false,
                    'message' => 'Cannot write files directly to plugins root directory'
                ];
            }

            $realPath = $fullPath; // Use constructed path for new files
        }

        // Check file extension whitelist for PHP files
        $extension = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
        $allowedExtensions = ['php', 'js', 'css', 'json', 'txt', 'md', 'xml', 'yml', 'yaml', 'ini'];

        if (!in_array($extension, $allowedExtensions, true)) {
            return [
                'valid' => false,
                'message' => "File extension '.{$extension}' is not allowed"
            ];
        }

        return [
            'valid' => true,
            'message' => 'Path is valid',
            'full_path' => $realPath
        ];
    }

    /**
     * Read plugin file contents
     *
     * @param string $pluginFile Plugin file (e.g., 'plugin-dir/plugin.php')
     * @param string $relativePath Relative path within plugin
     * @return array{success: bool, content?: string, error?: string}
     */
    public function readPluginFile(string $pluginFile, string $relativePath): array
    {
        $validation = $this->validatePluginFilePath($pluginFile, $relativePath);

        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => $validation['message']
            ];
        }

        $fullPath = $validation['full_path'];

        if (!file_exists($fullPath)) {
            return [
                'success' => false,
                'error' => 'File does not exist'
            ];
        }

        if (!is_readable($fullPath)) {
            return [
                'success' => false,
                'error' => 'File is not readable'
            ];
        }

        $content = file_get_contents($fullPath);

        if ($content === false) {
            return [
                'success' => false,
                'error' => 'Failed to read file'
            ];
        }

        return [
            'success' => true,
            'content' => $content
        ];
    }

    /**
     * Write plugin file contents with backup
     *
     * @param string $pluginFile Plugin file (e.g., 'plugin-dir/plugin.php')
     * @param string $relativePath Relative path within plugin
     * @param string $content New file content
     * @param bool $createBackup Whether to create backup before writing
     * @return array{success: bool, backup_path?: string, error?: string}
     */
    public function writePluginFile(
        string $pluginFile,
        string $relativePath,
        string $content,
        bool $createBackup = true
    ): array {
        $validation = $this->validatePluginFilePath($pluginFile, $relativePath);

        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => $validation['message']
            ];
        }

        $fullPath = $validation['full_path'];

        // Create backup if requested and file exists
        $backupPath = null;
        if ($createBackup && file_exists($fullPath)) {
            $backupPath = $fullPath . '.backup.' . time();
            if (!copy($fullPath, $backupPath)) {
                return [
                    'success' => false,
                    'error' => 'Failed to create backup file'
                ];
            }
        }

        // Ensure directory exists
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            if (!wp_mkdir_p($dir)) {
                return [
                    'success' => false,
                    'error' => 'Failed to create directory'
                ];
            }
        }

        // Write file
        $result = file_put_contents($fullPath, $content);

        if ($result === false) {
            // Restore backup if write failed
            if ($backupPath && file_exists($backupPath)) {
                copy($backupPath, $fullPath);
                unlink($backupPath);
            }

            return [
                'success' => false,
                'error' => 'Failed to write file'
            ];
        }

        $response = ['success' => true];
        if ($backupPath) {
            $response['backup_path'] = $backupPath;
        }

        return $response;
    }

    /**
     * Validate theme file path with security checks
     *
     * @param string $stylesheet Theme stylesheet
     * @param string $relativePath Relative path within theme
     * @return array{valid: bool, message: string, full_path?: string}
     */
    public function validateThemeFilePath(string $stylesheet, string $relativePath): array
    {
        // Check if file editing is allowed
        if (defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT) {
            return [
                'valid' => false,
                'message' => 'File editing is disabled via DISALLOW_FILE_EDIT constant'
            ];
        }

        // Check if file modification is allowed
        if (defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS) {
            return [
                'valid' => false,
                'message' => 'File modifications are disabled via DISALLOW_FILE_MODS constant'
            ];
        }

        // Get theme
        $theme = wp_get_theme($stylesheet);
        if (!$theme->exists()) {
            return [
                'valid' => false,
                'message' => 'Theme not found'
            ];
        }

        // Get theme directory
        $themeDir = $theme->get_stylesheet_directory();

        // Build full path
        $fullPath = $themeDir . '/' . ltrim($relativePath, '/');

        // For path traversal check, use realpath if file exists, otherwise validate the constructed path
        if (file_exists($fullPath)) {
            $realPath = realpath($fullPath);
            if ($realPath === false || strpos($realPath, realpath($themeDir)) !== 0) {
                return [
                    'valid' => false,
                    'message' => 'Invalid file path - path traversal detected'
                ];
            }
        } else {
            // For new files, validate the directory path and check for .. in relative path
            $realThemeDir = realpath($themeDir);
            if ($realThemeDir === false) {
                return [
                    'valid' => false,
                    'message' => 'Theme directory not found'
                ];
            }

            // Check if relative path contains path traversal attempts
            if (strpos($relativePath, '..') !== false) {
                return [
                    'valid' => false,
                    'message' => 'Invalid file path - path traversal detected'
                ];
            }

            // Validate that the constructed path is within theme directory
            $normalizedPath = str_replace('\\', '/', $fullPath);
            $normalizedThemeDir = str_replace('\\', '/', $realThemeDir);

            if (strpos($normalizedPath, $normalizedThemeDir) !== 0) {
                return [
                    'valid' => false,
                    'message' => 'Invalid file path - path traversal detected'
                ];
            }

            $realPath = $fullPath; // Use constructed path for new files
        }

        // Check file extension whitelist
        $extension = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
        $allowedExtensions = ['php', 'js', 'css', 'json', 'txt', 'md', 'xml', 'yml', 'yaml', 'ini', 'scss', 'less'];

        if (!in_array($extension, $allowedExtensions, true)) {
            return [
                'valid' => false,
                'message' => "File extension '.{$extension}' is not allowed"
            ];
        }

        return [
            'valid' => true,
            'message' => 'Path is valid',
            'full_path' => $realPath
        ];
    }

    /**
     * Read theme file contents
     *
     * @param string $stylesheet Theme stylesheet
     * @param string $relativePath Relative path within theme
     * @return array{success: bool, content?: string, error?: string}
     */
    public function readThemeFile(string $stylesheet, string $relativePath): array
    {
        $validation = $this->validateThemeFilePath($stylesheet, $relativePath);

        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => $validation['message']
            ];
        }

        $fullPath = $validation['full_path'];

        if (!file_exists($fullPath)) {
            return [
                'success' => false,
                'error' => 'File does not exist'
            ];
        }

        if (!is_readable($fullPath)) {
            return [
                'success' => false,
                'error' => 'File is not readable'
            ];
        }

        $content = file_get_contents($fullPath);

        if ($content === false) {
            return [
                'success' => false,
                'error' => 'Failed to read file'
            ];
        }

        return [
            'success' => true,
            'content' => $content
        ];
    }

    /**
     * Write theme file contents with backup
     *
     * @param string $stylesheet Theme stylesheet
     * @param string $relativePath Relative path within theme
     * @param string $content New file content
     * @param bool $createBackup Whether to create backup before writing
     * @return array{success: bool, backup_path?: string, error?: string}
     */
    public function writeThemeFile(
        string $stylesheet,
        string $relativePath,
        string $content,
        bool $createBackup = true
    ): array {
        $validation = $this->validateThemeFilePath($stylesheet, $relativePath);

        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => $validation['message']
            ];
        }

        $fullPath = $validation['full_path'];

        // Create backup if requested and file exists
        $backupPath = null;
        if ($createBackup && file_exists($fullPath)) {
            $backupPath = $fullPath . '.backup.' . time();
            if (!copy($fullPath, $backupPath)) {
                return [
                    'success' => false,
                    'error' => 'Failed to create backup file'
                ];
            }
        }

        // Ensure directory exists
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            if (!wp_mkdir_p($dir)) {
                return [
                    'success' => false,
                    'error' => 'Failed to create directory'
                ];
            }
        }

        // Write file
        $result = file_put_contents($fullPath, $content);

        if ($result === false) {
            // Restore backup if write failed
            if ($backupPath && file_exists($backupPath)) {
                copy($backupPath, $fullPath);
                unlink($backupPath);
            }

            return [
                'success' => false,
                'error' => 'Failed to write file'
            ];
        }

        $response = ['success' => true];
        if ($backupPath) {
            $response['backup_path'] = $backupPath;
        }

        return $response;
    }
}
