<?php
/**
 * OAuth 2.0 Authorization Server Metadata (RFC 8414)
 *
 * Provides metadata about the OAuth authorization server
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
$slug = get_option('insta_mcp_endpoint_slug', 'insta-mcp');
$baseUrl = $oauthConfig['issuer'];

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Output metadata
echo json_encode([
    'issuer' => $oauthConfig['issuer'],
    'authorization_endpoint' => home_url("/{$slug}/oauth/authorize"),
    'token_endpoint' => home_url("/{$slug}/oauth/token"),
    'jwks_uri' => home_url("/.well-known/jwks.json/{$slug}"),
    'scopes_supported' => array_keys($scopeRepo->getAvailableScopes()),
    'response_types_supported' => ['code'],
    'response_modes_supported' => ['query'],
    'grant_types_supported' => ['authorization_code', 'refresh_token'],
    'token_endpoint_auth_methods_supported' => ['client_secret_post'],
    'code_challenge_methods_supported' => ['S256', 'plain'],
    'service_documentation' => 'https://github.com/instawp/insta-mcp',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
