# Plugin & Theme Management Tools - Implementation Plan

**Status:** Not Started
**Priority:** High
**Estimated Effort:** ~2000 lines of code
**Decision Date:** 2025-10-23

## Overview

Add **6 consolidated MCP tools** for WordPress plugin and theme management. Each tool handles multiple operations via an `action` parameter to minimize MCP context usage.

**Context Efficiency:** 6 tools instead of 25 separate tools (76% reduction!)

## User Requirements (From Session)

- ✅ Support WordPress.org repository, external URLs, and ZIP uploads
- ✅ No MU-Plugins management (regular plugins only)
- ✅ Include all CRUD operations (search, install, activate, update, delete)
- ✅ Include file editing capabilities
- ✅ Include dependency checks
- ✅ Safe mode blocks ALL modifications (read-only when enabled)
- ❌ No bulk operations (clients can call single operations multiple times)

## Tool Architecture

### Plugin Tools (3 tools)

#### 1. PluginInfo - Discovery & Information
**File:** `src/Tools/Plugin/PluginInfo.php`
**Scope:** `mcp:read`

**Actions:**
- `search` - Search WordPress.org plugin repository
- `list` - List all installed plugins with status
- `get` - Get detailed info about specific plugin

**Parameters:**
```php
[
    'action' => ['required', 'string', ['in' => ['search', 'list', 'get']]],

    // For 'search' action
    'query' => ['optional', 'string'], // Search term
    'page' => ['optional', 'int', ['min' => 1]],
    'per_page' => ['optional', 'int', ['min' => 1, 'max' => 100]],
    'author' => ['optional', 'string'],
    'tag' => ['optional', 'string'],

    // For 'list' action
    'status' => ['optional', 'string', ['in' => ['all', 'active', 'inactive', 'update_available']]],

    // For 'get' action
    'plugin' => ['optional', 'string'], // Plugin slug or path (e.g., 'wordpress-seo' or 'wordpress-seo/wp-seo.php')
]
```

**Example Usage:**
```json
// Search WordPress.org
{
  "action": "search",
  "query": "seo",
  "per_page": 10,
  "page": 1
}

// List installed plugins
{
  "action": "list",
  "status": "active"
}

// Get plugin details
{
  "action": "get",
  "plugin": "wordpress-seo"
}
```

**Response Examples:**
```json
// search response
{
  "success": true,
  "data": {
    "plugins": [
      {
        "name": "Yoast SEO",
        "slug": "wordpress-seo",
        "version": "21.5",
        "author": "Team Yoast",
        "rating": 98,
        "num_ratings": 28500,
        "downloaded": 350000000,
        "short_description": "Improve your WordPress SEO...",
        "requires": "6.3",
        "requires_php": "7.2.5",
        "last_updated": "2024-10-15"
      }
    ],
    "info": {
      "page": 1,
      "pages": 45,
      "results": 450
    }
  }
}

// list response
{
  "success": true,
  "data": {
    "plugins": [
      {
        "name": "Yoast SEO",
        "plugin_file": "wordpress-seo/wp-seo.php",
        "version": "21.5",
        "status": "active",
        "update_available": false,
        "network_active": false,
        "description": "The first true all-in-one SEO solution...",
        "author": "Team Yoast",
        "author_uri": "https://yoast.com"
      }
    ],
    "count": {
      "all": 15,
      "active": 8,
      "inactive": 7,
      "update_available": 2
    }
  }
}

// get response
{
  "success": true,
  "data": {
    "name": "Yoast SEO",
    "plugin_file": "wordpress-seo/wp-seo.php",
    "version": "21.5",
    "status": "active",
    "update_available": false,
    "latest_version": "21.5",
    "description": "...",
    "author": "Team Yoast",
    "plugin_uri": "https://yoast.com/wordpress/plugins/seo/",
    "requires_wp": "6.3",
    "requires_php": "7.2.5",
    "network_only": false
  }
}
```

