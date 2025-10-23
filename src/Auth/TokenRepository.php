<?php

namespace InstaWP\MCP\PHP\Auth;

/**
 * Token Repository
 *
 * Manages user API tokens stored in wp_insta_mcp_user_tokens table
 */
class TokenRepository
{
    private \wpdb $wpdb;
    private string $table_name;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'insta_mcp_user_tokens';
    }

    /**
     * Create a new token for a user
     *
     * @param int $user_id WordPress user ID
     * @param string|null $label Optional label/description
     * @param string|null $expires_at Optional expiration datetime (MySQL format)
     * @return array{success: bool, token: ?string, error: ?string}
     */
    public function createToken(int $user_id, ?string $label = null, ?string $expires_at = null): array
    {
        // Verify user exists
        $user = get_userdata($user_id);
        if (!$user) {
            return [
                'success' => false,
                'token' => null,
                'error' => 'User not found'
            ];
        }

        // Generate secure random token (64 hex characters = 32 bytes)
        $token = bin2hex(random_bytes(32));
        $token_hash = hash('sha256', $token);

        // Insert into database
        $result = $this->wpdb->insert(
            $this->table_name,
            [
                'user_id' => $user_id,
                'token_hash' => $token_hash,
                'label' => $label,
                'expires_at' => $expires_at,
                'created_at' => current_time('mysql'),
                'last_used_at' => null,
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s']
        );

        if ($result === false) {
            return [
                'success' => false,
                'token' => null,
                'error' => $this->wpdb->last_error ?: 'Database error'
            ];
        }

        return [
            'success' => true,
            'token' => $token,
            'error' => null
        ];
    }

    /**
     * Validate a token and return user information
     *
     * @param string $token The plain text token
     * @return array{valid: bool, user_id: ?int, error: ?string}
     */
    public function validateToken(string $token): array
    {
        $token_hash = hash('sha256', $token);

        $row = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT user_id, expires_at FROM `{$this->table_name}` WHERE token_hash = %s",
            $token_hash
        ), ARRAY_A);

        if (!$row) {
            return [
                'valid' => false,
                'user_id' => null,
                'error' => 'Invalid token'
            ];
        }

        // Check expiration
        if ($row['expires_at'] !== null) {
            $expires_at = strtotime($row['expires_at']);
            if ($expires_at < time()) {
                return [
                    'valid' => false,
                    'user_id' => null,
                    'error' => 'Token expired'
                ];
            }
        }

        // Verify user still exists
        $user = get_userdata((int)$row['user_id']);
        if (!$user) {
            return [
                'valid' => false,
                'user_id' => null,
                'error' => 'User not found'
            ];
        }

        return [
            'valid' => true,
            'user_id' => (int)$row['user_id'],
            'error' => null
        ];
    }

    /**
     * Update the last_used_at timestamp for a token
     *
     * @param string $token The plain text token
     * @return bool Success status
     */
    public function updateLastUsed(string $token): bool
    {
        $token_hash = hash('sha256', $token);

        $result = $this->wpdb->update(
            $this->table_name,
            ['last_used_at' => current_time('mysql')],
            ['token_hash' => $token_hash],
            ['%s'],
            ['%s']
        );

        return $result !== false;
    }

    /**
     * Get all tokens for a specific user
     *
     * @param int $user_id WordPress user ID
     * @return array Array of token records (without token_hash)
     */
    public function getUserTokens(int $user_id): array
    {
        $results = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT id, user_id, label, expires_at, created_at, last_used_at
             FROM `{$this->table_name}`
             WHERE user_id = %d
             ORDER BY created_at DESC",
            $user_id
        ), ARRAY_A);

        return $results ?: [];
    }

    /**
     * Revoke (delete) a token by ID
     *
     * @param int $token_id Token ID
     * @param int $user_id User ID (for authorization check)
     * @return bool Success status
     */
    public function revokeToken(int $token_id, int $user_id): bool
    {
        $result = $this->wpdb->delete(
            $this->table_name,
            [
                'id' => $token_id,
                'user_id' => $user_id, // Ensure user can only delete their own tokens
            ],
            ['%d', '%d']
        );

        return $result !== false && $result > 0;
    }

    /**
     * Delete expired tokens (cleanup job)
     *
     * @return int Number of tokens deleted
     */
    public function deleteExpiredTokens(): int
    {
        $result = $this->wpdb->query(
            "DELETE FROM `{$this->table_name}`
             WHERE expires_at IS NOT NULL
             AND expires_at < NOW()"
        );

        return $result !== false ? (int)$result : 0;
    }
}
