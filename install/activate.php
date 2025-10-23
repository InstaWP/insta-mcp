<?php
/**
 * Plugin Activation
 *
 * Creates necessary database tables and sets default options
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Run plugin activation
 */
function insta_mcp_run_activation() {
    global $wpdb;

    // Create user tokens table (always needed)
    insta_mcp_create_user_tokens_table();

    // Only create OAuth tables if feature is enabled
    if (INSTA_MCP_OAUTH_FEATURE_ENABLED) {
        insta_mcp_create_oauth_tables();
    }

    // Set default options
    add_option('insta_mcp_endpoint_slug', 'insta-mcp');
    add_option('insta_mcp_safe_mode', false);
    add_option('insta_mcp_oauth_enabled', false);

    // OAuth defaults (only if feature enabled)
    if (INSTA_MCP_OAUTH_FEATURE_ENABLED) {
        add_option('insta_mcp_oauth_issuer', home_url('/insta-mcp'));
        add_option('insta_mcp_oauth_resource_identifier', home_url('/insta-mcp'));
        add_option('insta_mcp_oauth_private_key_path', '');
        add_option('insta_mcp_oauth_public_key_path', '');
        add_option('insta_mcp_oauth_access_token_ttl', 3600);
        add_option('insta_mcp_oauth_refresh_token_ttl', 2592000);
        add_option('insta_mcp_oauth_authorization_code_ttl', 600);
    }

    // Create default token for the installing user
    insta_mcp_create_default_token();

    // Register rewrite rules
    insta_mcp_register_rewrite_rules();

    // Flush rewrite rules to activate our custom endpoints
    flush_rewrite_rules();
}

/**
 * Create user tokens table
 *
 * Stores API tokens tied to WordPress users for simple authentication
 */