#### 2. PluginOperations - Installation & Management
**File:** `src/Tools/Plugin/PluginOperations.php`
**Scope:** `mcp:admin`
**Safe Mode:** Blocks all actions

**Actions:**
- `install` - Install from wp.org slug, external URL, or base64 ZIP
- `activate` - Activate a plugin
- `deactivate` - Deactivate a plugin
- `update` - Update to latest version
- `delete` - Delete plugin files

**Parameters:**
```php
[
    'action' => ['required', 'string', ['in' => ['install', 'activate', 'deactivate', 'update', 'delete']]],
    'plugin' => ['required', 'string'], // Plugin slug or plugin file path

    // For 'install' action
    'source' => ['optional', 'string', ['in' => ['wordpress.org', 'url', 'zip']]], // Default: 'wordpress.org'
    'source_data' => ['optional', 'string'], // URL or base64 ZIP content
    'activate_after_install' => ['optional', 'bool'], // Default: false
    'check_dependencies' => ['optional', 'bool'], // Default: true

    // For 'activate' action
    'network_wide' => ['optional', 'bool'], // For multisite, default: false
]
```

**Example Usage:**
```json
// Install from WordPress.org
{
  "action": "install",
  "plugin": "wordpress-seo",
  "source": "wordpress.org",
  "activate_after_install": true,
  "check_dependencies": true
}

// Install from external URL
{
  "action": "install",
  "plugin": "custom-plugin",
  "source": "url",
  "source_data": "https://example.com/my-plugin.zip",
  "activate_after_install": false
}

// Install from ZIP (base64 encoded)
{
  "action": "install",
  "plugin": "premium-plugin",
  "source": "zip",
  "source_data": "UEsDBBQAAAAIAOx7elYAAA...", // base64 ZIP content
  "check_dependencies": true
}

// Activate plugin
{
  "action": "activate",
  "plugin": "wordpress-seo/wp-seo.php"
}

// Deactivate plugin
{
  "action": "deactivate",
  "plugin": "wordpress-seo/wp-seo.php"
}

// Update plugin
{
  "action": "update",
  "plugin": "wordpress-seo/wp-seo.php"
}

// Delete plugin
{
  "action": "delete",
  "plugin": "wordpress-seo"
}
```

**Response Examples:**
```json
// install response
{
  "success": true,
  "data": {
    "plugin": "wordpress-seo",
    "plugin_file": "wordpress-seo/wp-seo.php",
    "version": "21.5",
    "installed": true,
    "activated": true,
    "dependencies_met": true,
    "message": "Plugin installed and activated successfully"
  }
}

// activate response
{
  "success": true,
  "data": {
    "plugin": "wordpress-seo/wp-seo.php",
    "status": "active",
    "message": "Plugin activated successfully"
  }
}

// update response
{
  "success": true,
  "data": {
    "plugin": "wordpress-seo/wp-seo.php",
    "old_version": "21.4",
    "new_version": "21.5",
    "message": "Plugin updated successfully"
  }
}

// delete response
{
  "success": true,
  "data": {
    "plugin": "wordpress-seo",
    "deleted": true,
    "message": "Plugin deleted successfully"
  }
}
```

**Dependency Validation:**
When `check_dependencies: true`:
- Parse plugin headers for "Requires PHP" and "Requires at least" (WP version)
- Check current PHP version and WordPress version
- Return error if requirements not met:
```json
{
  "success": false,
  "error": "Dependency requirements not met",
  "errors": {
    "requires_php": {
      "required": "7.4",
      "current": "7.2",
      "met": false
    },
    "requires_wp": {
      "required": "6.0",
      "current": "5.9",
      "met": false
    }
  }
}
```

#### 3. PluginFiles - File Editing
**File:** `src/Tools/Plugin/PluginFiles.php`
**Scope:** `mcp:admin`
**Safe Mode:** Blocks `write` action

**Actions:**
- `read` - Get plugin file contents
- `write` - Update plugin file contents

