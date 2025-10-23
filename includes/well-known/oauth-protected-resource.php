<?php
/**
 * OAuth 2.0 Protected Resource Metadata (RFC 9728)
 *
 * Provides metadata about the MCP resource server
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Load configuration from WordPress options
$config = insta_mcp_get_config();
$oauthConfig = $config['oauth'] ?? [];

if (empty($oauthConfig['enabled'])) {
    wp_die('OAuth is not enabled', 'OAuth Not Enabled', ['response' => 503]);
}

use InstaWP\MCP\PHP\OAuth\ScopeRepository;

$scopeRepo = new ScopeRepository();

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Output metadata
echo json_encode([
    'resource' => $oauthConfig['resource_identifier'],
    'authorization_servers' => [$oauthConfig['issuer']],
    'scopes_supported' => array_keys($scopeRepo->getAvailableScopes()),
    'bearer_methods_supported' => ['header'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
