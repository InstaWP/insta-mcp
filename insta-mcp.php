<?php
/**
 * Plugin Name: InstaMCP
 * Plugin URI: https://instawp.com/
 * Description: Model Context Protocol server for WordPress with OAuth 2.1 authentication
 * Version: 1.0.0
 * Author: InstaWP
 * Author URI: https://instawp.com/
 * Requires PHP: 8.2
 * Requires at least: 6.0
 * Text Domain: insta-mcp
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('INSTA_MCP_VERSION', '1.0.0');
define('INSTA_MCP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('INSTA_MCP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('INSTA_MCP_PLUGIN_FILE', __FILE__);

// Feature flag: OAuth functionality (disabled by default)
// Set to true to enable OAuth 2.1 authentication
define('INSTA_MCP_OAUTH_FEATURE_ENABLED', false);

// Load Composer autoloader
if (file_exists(INSTA_MCP_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once INSTA_MCP_PLUGIN_DIR . 'vendor/autoload.php';
}

/**
 * Plugin Activation
 */
function insta_mcp_activate() {
    require_once INSTA_MCP_PLUGIN_DIR . 'install/activate.php';
    insta_mcp_run_activation();
}
register_activation_hook(__FILE__, 'insta_mcp_activate');

/**
 * Plugin Deactivation
 */
function insta_mcp_deactivate() {
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'insta_mcp_deactivate');

/**
 * Initialize plugin
 */
function insta_mcp_init() {
    // Register rewrite rules
    insta_mcp_register_rewrite_rules();

    // Register query vars
    add_filter('query_vars', 'insta_mcp_register_query_vars');
}
add_action('init', 'insta_mcp_init');

/**
 * Register rewrite rules for MCP endpoints and OAuth discovery
 */
function insta_mcp_register_rewrite_rules() {
    $slug = get_option('insta_mcp_endpoint_slug', 'insta-mcp');

    // MCP HTTP endpoint: /insta-mcp (always enabled)
    add_rewrite_rule(
        '^' . $slug . '/?$',
        'index.php?insta_mcp_endpoint=1',
        'top'
    );

    // OAuth routes (only if feature is enabled)
    if (INSTA_MCP_OAUTH_FEATURE_ENABLED) {
        // RFC 8414 compliant OAuth metadata discovery
        // /.well-known/oauth-authorization-server/insta-mcp
        add_rewrite_rule(
            '^\.well-known/oauth-authorization-server/' . $slug . '/?$',
            'index.php?insta_mcp_oauth_meta=authorization-server',
            'top'
        );

        add_rewrite_rule(
            '^\.well-known/oauth-protected-resource/' . $slug . '/?$',
            'index.php?insta_mcp_oauth_meta=protected-resource',
            'top'
        );

        add_rewrite_rule(
            '^\.well-known/jwks\.json/' . $slug . '/?$',
            'index.php?insta_mcp_oauth_meta=jwks',
            'top'
        );

        // OAuth authorization and token endpoints
        add_rewrite_rule(
            '^' . $slug . '/oauth/(authorize|token)/?$',
            'index.php?insta_mcp_oauth=$matches[1]',
            'top'
        );
    }
}

/**
 * Register custom query vars
 */
function insta_mcp_register_query_vars($vars) {
    $vars[] = 'insta_mcp_endpoint';
    $vars[] = 'insta_mcp_oauth_meta';
    $vars[] = 'insta_mcp_oauth';
    return $vars;
}

/**
 * Handle template redirect for MCP endpoints
 */
function insta_mcp_handle_request() {
    // Handle MCP HTTP endpoint (always enabled)
    if (get_query_var('insta_mcp_endpoint')) {
        require_once INSTA_MCP_PLUGIN_DIR . 'includes/endpoints/mcp-http.php';
        exit;
    }

    // OAuth endpoints (only if feature is enabled)
    if (!INSTA_MCP_OAUTH_FEATURE_ENABLED) {
        return;
    }

    // Handle OAuth metadata endpoints
    if ($meta = get_query_var('insta_mcp_oauth_meta')) {
        insta_mcp_serve_oauth_metadata($meta);
        exit;
    }

    // Handle OAuth flow endpoints
    if ($oauth = get_query_var('insta_mcp_oauth')) {
        insta_mcp_handle_oauth_flow($oauth);
        exit;
    }
}
add_action('template_redirect', 'insta_mcp_handle_request', 1);

/**
 * Serve OAuth metadata endpoints
 */
function insta_mcp_serve_oauth_metadata($type) {
    switch ($type) {
        case 'authorization-server':
            require_once INSTA_MCP_PLUGIN_DIR . 'includes/well-known/oauth-authorization-server.php';
            break;
        case 'protected-resource':
            require_once INSTA_MCP_PLUGIN_DIR . 'includes/well-known/oauth-protected-resource.php';
            break;
        case 'jwks':
            require_once INSTA_MCP_PLUGIN_DIR . 'includes/well-known/jwks.php';
            break;
    }
}

/**
 * Handle OAuth flow endpoints
 */
function insta_mcp_handle_oauth_flow($flow) {
    switch ($flow) {
        case 'authorize':
            require_once INSTA_MCP_PLUGIN_DIR . 'includes/oauth/authorize.php';
            break;
        case 'token':
            require_once INSTA_MCP_PLUGIN_DIR . 'includes/oauth/token.php';
            break;
    }
}

/**
 * Add admin menu
 */
function insta_mcp_add_admin_menu() {
    add_options_page(
        __('InstaMCP Settings', 'insta-mcp'),
        __('InstaMCP', 'insta-mcp'),
        'manage_options',
        'insta-mcp',
        'insta_mcp_settings_page'
    );
}
add_action('admin_menu', 'insta_mcp_add_admin_menu');

/**
 * Render admin settings page
 */
function insta_mcp_settings_page() {
    require_once INSTA_MCP_PLUGIN_DIR . 'admin/settings.php';
}

/**
 * Get configuration array from WordPress options
 *
 * @return array Configuration array compatible with AuthenticationManager
 */
function insta_mcp_get_config() {
    $config = [
        'safe_mode' => get_option('insta_mcp_safe_mode', false),
        'oauth' => [
            'enabled' => get_option('insta_mcp_oauth_enabled', false),
            'issuer' => get_option('insta_mcp_oauth_issuer', home_url('/insta-mcp')),
            'resource_identifier' => get_option('insta_mcp_oauth_resource_identifier', home_url('/insta-mcp')),
            'private_key_path' => get_option('insta_mcp_oauth_private_key_path', ''),
            'public_key_path' => get_option('insta_mcp_oauth_public_key_path', ''),
            'access_token_ttl' => get_option('insta_mcp_oauth_access_token_ttl', 3600),
            'refresh_token_ttl' => get_option('insta_mcp_oauth_refresh_token_ttl', 2592000),
            'authorization_code_ttl' => get_option('insta_mcp_oauth_authorization_code_ttl', 600),
        ],
    ];

    // Define WP_MCP_SAFE_MODE constant for tools
    if ($config['safe_mode'] && !defined('WP_MCP_SAFE_MODE')) {
        define('WP_MCP_SAFE_MODE', true);
    }

    return $config;
}