**Parameters:**
```php
[
    'action' => ['required', 'string', ['in' => ['read', 'write']]],
    'plugin' => ['required', 'string'], // Plugin slug or directory name
    'file_path' => ['required', 'string'], // Relative path within plugin directory

    // For 'write' action
    'content' => ['optional', 'string'], // New file content
    'backup' => ['optional', 'bool'], // Create backup before write, default: true
]
```

**Security Validations:**
1. Check `DISALLOW_FILE_EDIT` constant
2. Validate file path is within plugin directory (prevent directory traversal)
3. Check file extension whitelist: `.php`, `.css`, `.js`, `.json`, `.txt`, `.md`
4. Ensure plugin exists and is installed
5. Create backup before write operation

**Example Usage:**
```json
// Read file
{
  "action": "read",
  "plugin": "wordpress-seo",
  "file_path": "admin/class-admin.php"
}

// Write file
{
  "action": "write",
  "plugin": "wordpress-seo",
  "file_path": "custom-config.php",
  "content": "<?php\n// Custom configuration\ndefine('MY_OPTION', true);",
  "backup": true
}
```

**Response Examples:**
```json
// read response
{
  "success": true,
  "data": {
    "plugin": "wordpress-seo",
    "file_path": "admin/class-admin.php",
    "content": "<?php\n\nclass WPSEO_Admin {...}",
    "size": 15420,
    "modified": "2024-10-15 14:30:00"
  }
}

// write response
{
  "success": true,
  "data": {
    "plugin": "wordpress-seo",
    "file_path": "custom-config.php",
    "written": true,
    "backup_created": true,
    "backup_path": "wp-content/plugins/wordpress-seo/custom-config.php.backup.20241023143000",
    "message": "File updated successfully"
  }
}
```

### Theme Tools (3 tools)

#### 4. ThemeInfo - Discovery & Information
**File:** `src/Tools/Theme/ThemeInfo.php`
**Scope:** `mcp:read`

**Actions:**
- `search` - Search WordPress.org theme repository
- `list` - List all installed themes with status
- `get` - Get detailed info about specific theme

**Parameters:**
```php
[
    'action' => ['required', 'string', ['in' => ['search', 'list', 'get']]],

    // For 'search' action
    'query' => ['optional', 'string'],
    'page' => ['optional', 'int', ['min' => 1]],
    'per_page' => ['optional', 'int', ['min' => 1, 'max' => 100]],
    'tag' => ['optional', 'string'],

    // For 'get' action
    'theme' => ['optional', 'string'], // Theme slug or directory name
]
```

**Example Usage:**
```json
// Search WordPress.org
{
  "action": "search",
  "query": "minimal",
  "per_page": 10
}

// List installed themes
{
  "action": "list"
}

// Get theme details
{
  "action": "get",
  "theme": "twentytwentyfour"
}
```

#### 5. ThemeOperations - Installation & Management
**File:** `src/Tools/Theme/ThemeOperations.php`
**Scope:** `mcp:admin`
**Safe Mode:** Blocks all actions

**Actions:**
- `install` - Install from wp.org slug, external URL, or base64 ZIP
- `activate` - Switch to theme (supports child theme parent auto-install)
- `update` - Update to latest version
- `delete` - Delete theme files (cannot delete active theme)

**Parameters:**
```php
[
    'action' => ['required', 'string', ['in' => ['install', 'activate', 'update', 'delete']]],
    'theme' => ['required', 'string'], // Theme slug or directory name

    // For 'install' action
    'source' => ['optional', 'string', ['in' => ['wordpress.org', 'url', 'zip']]],
    'source_data' => ['optional', 'string'],
    'activate_after_install' => ['optional', 'bool'],
    'check_dependencies' => ['optional', 'bool'],
]
```

