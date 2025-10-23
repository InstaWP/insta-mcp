# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

InstaMCP is a WordPress plugin that implements a Model Context Protocol (MCP) server with dual authentication support (OAuth 2.1 + User Tokens). It enables AI assistants like Claude to interact with WordPress sites through a standardized protocol, providing tools for content management, user operations, and site administration.

**Authentication Methods:**
1. **User Tokens** - Simple API tokens tied to WordPress users (via query param or header)
2. **OAuth 2.1** - Full OAuth authorization code flow with JWT tokens (optional)

## Plugin Architecture

### Core Structure

```
insta-mcp/
├── insta-mcp.php              # Main plugin file (hooks, rewrite rules)
├── src/                       # MCP implementation (Tools, Services, OAuth, Auth)
├── includes/
│   ├── endpoints/             # MCP HTTP endpoint handler
│   ├── oauth/                 # OAuth authorization/token flows
│   └── well-known/            # RFC 8414/9728 discovery endpoints
├── admin/                     # WordPress admin settings UI
└── install/                   # Plugin activation (database setup)
```

### Key Components

**Main Plugin File (`insta-mcp.php`):**
- WordPress plugin header and constants
- Activation/deactivation hooks
- Rewrite rules for RFC 8414 OAuth discovery
- Query var registration
- Template redirect handling
- Configuration helper (`insta_mcp_get_config()`)

**MCP Endpoint (`includes/endpoints/mcp-http.php`):**
- Loads configuration from WordPress options
- Handles authentication via AuthenticationManager
- Initializes all 17 MCP tools
- Creates MCP server with PSR-7 transport
- Returns responses via SAPI emitter

**OAuth Discovery (`includes/well-known/*.php`):**
- RFC 8414 Authorization Server Metadata
- RFC 9728 Protected Resource Metadata
- JWKS endpoint for JWT public keys

**Admin UI (`admin/settings.php`):**
- General settings (endpoint slug, safe mode)
- API Tokens management (create, view, revoke)
- OAuth configuration (issuer, keys, etc.) - optional
- Endpoints reference

## Development Commands

### Plugin Management
```bash
# Activate plugin
wp plugin activate insta-mcp

# Deactivate plugin
wp plugin deactivate insta-mcp

# Uninstall plugin (removes tables)
wp plugin uninstall insta-mcp

# Flush rewrite rules after code changes
wp rewrite flush
```

### Testing
```bash
# Run PHPUnit tests
vendor/bin/phpunit

# Run tests with coverage
vendor/bin/phpunit --coverage-html coverage/

# Run PHPStan analysis
vendor/bin/phpstan analyse
```

### Testing MCP Tools

Test tools via HTTP using the WordPress rewrite system:

```bash
# Test MCP endpoint without auth (should return 401 with WWW-Authenticate header)
curl -I https://your-site.com/insta-mcp

# Test with user token via query parameter (all HTTP methods)
curl https://your-site.com/insta-mcp?t=YOUR_TOKEN_HERE
curl -X POST https://your-site.com/insta-mcp?t=YOUR_TOKEN_HERE -d '...'

# Test with user token via header (all HTTP methods)
curl -H "Authorization: Bearer YOUR_TOKEN_HERE" https://your-site.com/insta-mcp

# Test OAuth discovery (RFC 8414) - if OAuth feature enabled
curl https://your-site.com/.well-known/oauth-authorization-server/insta-mcp
curl https://your-site.com/.well-known/oauth-protected-resource/insta-mcp
curl https://your-site.com/.well-known/jwks.json/insta-mcp
```

## WordPress Integration

### Rewrite Rules

The plugin registers WordPress rewrite rules in `insta_mcp_register_rewrite_rules()`:

```php
// RFC 8414 OAuth discovery
^\.well-known/oauth-authorization-server/insta-mcp → insta_mcp_oauth_meta=authorization-server
^\.well-known/oauth-protected-resource/insta-mcp → insta_mcp_oauth_meta=protected-resource
^\.well-known/jwks\.json/insta-mcp → insta_mcp_oauth_meta=jwks

// MCP endpoint
^insta-mcp → insta_mcp_endpoint=1

// OAuth flows
^insta-mcp/oauth/(authorize|token) → insta_mcp_oauth=$matches[1]
```

**Important:** After modifying rewrite rules, always flush them:
```php
flush_rewrite_rules();
```
Or via WP-CLI: `wp rewrite flush`

### Configuration Management

Configuration is stored in WordPress options (no `config.php` file):

```php
// Get all config as array
$config = insta_mcp_get_config();

// Individual options
get_option('insta_mcp_endpoint_slug', 'insta-mcp');
get_option('insta_mcp_safe_mode', false);
get_option('insta_mcp_oauth_enabled', false);
get_option('insta_mcp_oauth_issuer', home_url('/insta-mcp'));
// ... etc
```