function insta_mcp_create_user_tokens_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'insta_mcp_user_tokens';

    $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `user_id` bigint(20) unsigned NOT NULL,
        `token_hash` varchar(64) NOT NULL COMMENT 'SHA256 hash of token',
        `label` varchar(255) DEFAULT NULL COMMENT 'User-friendly description',
        `expires_at` datetime DEFAULT NULL COMMENT 'NULL = no expiration',
        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
        `last_used_at` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `token_hash` (`token_hash`),
        KEY `user_id` (`user_id`),
        KEY `expires_at` (`expires_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Create default token for the installing user
 */
function insta_mcp_create_default_token() {
    $user_id = get_current_user_id();

    if (!$user_id) {
        return; // No user context during activation (e.g., WP-CLI)
    }

    // Check if user already has a default token
    global $wpdb;
    $table_name = $wpdb->prefix . 'insta_mcp_user_tokens';
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM `{$table_name}` WHERE user_id = %d AND label = %s",
        $user_id,
        'Default Token'
    ));

    if ($existing > 0) {
        return; // Default token already exists
    }

    // Check if a pre-configured token is set
    $preconfigured_token = get_option('instamcp_default_token');

    if ($preconfigured_token && is_string($preconfigured_token) && strlen($preconfigured_token) === 64) {
        // Use the pre-configured token
        $token = $preconfigured_token;

        // Delete the option after using it (security: don't leave it in the database)
        delete_option('instamcp_default_token');
    } else {
        // Generate secure random token
        $token = bin2hex(random_bytes(32)); // 64 character hex string

        // If a preconfigured token was set but invalid, delete it anyway
        if ($preconfigured_token !== false) {
            delete_option('instamcp_default_token');
        }
    }

    $token_hash = hash('sha256', $token);

    // Store token
    $wpdb->insert(
        $table_name,
        [
            'user_id' => $user_id,
            'token_hash' => $token_hash,
            'label' => 'Default Token',
            'expires_at' => null, // No expiration
            'created_at' => current_time('mysql'),
        ],
        ['%d', '%s', '%s', '%s', '%s']
    );

    // Store the plain token temporarily so user can access it
    // We'll display this in admin notice after activation
    set_transient('insta_mcp_new_token_' . $user_id, $token, 300); // 5 minutes
}

/**
 * Create OAuth database tables
 */
function insta_mcp_create_oauth_tables() {
    global $wpdb;

    // Table prefix
    $prefix = $wpdb->prefix . 'insta_mcp_';

    /**
     * Table 1: OAuth Clients
     * Stores registered applications that can connect via OAuth
     */
    $clients_table = $prefix . 'oauth_clients';
    $sql_clients = "CREATE TABLE IF NOT EXISTS `{$clients_table}` (
        `client_id` varchar(80) NOT NULL,
        `client_secret` varchar(255) NOT NULL COMMENT 'Hashed with bcrypt',
        `client_name` varchar(255) NOT NULL,
        `redirect_uris` text NOT NULL COMMENT 'JSON array of allowed redirect URIs',
        `grant_types` varchar(255) DEFAULT 'authorization_code,refresh_token',
        `is_confidential` tinyint(1) DEFAULT 1 COMMENT '1 for confidential clients, 0 for public',
        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`client_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    /**
     * Table 2: Authorization Codes
     * Stores short-lived authorization codes (10 minutes)
     */
    $codes_table = $prefix . 'oauth_authorization_codes';
    $sql_codes = "CREATE TABLE IF NOT EXISTS `{$codes_table}` (
        `code` varchar(128) NOT NULL,
        `client_id` varchar(80) NOT NULL,
        `user_id` bigint(20) unsigned NOT NULL,
        `redirect_uri` text NOT NULL,
        `scopes` text DEFAULT NULL COMMENT 'Space-separated list of scopes',
        `code_challenge` varchar(128) DEFAULT NULL COMMENT 'PKCE code challenge',
        `code_challenge_method` varchar(10) DEFAULT 'S256',
        `expires_at` datetime NOT NULL,
        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
        `revoked` tinyint(1) DEFAULT 0,
        PRIMARY KEY (`code`),
        KEY `client_id` (`client_id`),
        KEY `user_id` (`user_id`),
        KEY `expires_at` (`expires_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    /**
     * Table 3: Access Tokens (for revocation tracking)
     * JWT tokens are stateless, but we track JTI for revocation
     */
    $tokens_table = $prefix . 'oauth_access_tokens';
    $sql_tokens = "CREATE TABLE IF NOT EXISTS `{$tokens_table}` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `jti` varchar(128) NOT NULL COMMENT 'JWT ID for revocation',
        `client_id` varchar(80) NOT NULL,
        `user_id` bigint(20) unsigned NOT NULL,
        `scopes` text DEFAULT NULL,
        `expires_at` datetime NOT NULL,
        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
        `revoked` tinyint(1) DEFAULT 0,
        PRIMARY KEY (`id`),
        UNIQUE KEY `jti` (`jti`),
        KEY `client_id` (`client_id`),
        KEY `user_id` (`user_id`),
        KEY `expires_at` (`expires_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    /**
     * Table 4: Refresh Tokens
     * Long-lived tokens for obtaining new access tokens
     */
    $refresh_table = $prefix . 'oauth_refresh_tokens';
    $sql_refresh = "CREATE TABLE IF NOT EXISTS `{$refresh_table}` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `refresh_token` varchar(128) NOT NULL,
        `access_token_jti` varchar(128) NOT NULL COMMENT 'Linked to access token',
        `client_id` varchar(80) NOT NULL,
        `user_id` bigint(20) unsigned NOT NULL,
        `scopes` text DEFAULT NULL,
        `expires_at` datetime NOT NULL,
        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
        `revoked` tinyint(1) DEFAULT 0,
        PRIMARY KEY (`id`),
        UNIQUE KEY `refresh_token` (`refresh_token`),
        KEY `access_token_jti` (`access_token_jti`),
        KEY `client_id` (`client_id`),
        KEY `user_id` (`user_id`),
        KEY `expires_at` (`expires_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    // Execute table creation using dbDelta for safe upgrades
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    dbDelta($sql_clients);
    dbDelta($sql_codes);
    dbDelta($sql_tokens);
    dbDelta($sql_refresh);
}