**Example Usage:**
```json
// Install from WordPress.org
{
  "action": "install",
  "theme": "twentytwentyfour",
  "source": "wordpress.org",
  "activate_after_install": true
}

// Install from URL
{
  "action": "install",
  "theme": "custom-theme",
  "source": "url",
  "source_data": "https://example.com/theme.zip"
}

// Activate theme
{
  "action": "activate",
  "theme": "twentytwentyfour"
}

// Update theme
{
  "action": "update",
  "theme": "twentytwentyfour"
}

// Delete theme (must not be active)
{
  "action": "delete",
  "theme": "twentytwentythree"
}
```

#### 6. ThemeFiles - File Editing
**File:** `src/Tools/Theme/ThemeFiles.php`
**Scope:** `mcp:admin`
**Safe Mode:** Blocks `write` action

**Actions:**
- `read` - Get theme file contents
- `write` - Update theme file contents

**Parameters:**
```php
[
    'action' => ['required', 'string', ['in' => ['read', 'write']]],
    'theme' => ['required', 'string'], // Theme slug or directory name
    'file_path' => ['required', 'string'], // Relative path within theme directory
    'content' => ['optional', 'string'], // For 'write' action
    'backup' => ['optional', 'bool'], // Default: true
]
```

**Security:** Same as PluginFiles + optional block for active theme's functions.php

## WordPressService Extensions

Add to `src/Services/WordPressService.php`:

### Plugin Methods (~15 methods)

```php
// Plugin Info
public function getPlugins(): array
public function getPluginData(string $pluginFile): array
public function isPluginActive(string $pluginFile): bool
public function isPluginInactive(string $pluginFile): bool

// Plugin Operations
public function activatePlugin(string $pluginFile, bool $networkWide = false): bool|\WP_Error
public function deactivatePlugin(string $pluginFile): void
public function validatePlugin(string $pluginFile): bool|\WP_Error
public function deletePlugin(string $pluginFile): bool|\WP_Error

// WordPress.org API
public function searchPluginsApi(array $args): object|\WP_Error
public function pluginsApiInfo(string $slug): object|\WP_Error

// Installation
public function installPluginFromSlug(string $slug): bool|\WP_Error
public function installPluginFromUrl(string $url): bool|\WP_Error
public function installPluginFromZip(string $base64Content): bool|\WP_Error
public function upgradePlugin(string $pluginFile): bool|\WP_Error

// File Operations
public function validatePluginFilePath(string $plugin, string $filePath): bool
public function readPluginFile(string $plugin, string $filePath): string|\WP_Error
public function writePluginFile(string $plugin, string $filePath, string $content, bool $backup = true): bool|\WP_Error
```

### Theme Methods (~12 methods)

```php
// Theme Info
public function getThemes(): array
public function getTheme(string $stylesheet): \WP_Theme|null
public function getCurrentTheme(): \WP_Theme

// Theme Operations
public function switchTheme(string $stylesheet): void
public function deleteTheme(string $stylesheet): bool|\WP_Error

// WordPress.org API
public function searchThemesApi(array $args): object|\WP_Error
public function themesApiInfo(string $slug): object|\WP_Error

// Installation
public function installThemeFromSlug(string $slug): bool|\WP_Error
public function installThemeFromUrl(string $url): bool|\WP_Error
public function installThemeFromZip(string $base64Content): bool|\WP_Error
public function upgradeTheme(string $stylesheet): bool|\WP_Error

// File Operations
public function validateThemeFilePath(string $theme, string $filePath): bool
public function readThemeFile(string $theme, string $filePath): string|\WP_Error
public function writeThemeFile(string $theme, string $filePath, string $content, bool $backup = true): bool|\WP_Error
```

## Implementation Details

### Safe Mode Behavior

When `get_option('insta_mcp_safe_mode')` is true:
- **PluginOperations**: All actions blocked → throw `ToolException::safeModeViolation()`
- **PluginFiles**: `write` action blocked
- **ThemeOperations**: All actions blocked
- **ThemeFiles**: `write` action blocked
- **Info tools**: All actions allowed (read-only)

