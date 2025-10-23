<?php
/**
 * JSON Web Key Set (JWKS) Endpoint
 *
 * Provides public keys for JWT signature verification
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

use InstaWP\MCP\PHP\OAuth\JwtService;

try {
    $jwtService = new JwtService(
        $oauthConfig['private_key_path'],
        $oauthConfig['public_key_path'],
        $oauthConfig['issuer'],
        $oauthConfig['resource_identifier']
    );

    // Set headers
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Cache-Control: public, max-age=3600'); // Cache for 1 hour

    echo json_encode($jwtService->getJwks(), JSON_PRETTY_PRINT);
} catch (\Exception $e) {
    wp_die(
        json_encode([
            'error' => 'server_error',
            'error_description' => 'Failed to generate JWKS: ' . $e->getMessage()
        ]),
        'JWKS Error',
        [
            'response' => 500,
            'content-type' => 'application/json'
        ]
    );
}
