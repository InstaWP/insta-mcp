<?php

namespace InstaWP\MCP\PHP\OAuth;

/**
 * Client Repository
 *
 * Manages OAuth client registrations in the database
 */
class ClientRepository
{
    private \wpdb $wpdb;
    private string $tableName;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->tableName = $wpdb->prefix . 'mcp_oauth_clients';
    }

    /**
     * Get a client by client_id
     *
     * @param string $clientId
     * @return array|null Client data or null if not found
     */
    public function getClient(string $clientId): ?array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM `{$this->tableName}` WHERE client_id = %s",
            $clientId
        );

        $result = $this->wpdb->get_row($sql, ARRAY_A);

        if (!$result) {
            return null;
        }

        // Decode JSON fields
        $result['redirect_uris'] = json_decode($result['redirect_uris'], true);

        return $result;
    }

    /**
     * Validate client credentials
     *
     * @param string $clientId
     * @param string $clientSecret Plain text secret to verify
     * @return bool True if credentials are valid
     */
    public function validateClient(string $clientId, string $clientSecret): bool
    {
        $client = $this->getClient($clientId);

        if (!$client) {
            return false;
        }

        // Verify hashed secret using password_verify
        return password_verify($clientSecret, $client['client_secret']);
    }

    /**
     * Check if a redirect URI is registered for a client
     *
     * @param string $clientId
     * @param string $redirectUri
     * @return bool True if redirect URI is valid for this client
     */
    public function validateRedirectUri(string $clientId, string $redirectUri): bool
    {
        $client = $this->getClient($clientId);

        if (!$client) {
            return false;
        }

        return in_array($redirectUri, $client['redirect_uris'], true);
    }

    /**
     * Register a new OAuth client
     *
     * @param string $clientId Unique client identifier
     * @param string $clientSecret Plain text secret (will be hashed)
     * @param string $clientName Human-readable client name
     * @param array $redirectUris Array of allowed redirect URIs
     * @param bool $isConfidential Whether this is a confidential client
     * @return bool True on success
     */
    public function createClient(
        string $clientId,
        string $clientSecret,
        string $clientName,
        array $redirectUris,
        bool $isConfidential = true
    ): bool {
        $hashedSecret = password_hash($clientSecret, PASSWORD_BCRYPT);

        $result = $this->wpdb->insert(
            $this->tableName,
            [
                'client_id' => $clientId,
                'client_secret' => $hashedSecret,
                'client_name' => $clientName,
                'redirect_uris' => json_encode($redirectUris),
                'is_confidential' => $isConfidential ? 1 : 0,
            ],
            ['%s', '%s', '%s', '%s', '%d']
        );

        return $result !== false;
    }

    /**
     * Delete a client
     *
     * @param string $clientId
     * @return bool True on success
     */
    public function deleteClient(string $clientId): bool
    {
        $result = $this->wpdb->delete(
            $this->tableName,
            ['client_id' => $clientId],
            ['%s']
        );

        return $result !== false;
    }

    /**
     * Get all registered clients
     *
     * @return array List of clients
     */
    public function getAllClients(): array
    {
        $sql = "SELECT client_id, client_name, redirect_uris, is_confidential, created_at
                FROM `{$this->tableName}`
                ORDER BY created_at DESC";

        $results = $this->wpdb->get_results($sql, ARRAY_A);

        // Decode JSON fields
        foreach ($results as &$result) {
            $result['redirect_uris'] = json_decode($result['redirect_uris'], true);
        }

        return $results;
    }
}