### File Editing Security

```php
protected function validateFilePath(string $baseDir, string $filePath): void
{
    // 1. Check DISALLOW_FILE_EDIT constant
    if (defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT) {
        throw ToolException::forbidden('File editing is disabled');
    }

    // 2. Prevent directory traversal
    $realBase = realpath($baseDir);
    $realPath = realpath($baseDir . '/' . $filePath);

    if (!$realPath || strpos($realPath, $realBase) !== 0) {
        throw ToolException::invalidInput('Invalid file path');
    }

    // 3. Extension whitelist
    $allowedExtensions = ['php', 'css', 'js', 'json', 'txt', 'md'];
    $ext = pathinfo($filePath, PATHINFO_EXTENSION);

    if (!in_array($ext, $allowedExtensions)) {
        throw ToolException::invalidInput('File type not allowed');
    }
}
```

### Dependency Checking

```php
protected function checkDependencies(array $pluginData): array
{
    $requiresPhp = $pluginData['RequiresPHP'] ?? '';
    $requiresWp = $pluginData['RequiresWP'] ?? '';

    $result = [
        'met' => true,
        'php' => [
            'required' => $requiresPhp,
            'current' => PHP_VERSION,
            'met' => empty($requiresPhp) || version_compare(PHP_VERSION, $requiresPhp, '>=')
        ],
        'wordpress' => [
            'required' => $requiresWp,
            'current' => get_bloginfo('version'),
            'met' => empty($requiresWp) || version_compare(get_bloginfo('version'), $requiresWp, '>=')
        ]
    ];

    $result['met'] = $result['php']['met'] && $result['wordpress']['met'];

    return $result;
}
```

### ZIP Installation

```php
protected function installFromZip(string $base64Content, string $type = 'plugin'): bool|\WP_Error
{
    // 1. Decode base64
    $zipContent = base64_decode($base64Content);
    if ($zipContent === false) {
        return new \WP_Error('invalid_zip', 'Invalid base64 content');
    }

    // 2. Save to temp file
    $tempFile = wp_tempnam('upload.zip');
    file_put_contents($tempFile, $zipContent);

    // 3. Use WordPress upgrader
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

    $upgrader = $type === 'plugin'
        ? new \Plugin_Upgrader()
        : new \Theme_Upgrader();

    $result = $upgrader->install($tempFile);

    // 4. Cleanup
    @unlink($tempFile);

    return $result;
}
```

## Files to Create

### Plugin Tools (3 files)
1. `src/Tools/Plugin/PluginInfo.php`
2. `src/Tools/Plugin/PluginOperations.php`
3. `src/Tools/Plugin/PluginFiles.php`

### Theme Tools (3 files)
4. `src/Tools/Theme/ThemeInfo.php`
5. `src/Tools/Theme/ThemeOperations.php`
6. `src/Tools/Theme/ThemeFiles.php`

### Tests (6 files)
- `tests/Unit/Tools/Plugin/PluginInfoTest.php`
- `tests/Unit/Tools/Plugin/PluginOperationsTest.php`
- `tests/Unit/Tools/Plugin/PluginFilesTest.php`
- `tests/Unit/Tools/Theme/ThemeInfoTest.php`
- `tests/Unit/Tools/Theme/ThemeOperationsTest.php`
- `tests/Unit/Tools/Theme/ThemeFilesTest.php`

## Files to Modify

1. **`includes/endpoints/mcp-http.php`**
   - Register 6 new tools
   - Add to `$tools` array after taxonomy tools

2. **`src/Services/WordPressService.php`**
   - Add ~27 new methods
   - Group by category (Plugin Info, Plugin Ops, Theme Info, Theme Ops, File Ops)

3. **`CLAUDE.md`**
   - Add "Plugin Management" section
   - Add "Theme Management" section
   - Document action-based routing pattern

4. **`README.md`**
   - Update feature count: "23 MCP Tools" (was 17)
   - Add Plugin & Theme management to features list