### Database Tables

Created on plugin activation (`install/activate.php`):

```
# User token authentication (always created)
wp_insta_mcp_user_tokens

# OAuth tables (only if OAuth feature enabled)
wp_insta_mcp_oauth_clients
wp_insta_mcp_oauth_authorization_codes
wp_insta_mcp_oauth_access_tokens
wp_insta_mcp_oauth_refresh_tokens
```

Uses `dbDelta()` for safe schema upgrades.

## Authentication

### User Token Authentication (Primary Method)

User tokens provide simple API key authentication tied to WordPress users.

**Features:**
- Tokens stored in `wp_insta_mcp_user_tokens` table
- SHA256 hashed for security
- Per-user token management
- Optional expiration dates
- Last used tracking

**Token Usage:**

```bash
# Method 1: Query parameter (all HTTP methods)
curl "https://your-site.com/insta-mcp?t=YOUR_64_CHAR_TOKEN"
curl -X POST "https://your-site.com/insta-mcp?t=YOUR_64_CHAR_TOKEN" -d '...'

# Method 2: Authorization header (all HTTP methods)
curl -H "Authorization: Bearer YOUR_64_CHAR_TOKEN" https://your-site.com/insta-mcp
```

**Priority:** Query parameter (`?t=`) is checked first, then Authorization header.

**Managing Tokens:**

1. Navigate to Settings → InstaMCP → API Tokens
2. Create new token with label and optional expiration
3. Copy token immediately (only shown once)
4. View all tokens with created/expires/last used dates
5. Revoke tokens when no longer needed

**Default Token:** A default token is automatically created for the user who activates the plugin. For automated deployments, you can pre-configure the token by setting the `instamcp_default_token` option before activation (the option will be automatically deleted after use).

**Token Repository (`src/Auth/TokenRepository.php`):**
- `createToken($user_id, $label, $expires_at)` - Generate new token
- `validateToken($token)` - Validate and return user info
- `getUserTokens($user_id)` - List user's tokens
- `revokeToken($token_id, $user_id)` - Delete token
- `deleteExpiredTokens()` - Cleanup expired tokens

### Authentication Flow

**Authentication is always required** - no open access mode is supported.

**Priority (defined in `src/Auth/AuthenticationManager.php`):**

1. **OAuth 2.1** (if enabled and JWT token present)
   - Validates JWT token
   - Checks token revocation
   - Returns user context with scopes

2. **User Token** (fallback or primary if OAuth disabled)
   - Checks query param `?t=` or `Authorization: Bearer` header (all HTTP methods)
   - Validates against `wp_insta_mcp_user_tokens` table
   - Returns WordPress user context with scopes mapped from roles

**After successful authentication:**
- `wp_set_current_user()` is called with the authenticated user ID
- All WordPress capability checks (edit_posts, publish_posts, etc.) are enforced
- Tools respect BOTH OAuth scopes AND WordPress user capabilities

**If neither authentication succeeds:** Returns 401 Unauthorized with `WWW-Authenticate` header.

**Key Points:**
- Authentication is **always required** (no "none" mode)
- OAuth takes priority if enabled and token is present
- User tokens work regardless of OAuth settings
- Both methods return user context for authorization checks

## OAuth 2.1 Implementation (Optional)

### RFC 8414 Path-Based Discovery

The plugin implements RFC 8414 correctly with path-based discovery:

**Issuer:** `https://your-site.com/insta-mcp`
**Discovery URL:** `https://your-site.com/.well-known/oauth-authorization-server/insta-mcp`

This is handled by WordPress rewrite rules that insert `.well-known` between the host and path component.

### WordPress Role to Scope Mapping

Used by both OAuth and token authentication (defined in `src/OAuth/ScopeRepository.php` and `src/Auth/BearerTokenAuth.php`):

| WordPress Role | OAuth Scopes |
|---------------|-------------|
| Administrator | `mcp:admin`, `mcp:delete`, `mcp:write`, `mcp:read` |
| Editor | `mcp:delete`, `mcp:write`, `mcp:read` |
| Author | `mcp:write`, `mcp:read` |
| Contributor | `mcp:read` |
| Subscriber | `mcp:read` |

**Note:** In addition to scope checks, all tools respect WordPress's built-in capability system (e.g., authors can only edit their own posts).

### WWW-Authenticate Header

The plugin includes `resource_metadata` parameter per RFC 9728:

```
WWW-Authenticate: Bearer realm="MCP Server",
  resource_metadata="https://your-site.com/.well-known/oauth-protected-resource/insta-mcp",
  error="invalid_token"
```

This tells OAuth clients where to discover metadata endpoints.

## Tool Development

### Adding New Tools

