# InstaMCP - WordPress MCP Server Plugin

**Model Context Protocol (MCP) server for WordPress**

Enable AI assistants like Claude to interact with your WordPress site through a standardized protocol with secure, per-user API tokens.

## Features

- ✅ **23 MCP Tools** for WordPress content, taxonomy, plugin, and theme management
- ✅ **Plugin & Theme Management** - Install, activate, update, and manage plugin/theme files
- ✅ **User Token Authentication** - Simple API tokens tied to WordPress users
- ✅ **Role-Based Permissions** - Inherit permissions from WordPress user roles
- ✅ **WordPress Capability Checks** - Respects edit_posts, publish_posts, etc.
- ✅ **Safe Mode** - Prevent delete operations for added safety
- ✅ **Multi-User Support** - Admins can create tokens for any user
- ✅ **Token Expiration** - Optional TTL for enhanced security
- ✅ **Admin Settings UI** - Easy token management and configuration
- ✅ **Query Param Auth** - Convenient `?t=token` authentication (all HTTP methods)
- 🚧 **OAuth 2.1** - Coming soon (currently in development)

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

## Endpoint

The main MCP endpoint for all requests:

```
https://your-site.com/insta-mcp
https://your-site.com/insta-mcp?t=YOUR_TOKEN  (with query param auth)
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

## Permissions & Security

### WordPress Role to Permission Mapping

Tokens inherit permissions from the associated WordPress user's role:

| WordPress Role | Permission Scopes | Example Capabilities |
|---------------|------------------|---------------------|
| Administrator | `mcp:admin`, `mcp:delete`, `mcp:write`, `mcp:read` | Full access to all content |
| Editor | `mcp:delete`, `mcp:write`, `mcp:read` | Edit/publish any content |
| Author | `mcp:write`, `mcp:read` | Edit/publish own content only |
| Contributor | `mcp:read` | Create drafts only |
| Subscriber | `mcp:read` | Read-only access |

### Dual Permission System

InstaMCP enforces **two layers of permissions**:

1. **Permission Scopes** - Broad permission categories (read, write, delete, admin)
2. **WordPress Capabilities** - Fine-grained permissions (edit_posts, edit_others_posts, publish_posts, etc.)

**Example:** An Author role user can create and publish content (`mcp:write` scope), but can only edit their **own** posts due to WordPress's `edit_others_posts` capability check.

After successful authentication, `wp_set_current_user()` is called to ensure all WordPress capability checks are enforced.

## Authentication

### User Token Authentication

InstaMCP uses simple, secure API tokens tied to WordPress users:

- **Easy to create** - Manage tokens via admin UI
- **Flexible authentication** - Use query parameter (`?t=token`) or Authorization header
- **Per-user permissions** - Inherits WordPress role and capabilities
- **Optional expiration** - Set TTL for enhanced security
- **Session tracking** - See when tokens were last used

**Authentication is always required** - no unauthenticated access mode.

**Security:** After successful authentication, `wp_set_current_user()` is called with the authenticated user ID, ensuring all WordPress capability checks (edit_posts, publish_posts, delete_posts, etc.) are properly enforced.

**OAuth 2.1:** Advanced OAuth authentication is currently in development and will be available in a future release.

## Database Tables

The plugin creates the following table on activation:

- `wp_insta_mcp_user_tokens` - Stores user API tokens with SHA256 hashing

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
