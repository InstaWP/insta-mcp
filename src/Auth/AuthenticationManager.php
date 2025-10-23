<?php

namespace InstaWP\MCP\PHP\Auth;

use InstaWP\MCP\PHP\OAuth\JwtService;
use InstaWP\MCP\PHP\OAuth\TokenRepository;

/**
 * Authentication Manager
 *
 * Orchestrates authentication strategy selection:
 * - OAuth (JWT tokens) - highest priority
 * - Static bearer tokens - fallback
 * - No authentication - default
 */
class AuthenticationManager
{
    private array $config;
    private ?OAuthAuthenticator $oauthAuth = null;
    private ?BearerTokenAuth $bearerAuth = null;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->initializeAuthenticators();
    }

    /**
     * Initialize authenticators based on configuration
     */
    private function initializeAuthenticators(): void
    {
        $oauthConfig = $this->config['oauth'] ?? [];

        // Initialize OAuth if enabled
        if (!empty($oauthConfig['enabled'])) {
            try {
                $jwtService = new JwtService(
                    $oauthConfig['private_key_path'],
                    $oauthConfig['public_key_path'],
                    $oauthConfig['issuer'],
                    $oauthConfig['resource_identifier']
                );

                $tokenRepo = new \InstaWP\MCP\PHP\OAuth\TokenRepository();
                $this->oauthAuth = new OAuthAuthenticator($jwtService, $tokenRepo);
            } catch (\Exception $e) {
                // Log error but don't fail - fall back to other methods
                error_log("Failed to initialize OAuth: " . $e->getMessage());
            }
        }

        // Initialize bearer token auth (always available)
        $this->bearerAuth = new BearerTokenAuth();
    }

    /**
     * Authenticate the incoming request
     *
     * Priority:
     * 1. OAuth (if enabled and JWT token present)
     * 2. User token authentication (query param or header)
     *
     * No open access mode - authentication is always required.
     *
     * @return array{authenticated: bool, error: ?string, headers: array, user: ?array, method: string}
     */
    public function authenticate(): array
    {
        // Try OAuth first (highest priority)
        if ($this->oauthAuth !== null) {
            $result = $this->oauthAuth->validate();

            // If OAuth token present but invalid, fail immediately
            if (!$result['authenticated'] && $result['error'] !== 'Authentication required') {
                return array_merge($result, ['method' => 'oauth']);
            }

            // If OAuth authentication succeeded
            if ($result['authenticated']) {
                return array_merge($result, ['method' => 'oauth']);
            }

            // No OAuth token present - fall through to try user token
        }

        // Try user token authentication (query param or header)
        $result = $this->bearerAuth->validate();

        if (!$result['authenticated']) {
            return array_merge($result, ['method' => 'token']);
        }

        return [
            'authenticated' => true,
            'error' => null,
            'headers' => [],
            'user' => $result['user'],
            'method' => 'token'
        ];
    }

    /**
     * Get authentication method being used
     *
     * @return string 'oauth' or 'token'
     */
    public function getAuthenticationMethod(): string
    {
        if ($this->oauthAuth !== null) {
            return 'oauth_or_token';
        }

        return 'token';
    }

    /**
     * Check if any authentication is enabled
     *
     * @return bool Always true - authentication is always required
     */
    public function isAuthenticationEnabled(): bool
    {
        return true;
    }

    /**
     * Send authentication error response
     *
     * @param string $error Error message
     * @param array<string, string> $headers Response headers
     */
    public function sendUnauthorizedResponse(string $error, array $headers = []): void
    {
        http_response_code(401);
        header('Content-Type: application/json');

        foreach ($headers as $name => $value) {
            header("$name: $value");
        }

        echo json_encode([
            'error' => 'Unauthorized',
            'message' => $error,
            'authentication_method' => $this->getAuthenticationMethod()
        ]);
    }
}