5. **`tests/inspector-tests.sh`**
   - Add plugin search test
   - Add theme list test
   - Add plugin install/activate/delete test

## Testing Requirements

### Unit Tests
Each tool needs tests for:
- All actions (search, list, get, install, activate, etc.)
- Parameter validation
- Safe mode blocking
- Scope authorization (mcp:read vs mcp:admin)
- Error handling (WP_Error cases)
- Dependency validation
- File path validation

### Integration Tests
Add to `tests/inspector-tests.sh`:
```bash
# Plugin management
run_test "Search plugins" \
    "$INSPECTOR --method tools/call --tool-name plugin_info --tool-arg action=search --tool-arg query=seo"

run_test "List plugins" \
    "$INSPECTOR --method tools/call --tool-name plugin_info --tool-arg action=list"

run_test "Install plugin" \
    "$INSPECTOR --method tools/call --tool-name plugin_operations --tool-arg action=install --tool-arg plugin=hello-dolly"

run_test "Activate plugin" \
    "$INSPECTOR --method tools/call --tool-name plugin_operations --tool-arg action=activate --tool-arg plugin=hello-dolly/hello.php"

run_test "Deactivate plugin" \
    "$INSPECTOR --method tools/call --tool-name plugin_operations --tool-arg action=deactivate --tool-arg plugin=hello-dolly/hello.php"

run_test "Delete plugin" \
    "$INSPECTOR --method tools/call --tool-name plugin_operations --tool-arg action=delete --tool-arg plugin=hello-dolly"

# Theme management
run_test "List themes" \
    "$INSPECTOR --method tools/call --tool-name theme_info --tool-arg action=list"
```

## Error Handling

All tools should return consistent error format:

```json
{
  "success": false,
  "error": "Error message",
  "errors": {
    "field_name": "Specific error",
    "another_field": "Another error"
  }
}
```

Common error scenarios:
- Plugin/theme not found
- Plugin/theme already installed
- Cannot delete active theme
- Dependency requirements not met
- File editing disabled (DISALLOW_FILE_EDIT)
- Invalid file path (directory traversal)
- File type not allowed
- Safe mode violation
- Insufficient permissions
- Network activation on non-multisite

## Performance Considerations

- **WordPress.org API**: Results should be cached (WordPress handles this)
- **File operations**: Read operations are fast, write operations create backups
- **Installation**: Downloading from external URLs can be slow - consider timeout handling
- **ZIP uploads**: Base64 decoding and extraction can use memory - validate ZIP size

## Security Checklist

- [ ] All operations require `mcp:admin` scope (except Info tools)
- [ ] Safe mode blocks all modifications
- [ ] File paths validated against directory traversal
- [ ] File extensions whitelisted
- [ ] DISALLOW_FILE_EDIT constant respected
- [ ] Cannot delete active theme
- [ ] WordPress capability checks via `wp_set_current_user()`
- [ ] ZIP files validated before extraction
- [ ] External URLs validated (https only for production)
- [ ] Backup created before file writes

## Implementation Priority

1. **Phase 1:** PluginInfo, ThemeInfo (read-only, safest)
2. **Phase 2:** PluginOperations, ThemeOperations (core functionality)
3. **Phase 3:** PluginFiles, ThemeFiles (most risky, implement last)
4. **Phase 4:** Integration tests and documentation

## Future Enhancements (Not in Scope)

- Bulk operations (can be added later without new tools - just accept arrays)
- MU-Plugins management
- Plugin/theme auto-update configuration
- Rollback to previous version
- Plugin/theme conflict detection
- Performance impact analysis
- Translation file management

---

**Ready to Implement:** ✅ All requirements documented
**Next Steps:**
1. Create Plugin/Theme tool directories
2. Implement PluginInfo (simplest, read-only)
3. Add WordPressService methods incrementally
4. Write unit tests alongside
5. Update registration in mcp-http.php
6. Add integration tests
7. Update documentation