1. Create tool class in `src/Tools/{Category}/`
2. Extend `AbstractTool`
3. Implement required methods:
   - `getName()` - Tool identifier
   - `getDescription()` - Tool description
   - `getSchema()` - Validation rules
   - `doExecute()` - Tool logic

4. Register in `includes/endpoints/mcp-http.php`:
   ```php
   $tools = [
       // ... existing tools
       new YourNewTool($wpService, $validationService),
   ];
   ```

### Validation Schema

Use Respect/Validation syntax:

```php
public function getSchema(): array
{
    return [
        'post_id' => ['intType', 'positive'],
        'status' => ['optional', 'in' => ['publish', 'draft']],
        'title' => ['stringType', 'length' => [1, 200]]
    ];
}
```

### Safe Mode

Check if safe mode is enabled for destructive operations:

```php
protected function doExecute(array $parameters): array
{
    $this->checkSafeMode('Delete operation');
    // ... rest of tool logic
}
```

## Admin Settings Page

Located at `admin/settings.php`. Uses WordPress Settings API patterns:

- **General Tab:** Endpoint slug, safe mode
- **OAuth Tab:** OAuth enable/disable, issuer, keys
- **Endpoints Tab:** Read-only URL reference

Form submission is handled with nonce verification:
```php
check_admin_referer('insta_mcp_settings')
```

## Activation & Deactivation

**Activation Hook (`install/activate.php`):**
- Creates database tables via `dbDelta()`
- Sets default options
- Registers rewrite rules
- Flushes rewrite rules

**Deactivation Hook:**
- Flushes rewrite rules (removes custom endpoints)

**Important:** Does NOT delete data on deactivation. Use uninstall hook for cleanup.

## Common Tasks

### Manage User Tokens

**Create Token:**
1. Go to Settings → InstaMCP → API Tokens
2. Enter a label (e.g., "Production Server", "Development")
3. Optionally set expiration in days
4. Click "Create Token"
5. **Copy the token immediately** - it's only shown once!

**Use Token:**
```bash
# Query parameter (GET only)
curl "https://your-site.com/insta-mcp?t=YOUR_TOKEN"

# Authorization header (any method)
curl -H "Authorization: Bearer YOUR_TOKEN" https://your-site.com/insta-mcp
```

**Revoke Token:**
1. Go to Settings → InstaMCP → API Tokens
2. Find token in list
3. Click "Revoke"

**Default Token:**
- Created automatically when plugin is activated
- Check transient immediately after activation
- Labeled "Default Token" with no expiration

**Pre-configure Token (Automated Deployments):**
```bash
# Before activating the plugin, set the token value:
wp option add instamcp_default_token "YOUR_64_CHAR_HEX_TOKEN_HERE"

# Then activate the plugin:
wp plugin activate insta-mcp

# The option will be automatically deleted after the token is created
```

### Change Endpoint Slug

1. Go to Settings → InstaMCP → General
2. Change "Endpoint Slug" field
3. Save (automatically flushes rewrite rules)
4. Update OAuth issuer URL if using OAuth
5. Update any hardcoded token URLs

### Enable OAuth

1. Generate RSA keys:
   ```bash
   openssl genrsa -out oauth-private.key 4096
   openssl rsa -in oauth-private.key -pubout -out oauth-public.key
   chmod 600 oauth-private.key
   ```

2. Upload keys to secure location (outside web root)

3. Configure in Settings → InstaMCP → OAuth:
   - Enable OAuth
   - Set issuer: `https://your-site.com/insta-mcp`
   - Set resource identifier: `https://your-site.com/insta-mcp`
   - Set key paths

4. Register OAuth client (TODO: Add WP-CLI command)

### Debug Rewrite Rules

```bash
# List all rewrite rules
wp rewrite list

# Check if our rules are registered
wp rewrite list | grep insta_mcp

# Flush and regenerate
wp rewrite flush
```

### Test OAuth Discovery

```bash
# Should return 200 with JSON metadata
curl -I https://your-site.com/.well-known/oauth-authorization-server/insta-mcp

# Should include issuer, authorization_endpoint, token_endpoint, etc.
curl https://your-site.com/.well-known/oauth-authorization-server/insta-mcp | jq .
```

## Troubleshooting

**404 on MCP endpoint:**
- Flush rewrite rules: `wp rewrite flush`
- Check permalink structure is not "Plain"
- Verify plugin is activated

**OAuth discovery 404:**
- Ensure rewrite rules are registered and flushed
- Check that slug in URL matches `insta_mcp_endpoint_slug` option
- Verify `.well-known` path is correct (should have plugin slug appended)

**WWW-Authenticate header missing resource_metadata:**
- Check OAuth is enabled in settings
- Verify `src/Auth/OAuthAuthenticator.php` includes resource_metadata parameter
- Ensure issuer URL is configured correctly

