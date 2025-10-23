<?php

namespace InstaWP\MCP\PHP\Auth;

/**
 * Bearer Token Authentication
 *
 * Validates tokens against user tokens stored in database.
 * Supports:
 * - Query parameter: ?t=<token> (GET requests only)
 * - Authorization header: Bearer <token>
 */
class BearerTokenAuth
{
    private TokenRepository $tokenRepo;

    public function __construct(?TokenRepository $tokenRepo = null)
    {
        $this->tokenRepo = $tokenRepo ?? new TokenRepository();
    }

    /**
     * Check if authentication is enabled
     *
     * Always returns true since token auth is always available
     */
    public function isEnabled(): bool
    {
        return true;
    }

    /**
     * Validate the incoming request for bearer token authentication
     *
     * @return array{authenticated: bool, error: ?string, headers: array<string, string>, user: ?array}
     */
    public function validate(): array
    {
        // Extract token from request
        $providedToken = $this->extractToken();

        // No token provided
        if ($providedToken === null) {
            return [
                'authenticated' => false,
                'error' => 'Authentication required',
                'headers' => [
                    'WWW-Authenticate' => 'Bearer realm="MCP Server", error="invalid_token", error_description="Bearer token required"'
                ],
                'user' => null
            ];
        }

        // Validate token against database
        $validation = $this->tokenRepo->validateToken($providedToken);

        if (!$validation['valid']) {
            return [
                'authenticated' => false,
                'error' => $validation['error'] ?? 'Invalid or expired token',
                'headers' => [
                    'WWW-Authenticate' => 'Bearer realm="MCP Server", error="invalid_token", error_description="' . ($validation['error'] ?? 'Invalid token') . '"'
                ],
                'user' => null
            ];
        }

        // Update last used timestamp
        $this->tokenRepo->updateLastUsed($providedToken);

        // Get user data for context
        $user_data = get_userdata($validation['user_id']);

        // Map WordPress roles to OAuth scopes
        $scopes = ['mcp:read']; // Default
        if ($user_data && !empty($user_data->roles)) {
            $roleScopes = [
                'administrator' => ['mcp:admin', 'mcp:delete', 'mcp:write', 'mcp:read'],
                'editor' => ['mcp:delete', 'mcp:write', 'mcp:read'],
                'author' => ['mcp:write', 'mcp:read'],
                'contributor' => ['mcp:read'],
                'subscriber' => ['mcp:read'],
            ];

            $grantedScopes = [];
            foreach ($user_data->roles as $role) {
                if (isset($roleScopes[$role])) {
                    $grantedScopes = array_merge($grantedScopes, $roleScopes[$role]);
                }
            }
            $scopes = !empty($grantedScopes) ? array_unique($grantedScopes) : $scopes;
        }

        $user_context = $user_data ? [
            'id' => $user_data->ID,
            'login' => $user_data->user_login,
            'email' => $user_data->user_email,
            'roles' => $user_data->roles,
            'scopes' => $scopes,
        ] : null;

        // Token is valid
        return [
            'authenticated' => true,
            'error' => null,
            'headers' => [],
            'user' => $user_context
        ];
    }

    /**
     * Extract bearer token from request
     *
     * Checks in priority order:
     * 1. Query parameter ?t=<token> (all HTTP methods)
     * 2. Authorization: Bearer <token> header
     */
    private function extractToken(): ?string
    {
        // Check query parameter (all HTTP methods)
        if (isset($_GET['t']) && !empty($_GET['t'])) {
            return trim($_GET['t']);
        }

        // Check Authorization header
        $authHeader = $this->getHeader('Authorization');
        if ($authHeader !== null && preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return trim($matches[1]);
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
