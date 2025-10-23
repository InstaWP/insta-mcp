<?php

namespace InstaWP\MCP\PHP\OAuth;

/**
 * Authorization Code Repository
 *
 * Manages short-lived authorization codes for the OAuth 2.1 flow
 */
class AuthCodeRepository
{
    private \wpdb $wpdb;
    private string $tableName;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->tableName = $wpdb->prefix . 'mcp_oauth_authorization_codes';
    }

    /**
     * Store a new authorization code
     *
     * @param string $code Unique authorization code
     * @param string $clientId Client identifier
     * @param int $userId WordPress user ID
     * @param string $redirectUri Redirect URI for this request
     * @param array $scopes Granted scopes
     * @param string|null $codeChallenge PKCE code challenge
     * @param string $codeChallengeMethod PKCE challenge method (S256 or plain)
     * @param int $ttl Time to live in seconds (default 600 = 10 minutes)
     * @return bool True on success
     */
    public function createCode(
        string $code,
        string $clientId,
        int $userId,
        string $redirectUri,
        array $scopes,
        ?string $codeChallenge = null,
        string $codeChallengeMethod = 'S256',
        int $ttl = 600
    ): bool {
        $expiresAt = date('Y-m-d H:i:s', time() + $ttl);

        $result = $this->wpdb->insert(
            $this->tableName,
            [
                'code' => $code,
                'client_id' => $clientId,
                'user_id' => $userId,
                'redirect_uri' => $redirectUri,
                'scopes' => implode(' ', $scopes),
                'code_challenge' => $codeChallenge,
                'code_challenge_method' => $codeChallengeMethod,
                'expires_at' => $expiresAt,
            ],
            ['%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s']
        );

        return $result !== false;
    }

    /**
     * Retrieve and consume an authorization code
     *
     * @param string $code Authorization code
     * @return array|null Code data or null if not found/expired/revoked
     */
    public function getAndRevokeCode(string $code): ?array
    {
        // Get the code
        $sql = $this->wpdb->prepare(
            "SELECT * FROM `{$this->tableName}`
             WHERE code = %s
             AND revoked = 0
             AND expires_at > NOW()",
            $code
        );

        $result = $this->wpdb->get_row($sql, ARRAY_A);

        if (!$result) {
            return null;
        }

        // Revoke the code immediately (single use only)
        $this->wpdb->update(
            $this->tableName,
            ['revoked' => 1],
            ['code' => $code],
            ['%d'],
            ['%s']
        );

        // Parse scopes
        $result['scopes'] = !empty($result['scopes']) ? explode(' ', $result['scopes']) : [];

        return $result;
    }

    /**
     * Verify PKCE code verifier against the stored challenge
     *
     * @param array $codeData Authorization code data
     * @param string $codeVerifier Code verifier from token request
     * @return bool True if verification succeeds
     */
    public function verifyPkceChallenge(array $codeData, string $codeVerifier): bool
    {
        // No PKCE used
        if (empty($codeData['code_challenge'])) {
            return true;
        }

        $method = $codeData['code_challenge_method'] ?? 'S256';

        if ($method === 'S256') {
            // SHA256 hash and base64url encode
            $computed = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
        } else {
            // Plain text
            $computed = $codeVerifier;
        }

        return hash_equals($codeData['code_challenge'], $computed);
    }

    /**
     * Clean up expired authorization codes
     *
     * @return int Number of deleted codes
     */
    public function cleanupExpired(): int
    {
        $result = $this->wpdb->query(
            "DELETE FROM `{$this->tableName}` WHERE expires_at < NOW()"
        );

        return $result !== false ? $result : 0;
    }
}