**Database tables not created:**
- Check activation hook ran: `wp plugin activate insta-mcp`
- Verify database credentials are correct
- Check `wp_options` table for `insta_mcp_*` options

**Admin settings not saving:**
- Check user has `manage_options` capability
- Verify nonce is valid
- Look for PHP errors in debug.log

**Token authentication failing:**
- Verify token hasn't expired (check Expires column in API Tokens tab)
- Ensure token is correct (64 hex characters)
- For query param: Only works with GET requests (`?t=token`)
- For header: Use `Authorization: Bearer TOKEN` format
- Check `wp_insta_mcp_user_tokens` table exists
- Verify token hash matches in database: `SELECT * FROM wp_insta_mcp_user_tokens WHERE token_hash = SHA2('YOUR_TOKEN', 256)`

**Default token not created on activation:**
- Must activate via WordPress admin (not WP-CLI without user context)
- Check transient: `get_transient('insta_mcp_new_token_' . USER_ID)`
- Manually create token in API Tokens tab

## Key Design Decisions

- **WordPress Rewrite Rules:** Use WordPress routing instead of `.htaccess` for better compatibility and RFC 8414 compliance
- **Options vs Files:** Store configuration in `wp_options` table (no config.php file) for better WordPress integration
- **Token-First Auth:** User tokens are primary authentication method (simpler, per-user), OAuth is optional for advanced use cases
- **No Open Access:** Authentication is always required - no unauthenticated mode for security
- **Query Param Support:** Allow `?t=token` for easy GET request authentication (convenient for simple integrations)
- **SHA256 Token Hashing:** Store hashed tokens (not plain text) for security
- **dbDelta for Tables:** Use WordPress's `dbDelta()` for safe database schema upgrades
- **PSR Standards:** Maintain PSR-3 (logging), PSR-7 (HTTP), PSR-17 (HTTP factories) compatibility
- **Namespaced Tables:** Prefix all tables with `wp_insta_mcp_` to avoid conflicts
- **Admin UI:** Provide user-friendly settings instead of requiring file editing

## Performance Considerations

- **Session Storage:** Sessions stored in `wp-content/insta-mcp-sessions/` (auto-created)
- **Rewrite Rules:** Cached by WordPress, no performance impact after initial load
- **OAuth Token Validation:** JWT validation is stateless (no database queries except revocation check)
- **Database Queries:** All queries use `$wpdb` prepared statements

## Security Best Practices

- **Nonce Verification:** All admin forms use WordPress nonces
- **Capability Checks:** Admin pages check `manage_options` capability
- **Input Sanitization:** All user input sanitized with WordPress functions
- **Output Escaping:** All output escaped with `esc_*()` functions
- **Key Storage:** RSA keys stored outside web root (user configurable)
- **Token Revocation:** Access tokens can be revoked via database

## Testing OAuth Flow

1. **Client connects without token:**
   ```bash
   curl -I https://your-site.com/insta-mcp
   # Should return 401 with WWW-Authenticate header
   ```

2. **Client discovers metadata:**
   ```bash
   curl https://your-site.com/.well-known/oauth-protected-resource/insta-mcp
   # Returns authorization_servers array
   ```

3. **Client gets authorization server metadata:**
   ```bash
   curl https://your-site.com/.well-known/oauth-authorization-server/insta-mcp
   # Returns authorization_endpoint, token_endpoint, etc.
   ```

4. **User authorizes in browser:**
   ```
   https://your-site.com/insta-mcp/oauth/authorize?
     client_id=...&
     redirect_uri=...&
     response_type=code&
     scope=mcp:read+mcp:write
   ```

5. **Client exchanges code for token:**
   ```bash
   curl -X POST https://your-site.com/insta-mcp/oauth/token \
     -d "grant_type=authorization_code" \
     -d "code=..." \
     -d "client_id=..." \
     -d "client_secret=..."
   ```

6. **Client makes authenticated request:**
   ```bash
   curl https://your-site.com/insta-mcp \
     -H "Authorization: Bearer <jwt_token>"
   ```

## Contributing

When making changes:

1. **Update rewrite rules?** Flush rewrite rules after changes
2. **Add new option?** Include in `insta_mcp_get_config()`
3. **Modify database?** Update `install/activate.php` with dbDelta
4. **Add new tool?** Register in `includes/endpoints/mcp-http.php`
5. **Change endpoints?** Update admin settings page documentation

## References

- [MCP Specification](https://modelcontextprotocol.io/specification/2025-06-18/basic/authorization)
- [RFC 8414 - OAuth Authorization Server Metadata](https://datatracker.ietf.org/doc/html/rfc8414)
- [RFC 9728 - OAuth Protected Resource Metadata](https://datatracker.ietf.org/doc/html/rfc9728)
- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [WordPress Rewrite API](https://developer.wordpress.org/apis/rewrite/)
