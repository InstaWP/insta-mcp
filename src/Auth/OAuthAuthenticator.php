<?php

namespace InstaWP\MCP\PHP\Auth;

use InstaWP\MCP\PHP\OAuth\JwtService;
use InstaWP\MCP\PHP\OAuth\TokenRepository;

/**
 * OAuth Authenticator
 *
 * Validates JWT access tokens for OAuth 2.1 authentication
 */
class OAuthAuthenticator
{
    private JwtService $jwtService;
    private TokenRepository $tokenRepo;

    public function __construct(JwtService $jwtService, TokenRepository $tokenRepo)
    {
        $this->jwtService = $jwtService;
        $this->tokenRepo = $tokenRepo;
    }

    /**
     * Validate the incoming request for OAuth JWT token
     *
     * @return array{authenticated: bool, error: ?string, headers: array<string, string>, user: ?array}
     */
    public function validate(): array
    {
        // Extract token from request
        $token = $this->extractToken();

        // Get metadata URL for WWW-Authenticate header (RFC 9728)
        $issuer = $this->jwtService->getIssuer();
        $metadataUrl = $issuer . '/.well-known/oauth-protected-resource';

        // No token provided
        if ($token === null) {
            return [
                'authenticated' => false,
                'error' => 'Authentication required',
                'headers' => [
                    'WWW-Authenticate' => sprintf(
                        'Bearer realm="MCP Server", resource_metadata="%s", error="invalid_token", error_description="Bearer token required"',
                        $metadataUrl
                    )
                ],
                'user' => null
            ];
        }

        // Validate and parse JWT token
        $validation = $this->jwtService->validateToken($token);

        if (!$validation['valid']) {
            return [
                'authenticated' => false,
                'error' => $validation['error'],
                'headers' => [
                    'WWW-Authenticate' => sprintf(
                        'Bearer realm="MCP Server", resource_metadata="%s", error="invalid_token", error_description="%s"',
                        $metadataUrl,
                        $validation['error']
                    )
                ],
                'user' => null
            ];
        }

        $claims = $validation['claims'];

        // Check if token has been revoked
        if ($this->tokenRepo->isAccessTokenRevoked($claims['jti'])) {
            return [
                'authenticated' => false,
                'error' => 'Token has been revoked',
                'headers' => [
                    'WWW-Authenticate' => sprintf(
                        'Bearer realm="MCP Server", resource_metadata="%s", error="invalid_token", error_description="Token has been revoked"',
                        $metadataUrl
                    )
                ],
                'user' => null
            ];
        }

        // Token is valid - return user context
        return [
            'authenticated' => true,
            'error' => null,
            'headers' => [],
            'user' => [
                'user_id' => $claims['user_id'],
                'username' => $claims['username'],
                'roles' => $claims['roles'],
                'scopes' => $claims['scopes'],
                'client_id' => $claims['client_id'],
            ]
        ];
    }

    /**
     * Extract bearer token from request headers
     *
     * Checks both:
     * 1. Authorization: Bearer <token>
     * 2. X-MCP-API-Key: <token>
     */
    private function extractToken(): ?string
    {
        // Check Authorization header (standard)
        $authHeader = $this->getHeader('Authorization');
        if ($authHeader !== null && preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return trim($matches[1]);
        }

        // Check X-MCP-API-Key header (fallback)
        $apiKeyHeader = $this->getHeader('X-MCP-API-Key');
        if ($apiKeyHeader !== null) {
            return trim($apiKeyHeader);
        }

        return null;
    }

    /**
     * Get header value from request (case-insensitive)
     */
    private function getHeader(string $name): ?string
    {
        // Check $_SERVER with HTTP_ prefix
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (isset($_SERVER[$serverKey])) {
            return $_SERVER[$serverKey];
        }

        // Check without HTTP_ prefix (for Authorization header)
        $serverKeyAlt = strtoupper(str_replace('-', '_', $name));
        if (isset($_SERVER[$serverKeyAlt])) {
            return $_SERVER[$serverKeyAlt];
        }

        // Check getallheaders() if available
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $key => $value) {
                if (strcasecmp($key, $name) === 0) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * Send 401 Unauthorized response with proper headers
     *
     * @param string $error Error message
     * @param array<string, string> $headers Additional headers to send
     */
    public function sendUnauthorizedResponse(string $error, array $headers = []): void
    {
        http_response_code(401);
        header('Content-Type: application/json');

        // Send WWW-Authenticate and other headers
        foreach ($headers as $name => $value) {
            header("$name: $value");
        }

        echo json_encode([
            'error' => 'Unauthorized',
            'message' => $error
        ]);
    }
}
