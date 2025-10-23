# InstaMCP - WordPress MCP Server Plugin

**Model Context Protocol (MCP) server for WordPress with flexible authentication options**

Enable AI assistants like Claude to interact with your WordPress site through a standardized protocol with secure, per-user API tokens or OAuth 2.1 authentication.

## Features

- ✅ **17 MCP Tools** for WordPress content and taxonomy management
- ✅ **User Token Authentication** - Simple API tokens tied to WordPress users (primary method)
- ✅ **OAuth 2.1 Authentication** - Full OAuth flow with JWT tokens (optional, advanced)
- ✅ **RFC 8414 & RFC 9728 Compliant** OAuth discovery
- ✅ **Role-Based Permissions** - Inherit permissions from WordPress user roles
- ✅ **WordPress Capability Checks** - Respects edit_posts, publish_posts, etc.
- ✅ **Safe Mode** - Prevent delete operations for added safety
- ✅ **Multi-User Support** - Admins can create tokens for any user
- ✅ **Token Expiration** - Optional TTL for enhanced security
- ✅ **Admin Settings UI** - Easy token management and configuration
- ✅ **Query Param Auth** - Convenient `?t=token` authentication (all HTTP methods)

## Installation

1. Copy the `insta-mcp` folder to `/wp-content/plugins/`
2. Run `composer install` inside the plugin directory
3. Activate the plugin in WordPress Admin → Plugins
4. Your first API token is automatically created! Check Settings → InstaMCP → API Tokens

### Automated Deployment (Optional)

For automated deployments, you can pre-configure the default token before activation:

```bash
# Set a pre-configured token (must be 64 hex characters)
wp option add instamcp_default_token "YOUR_64_CHAR_HEX_TOKEN_HERE"

# Activate the plugin
wp plugin activate insta-mcp

# The option is automatically deleted after the token is created for security
```

## Quick Start

### Using Token Authentication (Recommended)

**Step 1: Get Your Token**
1. Go to WordPress Admin → Settings → InstaMCP → API Tokens
2. Your default token is shown after activation (copy it!)
3. Or create a new token with a custom label and optional expiration

**Step 2: Connect**

```bash
# Method 1: Query parameter (all HTTP methods)
curl "https://your-site.com/insta-mcp?t=YOUR_64_CHAR_TOKEN"

# Method 2: Authorization header (all HTTP methods)
curl -H "Authorization: Bearer YOUR_64_CHAR_TOKEN" https://your-site.com/insta-mcp
```

**Step 3: Use with Claude Desktop**

Add to `~/Library/Application Support/Claude/claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "wordpress": {
      "command": "npx",
      "args": [
        "mcp-remote",
        "https://your-site.com/insta-mcp?t=YOUR_TOKEN_HERE"
      ]
    }
  }
}
```

## Endpoints

### MCP Endpoint
```
https://your-site.com/insta-mcp
https://your-site.com/insta-mcp?t=TOKEN  (with query param auth)
```

### OAuth Discovery (RFC 8414) - Optional, Advanced Use
```
https://your-site.com/.well-known/oauth-authorization-server/insta-mcp
https://your-site.com/.well-known/oauth-protected-resource/insta-mcp
https://your-site.com/.well-known/jwks.json/insta-mcp
```

### OAuth Flow - Optional, Advanced Use
```
https://your-site.com/insta-mcp/oauth/authorize
https://your-site.com/insta-mcp/oauth/token
```

## Configuration

### API Tokens (Primary Authentication)

**Create Token:**
1. Navigate to Settings → InstaMCP → API Tokens
2. Select user (default: current user)
3. Enter a descriptive label (e.g., "Production Server", "Claude Desktop")
4. Optionally set expiration in days (leave empty for no expiration)
5. Click "Create Token"
6. **Copy the token immediately** - it's only shown once!

**Manage Tokens:**
- View all tokens with user, label, created/expires/last used dates
- Revoke tokens at any time
- Admins can create/revoke tokens for any user

**Token Features:**
- 64-character hex tokens (SHA256 hashed in database)
- Per-user authentication (inherits WordPress role permissions)
- Optional expiration dates
- Last used tracking
- Auto-cleanup of expired tokens

