<?php

namespace InstaWP\MCP\PHP\OAuth;

/**
 * Token Repository
 *
 * Manages access tokens and refresh tokens
 */
class TokenRepository
{
    private \wpdb $wpdb;
    private string $accessTokensTable;
    private string $refreshTokensTable;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->accessTokensTable = $wpdb->prefix . 'mcp_oauth_access_tokens';
        $this->refreshTokensTable = $wpdb->prefix . 'mcp_oauth_refresh_tokens';
    }

    /**
     * Store an access token (for revocation tracking)
     *
     * @param string $jti JWT ID
     * @param string $clientId Client identifier
     * @param int $userId WordPress user ID
     * @param array $scopes Granted scopes
     * @param int $expiresAt Unix timestamp when token expires
     * @return bool True on success
     */
    public function createAccessToken(
        string $jti,
        string $clientId,
        int $userId,
        array $scopes,
        int $expiresAt
    ): bool {
        $result = $this->wpdb->insert(
            $this->accessTokensTable,
            [
                'jti' => $jti,
                'client_id' => $clientId,
                'user_id' => $userId,
                'scopes' => implode(' ', $scopes),
                'expires_at' => date('Y-m-d H:i:s', $expiresAt),
            ],
            ['%s', '%s', '%d', '%s', '%s']
        );

        return $result !== false;
    }

    /**
     * Store a refresh token
     *
     * @param string $refreshToken Refresh token string
     * @param string $accessTokenJti Associated access token JTI
     * @param string $clientId Client identifier
     * @param int $userId WordPress user ID
     * @param array $scopes Granted scopes
     * @param int $ttl Time to live in seconds (default 30 days)
     * @return bool True on success
     */
    public function createRefreshToken(
        string $refreshToken,
        string $accessTokenJti,
        string $clientId,
        int $userId,
        array $scopes,
        int $ttl = 2592000
    ): bool {
        $expiresAt = date('Y-m-d H:i:s', time() + $ttl);

        $result = $this->wpdb->insert(
            $this->refreshTokensTable,
            [
                'refresh_token' => $refreshToken,
                'access_token_jti' => $accessTokenJti,
                'client_id' => $clientId,
                'user_id' => $userId,
                'scopes' => implode(' ', $scopes),
                'expires_at' => $expiresAt,
            ],
            ['%s', '%s', '%s', '%d', '%s', '%s']
        );

        return $result !== false;
    }

    /**
     * Check if an access token is revoked
     *
     * @param string $jti JWT ID
     * @return bool True if revoked
     */
    public function isAccessTokenRevoked(string $jti): bool
    {
        $sql = $this->wpdb->prepare(
            "SELECT revoked FROM `{$this->accessTokensTable}` WHERE jti = %s",
            $jti
        );

        $revoked = $this->wpdb->get_var($sql);

        return $revoked === '1' || $revoked === 1;
    }

    /**
     * Retrieve and validate a refresh token
     *
     * @param string $refreshToken Refresh token string
     * @return array|null Token data or null if invalid/expired/revoked
     */
    public function getRefreshToken(string $refreshToken): ?array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM `{$this->refreshTokensTable}`
             WHERE refresh_token = %s
             AND revoked = 0
             AND expires_at > NOW()",
            $refreshToken
        );

        $result = $this->wpdb->get_row($sql, ARRAY_A);

        if (!$result) {
            return null;
        }

        $result['scopes'] = !empty($result['scopes']) ? explode(' ', $result['scopes']) : [];

        return $result;
    }

    /**
     * Revoke an access token
     *
     * @param string $jti JWT ID
     * @return bool True on success
     */
    public function revokeAccessToken(string $jti): bool
    {
        $result = $this->wpdb->update(
            $this->accessTokensTable,
            ['revoked' => 1],
            ['jti' => $jti],
            ['%d'],
            ['%s']
        );

        return $result !== false;
    }

    /**
     * Revoke a refresh token
     *
     * @param string $refreshToken Refresh token string
     * @return bool True on success
     */
    public function revokeRefreshToken(string $refreshToken): bool
    {
        $result = $this->wpdb->update(
            $this->refreshTokensTable,
            ['revoked' => 1],
            ['refresh_token' => $refreshToken],
            ['%d'],
            ['%s']
        );

        return $result !== false;
    }

    /**
     * Revoke all tokens for a user
     *
     * @param int $userId WordPress user ID
     * @return bool True on success
     */
    public function revokeAllUserTokens(int $userId): bool
    {
        $result1 = $this->wpdb->update(
            $this->accessTokensTable,
            ['revoked' => 1],
            ['user_id' => $userId],
            ['%d'],
            ['%d']
        );

        $result2 = $this->wpdb->update(
            $this->refreshTokensTable,
            ['revoked' => 1],
            ['user_id' => $userId],
            ['%d'],
            ['%d']
        );

        return $result1 !== false && $result2 !== false;
    }

    /**
     * Clean up expired tokens
     *
     * @return array{access: int, refresh: int} Number of deleted tokens
     */
    public function cleanupExpired(): array
    {
        $accessDeleted = $this->wpdb->query(
            "DELETE FROM `{$this->accessTokensTable}` WHERE expires_at < NOW()"
        );

        $refreshDeleted = $this->wpdb->query(
            "DELETE FROM `{$this->refreshTokensTable}` WHERE expires_at < NOW()"
        );

        return [
            'access' => $accessDeleted !== false ? $accessDeleted : 0,
            'refresh' => $refreshDeleted !== false ? $refreshDeleted : 0,
        ];
    }
}
