<?php

namespace InstaWP\MCP\PHP\OAuth;

/**
 * Scope Repository
 *
 * Manages OAuth scopes and their mapping to WordPress roles
 */
class ScopeRepository
{
    /**
     * All available MCP scopes
     */
    private const AVAILABLE_SCOPES = [
        'mcp:read' => 'Read-only access to content, taxonomies, and site info',
        'mcp:write' => 'Create and update content and taxonomy terms',
        'mcp:delete' => 'Delete content and taxonomy terms',
        'mcp:admin' => 'Full administrative access including safe mode override',
    ];

    /**
     * WordPress role to scope mapping
     */
    private const ROLE_SCOPE_MAP = [
        'administrator' => ['mcp:admin', 'mcp:delete', 'mcp:write', 'mcp:read'],
        'editor' => ['mcp:delete', 'mcp:write', 'mcp:read'],
        'author' => ['mcp:write', 'mcp:read'],
        'contributor' => ['mcp:read'],
        'subscriber' => ['mcp:read'],
    ];

    /**
     * Get all available scopes
     *
     * @return array<string, string> Scope => Description
     */
    public function getAvailableScopes(): array
    {
        return self::AVAILABLE_SCOPES;
    }

    /**
     * Get scopes for a WordPress user based on their roles
     *
     * @param int $userId WordPress user ID
     * @return array List of granted scopes
     */
    public function getScopesForUser(int $userId): array
    {
        $user = get_userdata($userId);

        if (!$user || empty($user->roles)) {
            return ['mcp:read']; // Default: read-only access
        }

        $grantedScopes = [];

        // Collect scopes from all user roles
        foreach ($user->roles as $role) {
            if (isset(self::ROLE_SCOPE_MAP[$role])) {
                $grantedScopes = array_merge($grantedScopes, self::ROLE_SCOPE_MAP[$role]);
            }
        }

        // Remove duplicates and return
        return array_unique($grantedScopes);
    }

    /**
     * Filter requested scopes to only what the user is allowed to have
     *
     * @param int $userId WordPress user ID
     * @param array $requestedScopes Scopes being requested
     * @return array Filtered scopes (intersection of requested and allowed)
     */
    public function filterScopesForUser(int $userId, array $requestedScopes): array
    {
        $userScopes = $this->getScopesForUser($userId);

        // Return intersection of requested and allowed scopes
        return array_values(array_intersect($requestedScopes, $userScopes));
    }

    /**
     * Validate that all requested scopes exist
     *
     * @param array $scopes Scopes to validate
     * @return bool True if all scopes are valid
     */
    public function validateScopes(array $scopes): bool
    {
        $validScopes = array_keys(self::AVAILABLE_SCOPES);

        foreach ($scopes as $scope) {
            if (!in_array($scope, $validScopes, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a user has a specific scope
     *
     * @param int $userId WordPress user ID
     * @param string $requiredScope Scope to check
     * @return bool True if user has the scope
     */
    public function userHasScope(int $userId, string $requiredScope): bool
    {
        $userScopes = $this->getScopesForUser($userId);
        return in_array($requiredScope, $userScopes, true);
    }

    /**
     * Check if scopes include a required scope
     *
     * @param array $userScopes User's granted scopes
     * @param string $requiredScope Required scope
     * @return bool True if required scope is present
     */
    public function scopesInclude(array $userScopes, string $requiredScope): bool
    {
        // Admin scope grants all permissions
        if (in_array('mcp:admin', $userScopes, true)) {
            return true;
        }

        return in_array($requiredScope, $userScopes, true);
    }
}