### General Settings
- **Endpoint Slug**: Customize the URL slug (default: `insta-mcp`)
- **Safe Mode**: Prevent delete operations for added protection

### OAuth Settings (Optional, Advanced)
1. Generate RSA keys:
   ```bash
   openssl genrsa -out oauth-private.key 4096
   openssl rsa -in oauth-private.key -pubout -out oauth-public.key
   chmod 600 oauth-private.key
   ```

2. Configure in Settings → InstaMCP:
   - Enable OAuth authentication
   - Set issuer URL (e.g., `https://your-site.com/insta-mcp`)
   - Set RSA key paths

3. Register OAuth clients (via WP-CLI or custom script)

## Permissions & Security

### WordPress Role to Scope Mapping

Tokens inherit permissions from the associated WordPress user's role:

| WordPress Role | OAuth Scopes | Example Capabilities |
|---------------|-------------|---------------------|
| Administrator | `mcp:admin`, `mcp:delete`, `mcp:write`, `mcp:read` | Full access to all content |
| Editor | `mcp:delete`, `mcp:write`, `mcp:read` | Edit/publish any content |
| Author | `mcp:write`, `mcp:read` | Edit/publish own content only |
| Contributor | `mcp:read` | Create drafts only |
| Subscriber | `mcp:read` | Read-only access |

### Dual Permission System

InstaMCP enforces **two layers of permissions**:

1. **OAuth Scopes** - Broad permission categories (read, write, delete, admin)
2. **WordPress Capabilities** - Fine-grained permissions (edit_posts, edit_others_posts, publish_posts, etc.)

**Example:** An Author role user can create and publish content (`mcp:write` scope), but can only edit their **own** posts due to WordPress's `edit_others_posts` capability check.

After successful authentication, `wp_set_current_user()` is called to ensure all WordPress capability checks are enforced.

## Authentication

### Authentication Methods

InstaMCP supports two authentication methods:

1. **User Tokens (Primary, Recommended)**
   - Simple API tokens tied to WordPress users
   - Easy to create and manage via admin UI
   - Supports query parameter (`?t=token`) or header authentication (all HTTP methods)
   - Per-user permissions based on WordPress roles
   - Optional expiration dates
   - Automatically sets WordPress current user for capability checks

2. **OAuth 2.1 (Optional, Advanced)**
   - Full OAuth authorization code flow
   - JWT-based access tokens
   - RFC 8414/9728 compliant discovery
   - Refresh token support
   - Best for third-party integrations

**Authentication is always required** - no unauthenticated access mode.

**Priority:** OAuth (if enabled and JWT present) → User Token → 401 Unauthorized

**Security:** After successful authentication, `wp_set_current_user()` is called with the authenticated user ID, ensuring all WordPress capability checks (edit_posts, publish_posts, delete_posts, etc.) are properly enforced. This means tools respect both OAuth scopes AND WordPress's granular permission system.

## Database Tables

The plugin creates the following tables on activation:

**User Token Authentication (always):**
- `wp_insta_mcp_user_tokens` - User API tokens

**OAuth 2.1 (only if OAuth feature enabled):**
- `wp_insta_mcp_oauth_clients` - Registered OAuth clients
- `wp_insta_mcp_oauth_authorization_codes` - Short-lived authorization codes
- `wp_insta_mcp_oauth_access_tokens` - Token revocation tracking
- `wp_insta_mcp_oauth_refresh_tokens` - Long-lived refresh tokens

## Development

### Requirements
- PHP 8.2+
- WordPress 6.0+
- Composer

### Project Structure
```
insta-mcp/
├── insta-mcp.php           # Main plugin file
├── src/                    # MCP tools and services
├── includes/
│   ├── endpoints/          # MCP HTTP endpoint
│   ├── oauth/              # OAuth authorization/token flows
│   └── well-known/         # OAuth discovery endpoints
├── admin/                  # Admin settings UI
└── install/                # Activation hooks
```

## License

GPL v2 or later

## Credits

Developed by [InstaWP](https://instawp.com/)
