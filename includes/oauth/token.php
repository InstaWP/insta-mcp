<?php
/**
 * OAuth 2.1 Token Endpoint
 *
 * Exchanges authorization codes for access tokens
 * Supports: authorization_code and refresh_token grant types
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../load-wordpress.php';

use InstaWP\MCP\PHP\OAuth\ClientRepository;
use InstaWP\MCP\PHP\OAuth\AuthCodeRepository;
use InstaWP\MCP\PHP\OAuth\TokenRepository;
use InstaWP\MCP\PHP\OAuth\JwtService;

// Load configuration
$config = require __DIR__ . '/../config.php';
$oauthConfig = $config['oauth'] ?? [];

if (empty($oauthConfig['enabled'])) {
    http_response_code(503);
    echo json_encode(['error' => 'server_error', 'error_description' => 'OAuth is not enabled']);
    exit;
}

// Set JSON response headers
header('Content-Type: application/json');
header('Cache-Control: no-store');
header('Pragma: no-cache');

// Error helper
function sendError(string $error, string $description, int $httpCode = 400): void
{
    http_response_code($httpCode);
    echo json_encode([
        'error' => $error,
        'error_description' => $description
    ]);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('invalid_request', 'Only POST method is allowed', 405);
}

// Get POST parameters
$grantType = $_POST['grant_type'] ?? '';
$code = $_POST['code'] ?? '';
$redirectUri = $_POST['redirect_uri'] ?? '';
$clientId = $_POST['client_id'] ?? '';
$clientSecret = $_POST['client_secret'] ?? '';
$codeVerifier = $_POST['code_verifier'] ?? '';
$refreshToken = $_POST['refresh_token'] ?? '';

// Validate client credentials
if (empty($clientId) || empty($clientSecret)) {
    sendError('invalid_client', 'Missing client credentials', 401);
}

// Initialize repositories
$clientRepo = new ClientRepository();
$authCodeRepo = new AuthCodeRepository();
$tokenRepo = new TokenRepository();

// Validate client
if (!$clientRepo->validateClient($clientId, $clientSecret)) {
    sendError('invalid_client', 'Invalid client credentials', 401);
}

// Initialize JWT service
$jwtService = new JwtService(
    $oauthConfig['private_key_path'],
    $oauthConfig['public_key_path'],
    $oauthConfig['issuer'],
    $oauthConfig['resource_identifier']
);

// Handle different grant types
if ($grantType === 'authorization_code') {
    // Validate authorization code
    if (empty($code)) {
        sendError('invalid_request', 'Missing authorization code');
    }

    if (empty($redirectUri)) {
        sendError('invalid_request', 'Missing redirect_uri');
    }

    // Retrieve and revoke authorization code (single use)
    $codeData = $authCodeRepo->getAndRevokeCode($code);

    if (!$codeData) {
        sendError('invalid_grant', 'Invalid, expired, or already used authorization code');
    }

    // Verify client matches
    if ($codeData['client_id'] !== $clientId) {
        sendError('invalid_grant', 'Authorization code was issued to a different client');
    }

    // Verify redirect URI matches
    if ($codeData['redirect_uri'] !== $redirectUri) {
        sendError('invalid_grant', 'Redirect URI does not match');
    }

    // Verify PKCE code verifier
    if (!empty($codeData['code_challenge'])) {
        if (empty($codeVerifier)) {
            sendError('invalid_request', 'Missing code_verifier');
        }

        if (!$authCodeRepo->verifyPkceChallenge($codeData, $codeVerifier)) {
            sendError('invalid_grant', 'Invalid code_verifier');
        }
    }

    // Generate tokens
    $userId = (int) $codeData['user_id'];
    $scopes = $codeData['scopes'];
    $jti = bin2hex(random_bytes(16));
    $accessTokenTtl = $oauthConfig['access_token_ttl'] ?? 3600;
    $refreshTokenTtl = $oauthConfig['refresh_token_ttl'] ?? 2592000;

    // Create JWT access token
    $accessToken = $jwtService->createAccessToken(
        $jti,
        $userId,
        $clientId,
        $scopes,
        $accessTokenTtl
    );

    $expiresAt = time() + $accessTokenTtl;

    // Store access token (for revocation tracking)
    $tokenRepo->createAccessToken($jti, $clientId, $userId, $scopes, $expiresAt);

    // Generate refresh token
    $refreshTokenString = bin2hex(random_bytes(32));
    $tokenRepo->createRefreshToken($refreshTokenString, $jti, $clientId, $userId, $scopes, $refreshTokenTtl);

    // Return successful response
    echo json_encode([
        'access_token' => $accessToken,
        'token_type' => 'Bearer',
        'expires_in' => $accessTokenTtl,
        'refresh_token' => $refreshTokenString,
        'scope' => implode(' ', $scopes)
    ]);

} elseif ($grantType === 'refresh_token') {
    // Refresh token grant
    if (empty($refreshToken)) {
        sendError('invalid_request', 'Missing refresh_token');
    }

    // Validate refresh token
    $refreshTokenData = $tokenRepo->getRefreshToken($refreshToken);

    if (!$refreshTokenData) {
        sendError('invalid_grant', 'Invalid, expired, or revoked refresh token');
    }

    // Verify client matches
    if ($refreshTokenData['client_id'] !== $clientId) {
        sendError('invalid_grant', 'Refresh token was issued to a different client');
    }

    // Revoke old tokens
    $tokenRepo->revokeAccessToken($refreshTokenData['access_token_jti']);
    $tokenRepo->revokeRefreshToken($refreshToken);

    // Generate new tokens
    $userId = (int) $refreshTokenData['user_id'];
    $scopes = $refreshTokenData['scopes'];
    $jti = bin2hex(random_bytes(16));
    $accessTokenTtl = $oauthConfig['access_token_ttl'] ?? 3600;
    $refreshTokenTtl = $oauthConfig['refresh_token_ttl'] ?? 2592000;

    // Create new JWT access token
    $accessToken = $jwtService->createAccessToken(
        $jti,
        $userId,
        $clientId,
        $scopes,
        $accessTokenTtl
    );

    $expiresAt = time() + $accessTokenTtl;

    // Store new access token
    $tokenRepo->createAccessToken($jti, $clientId, $userId, $scopes, $expiresAt);

    // Generate new refresh token
    $newRefreshToken = bin2hex(random_bytes(32));
    $tokenRepo->createRefreshToken($newRefreshToken, $jti, $clientId, $userId, $scopes, $refreshTokenTtl);

    // Return successful response
    echo json_encode([
        'access_token' => $accessToken,
        'token_type' => 'Bearer',
        'expires_in' => $accessTokenTtl,
        'refresh_token' => $newRefreshToken,
        'scope' => implode(' ', $scopes)
    ]);

} else {
    sendError('unsupported_grant_type', 'Grant type must be authorization_code or refresh_token');
}
