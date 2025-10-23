<?php
/**
 * Admin Settings Page
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check user permissions
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'insta-mcp'));
}

// Load TokenRepository
require_once INSTA_MCP_PLUGIN_DIR . 'src/Auth/TokenRepository.php';
$tokenRepo = new \InstaWP\MCP\PHP\Auth\TokenRepository();

// Handle token creation
if (isset($_POST['insta_mcp_create_token']) && check_admin_referer('insta_mcp_create_token')) {
    $label = sanitize_text_field($_POST['token_label']);
    $user_id = isset($_POST['token_user_id']) ? (int)$_POST['token_user_id'] : get_current_user_id();
    $expires_days = isset($_POST['token_expires_days']) && $_POST['token_expires_days'] !== ''
        ? (int)$_POST['token_expires_days']
        : null;

    // Verify the user exists
    if (!get_userdata($user_id)) {
        echo '<div class="notice notice-error"><p>' . __('Invalid user selected.', 'insta-mcp') . '</p></div>';
    } else {
        $expires_at = null;
        if ($expires_days !== null && $expires_days > 0) {
            $expires_at = date('Y-m-d H:i:s', strtotime("+{$expires_days} days"));
        }

        $result = $tokenRepo->createToken($user_id, $label, $expires_at);

        if ($result['success']) {
            // Store token temporarily for display
            set_transient('insta_mcp_new_token_' . get_current_user_id(), $result['token'], 300);
            echo '<div class="notice notice-success"><p>' . __('Token created successfully! Copy it now - it won\'t be shown again.', 'insta-mcp') . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html($result['error']) . '</p></div>';
        }
    }
}

// Handle token revocation
if (isset($_POST['insta_mcp_revoke_token']) && check_admin_referer('insta_mcp_revoke_token')) {
    $token_id = (int)$_POST['token_id'];

    // Admin can revoke any token, so we need to get the token's user_id first
    global $wpdb;
    $table_name = $wpdb->prefix . 'insta_mcp_user_tokens';
    $token_user_id = $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM `{$table_name}` WHERE id = %d",
        $token_id
    ));

    if ($token_user_id) {
        $success = $tokenRepo->revokeToken($token_id, $token_user_id);
        if ($success) {
            echo '<div class="notice notice-success"><p>' . __('Token revoked successfully!', 'insta-mcp') . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . __('Failed to revoke token.', 'insta-mcp') . '</p></div>';
        }
    } else {
        echo '<div class="notice notice-error"><p>' . __('Token not found.', 'insta-mcp') . '</p></div>';
    }
}

// Handle form submission
if (isset($_POST['insta_mcp_save_settings']) && check_admin_referer('insta_mcp_settings')) {
    // Save general settings
    update_option('insta_mcp_endpoint_slug', sanitize_text_field($_POST['endpoint_slug']));
    update_option('insta_mcp_safe_mode', isset($_POST['safe_mode']) ? 1 : 0);

    // Save OAuth settings
    update_option('insta_mcp_oauth_enabled', isset($_POST['oauth_enabled']) ? 1 : 0);
    update_option('insta_mcp_oauth_issuer', esc_url_raw($_POST['oauth_issuer']));
    update_option('insta_mcp_oauth_resource_identifier', esc_url_raw($_POST['oauth_resource_identifier']));
    update_option('insta_mcp_oauth_private_key_path', sanitize_text_field($_POST['oauth_private_key_path']));
    update_option('insta_mcp_oauth_public_key_path', sanitize_text_field($_POST['oauth_public_key_path']));

    // Flush rewrite rules when slug changes
    flush_rewrite_rules();

    echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', 'insta-mcp') . '</p></div>';
}

// Get current settings
$endpoint_slug = get_option('insta_mcp_endpoint_slug', 'insta-mcp');
$safe_mode = get_option('insta_mcp_safe_mode', false);
$oauth_enabled = get_option('insta_mcp_oauth_enabled', false);
$oauth_issuer = get_option('insta_mcp_oauth_issuer', home_url('/insta-mcp'));
$oauth_resource_identifier = get_option('insta_mcp_oauth_resource_identifier', home_url('/insta-mcp'));
$oauth_private_key_path = get_option('insta_mcp_oauth_private_key_path', '');
$oauth_public_key_path = get_option('insta_mcp_oauth_public_key_path', '');

?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <h2 class="nav-tab-wrapper">
        <a href="#general" class="nav-tab nav-tab-active"><?php _e('General', 'insta-mcp'); ?></a>
        <a href="#tokens" class="nav-tab"><?php _e('API Tokens', 'insta-mcp'); ?></a>
        <?php if (INSTA_MCP_OAUTH_FEATURE_ENABLED): ?>
            <a href="#oauth" class="nav-tab"><?php _e('OAuth', 'insta-mcp'); ?></a>
        <?php endif; ?>
        <a href="#endpoints" class="nav-tab"><?php _e('Endpoints', 'insta-mcp'); ?></a>
    </h2>

    <form method="post" action="">
        <?php wp_nonce_field('insta_mcp_settings'); ?>

        <!-- General Settings -->
        <div id="general-settings" class="tab-content">
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="endpoint_slug"><?php _e('Endpoint Slug', 'insta-mcp'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="endpoint_slug" name="endpoint_slug"
                               value="<?php echo esc_attr($endpoint_slug); ?>" class="regular-text" />
                        <p class="description">
                            <?php printf(__('The URL slug for the MCP endpoint. Current: %s', 'insta-mcp'),
                                '<code>' . home_url('/' . $endpoint_slug) . '</code>'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Safe Mode', 'insta-mcp'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="safe_mode" value="1" <?php checked($safe_mode, 1); ?> />
                            <?php _e('Enable safe mode (prevents delete operations)', 'insta-mcp'); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <!-- API Tokens -->
        <div id="tokens-settings" class="tab-content" style="display:none;">
            <?php
            // Display newly created token if available
            $new_token = get_transient('insta_mcp_new_token_' . get_current_user_id());
            if ($new_token) {
                delete_transient('insta_mcp_new_token_' . get_current_user_id());
                ?>
                <div class="notice notice-info">
                    <p><strong><?php _e('Your new token:', 'insta-mcp'); ?></strong></p>
                    <p>
                        <code id="new-token-display" style="font-size: 14px; padding: 10px; background: #f5f5f5; display: inline-block; word-break: break-all;">
                            <?php echo esc_html($new_token); ?>
                        </code>
                        <button type="button" class="button" onclick="copyToken('<?php echo esc_js($new_token); ?>')">
                            <?php _e('Copy', 'insta-mcp'); ?>
                        </button>
                    </p>
                    <p class="description">
                        <?php _e('Save this token now! It will not be shown again.', 'insta-mcp'); ?><br>
                        <?php printf(
                            __('Usage: %s or %s', 'insta-mcp'),
                            '<code>' . home_url('/' . $endpoint_slug . '?t=' . $new_token) . '</code>',
                            '<code>Authorization: Bearer ' . $new_token . '</code>'
                        ); ?>
                    </p>
                </div>
                <?php
            }
            ?>

            <h3><?php _e('Create New Token', 'insta-mcp'); ?></h3>
            <form method="post" action="" style="margin-bottom: 30px;">
                <?php wp_nonce_field('insta_mcp_create_token'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="token_user_id"><?php _e('User', 'insta-mcp'); ?></label>
                        </th>
                        <td>
                            <?php
                            wp_dropdown_users([
                                'name' => 'token_user_id',
                                'id' => 'token_user_id',
                                'selected' => get_current_user_id(),
                                'show_option_none' => __('Select User', 'insta-mcp'),
                                'option_none_value' => '',
                            ]);
                            ?>
                            <p class="description"><?php _e('Which user this token belongs to', 'insta-mcp'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="token_label"><?php _e('Token Label', 'insta-mcp'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="token_label" name="token_label" class="regular-text" required />
                            <p class="description"><?php _e('A friendly name to identify this token', 'insta-mcp'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="token_expires_days"><?php _e('Expires In (days)', 'insta-mcp'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="token_expires_days" name="token_expires_days" class="small-text" min="1" />
                            <p class="description"><?php _e('Leave empty for no expiration', 'insta-mcp'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Create Token', 'insta-mcp'), 'secondary', 'insta_mcp_create_token'); ?>
            </form>

            <h3><?php _e('All Tokens', 'insta-mcp'); ?></h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('User', 'insta-mcp'); ?></th>
                        <th><?php _e('Label', 'insta-mcp'); ?></th>
                        <th><?php _e('Created', 'insta-mcp'); ?></th>
                        <th><?php _e('Expires', 'insta-mcp'); ?></th>
                        <th><?php _e('Last Used', 'insta-mcp'); ?></th>
                        <th><?php _e('Actions', 'insta-mcp'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Get all tokens (admin can see all)
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'insta_mcp_user_tokens';
                    $tokens = $wpdb->get_results(
                        "SELECT * FROM `{$table_name}` ORDER BY created_at DESC",
                        ARRAY_A
                    );
                    if (empty($tokens)) {
                        echo '<tr><td colspan="6">' . __('No tokens found.', 'insta-mcp') . '</td></tr>';
                    } else {
                        foreach ($tokens as $token) {
                            $is_expired = $token['expires_at'] && strtotime($token['expires_at']) < time();
                            $user = get_userdata($token['user_id']);
                            ?>
                            <tr<?php if ($is_expired) echo ' style="opacity: 0.6;"'; ?>>
                                <td>
                                    <?php
                                    if ($user) {
                                        echo esc_html($user->user_login);
                                        echo ' <span style="color: #666;">(' . esc_html($user->display_name) . ')</span>';
                                    } else {
                                        echo '<span style="color: #dc3232;">' . __('User deleted', 'insta-mcp') . '</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php echo esc_html($token['label']); ?>
                                    <?php if ($is_expired) echo '<span style="color: #dc3232;"> (Expired)</span>'; ?>
                                </td>
                                <td><?php echo esc_html(date('Y-m-d H:i', strtotime($token['created_at']))); ?></td>
                                <td>
                                    <?php
                                    if ($token['expires_at']) {
                                        echo esc_html(date('Y-m-d H:i', strtotime($token['expires_at'])));
                                    } else {
                                        echo __('Never', 'insta-mcp');
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    if ($token['last_used_at']) {
                                        echo esc_html(date('Y-m-d H:i', strtotime($token['last_used_at'])));
                                    } else {
                                        echo __('Never', 'insta-mcp');
                                    }
                                    ?>
                                </td>
                                <td>
                                    <button type="button" class="button button-small" onclick="showTokenUsage('<?php echo esc_js($endpoint_slug); ?>')">
                                        <?php _e('Usage', 'insta-mcp'); ?>
                                    </button>
                                    <form method="post" action="" style="display: inline; margin-left: 5px;">
                                        <?php wp_nonce_field('insta_mcp_revoke_token'); ?>
                                        <input type="hidden" name="token_id" value="<?php echo esc_attr($token['id']); ?>" />
                                        <button type="submit" name="insta_mcp_revoke_token" class="button button-small"
                                                onclick="return confirm('<?php esc_attr_e('Are you sure you want to revoke this token?', 'insta-mcp'); ?>');">
                                            <?php _e('Revoke', 'insta-mcp'); ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- OAuth Settings -->
        <?php if (INSTA_MCP_OAUTH_FEATURE_ENABLED): ?>
        <div id="oauth-settings" class="tab-content" style="display:none;">
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php _e('Enable OAuth', 'insta-mcp'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="oauth_enabled" value="1" <?php checked($oauth_enabled, 1); ?> />
                            <?php _e('Enable OAuth 2.1 authentication', 'insta-mcp'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="oauth_issuer"><?php _e('OAuth Issuer', 'insta-mcp'); ?></label>
                    </th>
                    <td>
                        <input type="url" id="oauth_issuer" name="oauth_issuer"
                               value="<?php echo esc_url($oauth_issuer); ?>" class="regular-text" />
                        <p class="description">
                            <?php _e('The OAuth issuer URL (must match where .well-known is accessible)', 'insta-mcp'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="oauth_resource_identifier"><?php _e('Resource Identifier', 'insta-mcp'); ?></label>
                    </th>
                    <td>
                        <input type="url" id="oauth_resource_identifier" name="oauth_resource_identifier"
                               value="<?php echo esc_url($oauth_resource_identifier); ?>" class="regular-text" />
                        <p class="description">
                            <?php _e('The resource identifier URL (typically same as issuer)', 'insta-mcp'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="oauth_private_key_path"><?php _e('Private Key Path', 'insta-mcp'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="oauth_private_key_path" name="oauth_private_key_path"
                               value="<?php echo esc_attr($oauth_private_key_path); ?>" class="regular-text" />
                        <p class="description">
                            <?php _e('Absolute path to RSA private key file', 'insta-mcp'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="oauth_public_key_path"><?php _e('Public Key Path', 'insta-mcp'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="oauth_public_key_path" name="oauth_public_key_path"
                               value="<?php echo esc_attr($oauth_public_key_path); ?>" class="regular-text" />
                        <p class="description">
                            <?php _e('Absolute path to RSA public key file', 'insta-mcp'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        <?php endif; ?>

        <!-- Endpoints Info -->
        <div id="endpoints-info" class="tab-content" style="display:none;">
            <h2><?php _e('Getting Started', 'insta-mcp'); ?></h2>
            <p><?php _e('To use InstaMCP, you need an API token. Go to the', 'insta-mcp'); ?> <a href="#tokens" class="insta-mcp-tab-link"><?php _e('API Tokens tab', 'insta-mcp'); ?></a> <?php _e('to create or view your tokens.', 'insta-mcp'); ?></p>

            <h3><?php _e('Main MCP Endpoint', 'insta-mcp'); ?></h3>
            <p><?php _e('This is the primary endpoint for all MCP requests:', 'insta-mcp'); ?></p>
            <div class="endpoint-box">
                <code id="mcp-endpoint-url"><?php echo home_url('/' . $endpoint_slug); ?></code>
                <button class="button button-small" onclick="copyEndpointUrl('mcp-endpoint-url')" style="margin-left: 10px;">
                    <?php _e('Copy URL', 'insta-mcp'); ?>
                </button>
            </div>

            <h3><?php _e('Token Authentication Examples', 'insta-mcp'); ?></h3>

            <h4><?php _e('Method 1: Query Parameter (All HTTP methods)', 'insta-mcp'); ?></h4>
            <p><?php _e('Append your token to the URL as a query parameter:', 'insta-mcp'); ?></p>
            <div class="endpoint-box">
                <code id="query-param-example"><?php echo home_url('/' . $endpoint_slug . '?t=YOUR_TOKEN_HERE'); ?></code>
                <button class="button button-small" onclick="copyEndpointUrl('query-param-example')" style="margin-left: 10px;">
                    <?php _e('Copy Example', 'insta-mcp'); ?>
                </button>
            </div>
            <p class="description">
                <strong><?php _e('Pros:', 'insta-mcp'); ?></strong> <?php _e('Simple to use, works with all HTTP methods, perfect for tools like Claude Desktop', 'insta-mcp'); ?><br>
                <strong><?php _e('Cons:', 'insta-mcp'); ?></strong> <?php _e('Token visible in URL logs', 'insta-mcp'); ?>
            </p>

            <h4><?php _e('Method 2: Authorization Header (All HTTP methods)', 'insta-mcp'); ?></h4>
            <p><?php _e('Include token in the Authorization header:', 'insta-mcp'); ?></p>
            <div class="endpoint-box">
                <code>curl -H "Authorization: Bearer YOUR_TOKEN_HERE" <?php echo home_url('/' . $endpoint_slug); ?></code>
            </div>
            <p class="description">
                <strong><?php _e('Pros:', 'insta-mcp'); ?></strong> <?php _e('Works with all HTTP methods, more secure (not in URL)', 'insta-mcp'); ?><br>
                <strong><?php _e('Cons:', 'insta-mcp'); ?></strong> <?php _e('Requires setting custom headers', 'insta-mcp'); ?>
            </p>

            <h3><?php _e('Using with Claude Desktop', 'insta-mcp'); ?></h3>
            <p><?php _e('Add this to your Claude Desktop configuration file:', 'insta-mcp'); ?></p>
            <div class="endpoint-box">
                <pre id="claude-config" style="background: #f5f5f5; padding: 15px; overflow-x: auto;">{
  "mcpServers": {
    "wordpress": {
      "command": "npx",
      "args": [
        "mcp-remote",
        "<?php echo home_url('/' . $endpoint_slug . '?t=YOUR_TOKEN_HERE'); ?>"
      ]
    }
  }
}</pre>
                <button class="button button-small" onclick="copyEndpointUrl('claude-config')" style="margin-top: 10px;">
                    <?php _e('Copy Config', 'insta-mcp'); ?>
                </button>
            </div>
            <p class="description">
                <strong><?php _e('Location:', 'insta-mcp'); ?></strong>
                <br><?php _e('macOS:', 'insta-mcp'); ?> <code>~/Library/Application Support/Claude/claude_desktop_config.json</code>
                <br><?php _e('Windows:', 'insta-mcp'); ?> <code>%APPDATA%\Claude\claude_desktop_config.json</code>
            </p>

            <?php if (INSTA_MCP_OAUTH_FEATURE_ENABLED && $oauth_enabled): ?>
            <hr style="margin: 30px 0;">
            <h2><?php _e('OAuth 2.1 Endpoints (Advanced)', 'insta-mcp'); ?></h2>
            <p><?php _e('For third-party integrations and advanced use cases. Most users should use API tokens instead.', 'insta-mcp'); ?></p>

            <h3><?php _e('OAuth Discovery (RFC 8414/9728)', 'insta-mcp'); ?></h3>
            <table class="widefat" style="margin-top: 15px;">
                <thead>
                    <tr>
                        <th><?php _e('Type', 'insta-mcp'); ?></th>
                        <th><?php _e('URL', 'insta-mcp'); ?></th>
                        <th><?php _e('Action', 'insta-mcp'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php _e('Authorization Server Metadata', 'insta-mcp'); ?></td>
                        <td><code id="oauth-as-url"><?php echo home_url('/.well-known/oauth-authorization-server/' . $endpoint_slug); ?></code></td>
                        <td><button class="button button-small" onclick="copyEndpointUrl('oauth-as-url')"><?php _e('Copy', 'insta-mcp'); ?></button></td>
                    </tr>
                    <tr>
                        <td><?php _e('Protected Resource Metadata', 'insta-mcp'); ?></td>
                        <td><code id="oauth-pr-url"><?php echo home_url('/.well-known/oauth-protected-resource/' . $endpoint_slug); ?></code></td>
                        <td><button class="button button-small" onclick="copyEndpointUrl('oauth-pr-url')"><?php _e('Copy', 'insta-mcp'); ?></button></td>
                    </tr>
                    <tr>
                        <td><?php _e('JWKS (Public Keys)', 'insta-mcp'); ?></td>
                        <td><code id="oauth-jwks-url"><?php echo home_url('/.well-known/jwks.json/' . $endpoint_slug); ?></code></td>
                        <td><button class="button button-small" onclick="copyEndpointUrl('oauth-jwks-url')"><?php _e('Copy', 'insta-mcp'); ?></button></td>
                    </tr>
                </tbody>
            </table>

            <h3><?php _e('OAuth Flow Endpoints', 'insta-mcp'); ?></h3>
            <table class="widefat" style="margin-top: 15px;">
                <thead>
                    <tr>
                        <th><?php _e('Endpoint', 'insta-mcp'); ?></th>
                        <th><?php _e('URL', 'insta-mcp'); ?></th>
                        <th><?php _e('Action', 'insta-mcp'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php _e('Authorization', 'insta-mcp'); ?></td>
                        <td><code id="oauth-auth-url"><?php echo home_url('/' . $endpoint_slug . '/oauth/authorize'); ?></code></td>
                        <td><button class="button button-small" onclick="copyEndpointUrl('oauth-auth-url')"><?php _e('Copy', 'insta-mcp'); ?></button></td>
                    </tr>
                    <tr>
                        <td><?php _e('Token', 'insta-mcp'); ?></td>
                        <td><code id="oauth-token-url"><?php echo home_url('/' . $endpoint_slug . '/oauth/token'); ?></code></td>
                        <td><button class="button button-small" onclick="copyEndpointUrl('oauth-token-url')"><?php _e('Copy', 'insta-mcp'); ?></button></td>
                    </tr>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <?php submit_button(__('Save Settings', 'insta-mcp'), 'primary', 'insta_mcp_save_settings'); ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Tab switching
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        $('.tab-content').hide();

        var target = $(this).attr('href').substring(1);
        $('#' + target + '-settings, #' + target + '-info').show();
    });

    // Handle inline tab links (e.g., "API Tokens tab" link)
    $('.insta-mcp-tab-link').on('click', function(e) {
        e.preventDefault();
        var target = $(this).attr('href').substring(1);
        var targetTab = $('.nav-tab[href="#' + target + '"]');

        if (targetTab.length) {
            $('.nav-tab').removeClass('nav-tab-active');
            targetTab.addClass('nav-tab-active');
            $('.tab-content').hide();
            $('#' + target + '-settings, #' + target + '-info').show();
        }
    });
});

// Copy token to clipboard
function copyToken(token) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(token).then(function() {
            alert('Token copied to clipboard!');
        }).catch(function(err) {
            fallbackCopyToken(token);
        });
    } else {
        fallbackCopyToken(token);
    }
}

function fallbackCopyToken(token) {
    var textArea = document.createElement('textarea');
    textArea.value = token;
    textArea.style.position = 'fixed';
    textArea.style.left = '-999999px';
    document.body.appendChild(textArea);
    textArea.select();
    try {
        document.execCommand('copy');
        alert('Token copied to clipboard!');
    } catch (err) {
        alert('Failed to copy token. Please copy it manually.');
    }
    document.body.removeChild(textArea);
}

// Show token usage modal
function showTokenUsage(endpointSlug) {
    var baseUrl = '<?php echo esc_js(home_url('/')); ?>';
    var modalHtml = '<div id="token-usage-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 100000; display: flex; align-items: center; justify-content: center;">' +
        '<div style="background: white; padding: 30px; border-radius: 5px; max-width: 600px; max-height: 80%; overflow-y: auto;">' +
        '<h2 style="margin-top: 0;">Token Usage Instructions</h2>' +
        '<p><strong>Note:</strong> For security reasons, tokens are only displayed once when created. If you need the token value, create a new token.</p>' +
        '<h3>Method 1: Query Parameter (GET requests only)</h3>' +
        '<p>Append your token to the URL:</p>' +
        '<code style="display: block; background: #f5f5f5; padding: 10px; margin: 10px 0; word-break: break-all;">' + baseUrl + endpointSlug + '?t=YOUR_TOKEN</code>' +
        '<h3>Method 2: Authorization Header (All HTTP methods)</h3>' +
        '<p>Include token in the Authorization header:</p>' +
        '<code style="display: block; background: #f5f5f5; padding: 10px; margin: 10px 0;">Authorization: Bearer YOUR_TOKEN</code>' +
        '<h3>Example with curl:</h3>' +
        '<code style="display: block; background: #f5f5f5; padding: 10px; margin: 10px 0; word-break: break-all;"># Query parameter<br>curl "' + baseUrl + endpointSlug + '?t=YOUR_TOKEN"<br><br># Header<br>curl -H "Authorization: Bearer YOUR_TOKEN" ' + baseUrl + endpointSlug + '</code>' +
        '<div style="text-align: right; margin-top: 20px;">' +
        '<button onclick="closeTokenUsageModal()" class="button button-primary">Close</button>' +
        '</div>' +
        '</div>' +
        '</div>';

    jQuery('body').append(modalHtml);
}

function closeTokenUsageModal() {
    jQuery('#token-usage-modal').remove();
}

// Close modal on click outside
jQuery(document).on('click', '#token-usage-modal', function(e) {
    if (e.target.id === 'token-usage-modal') {
        closeTokenUsageModal();
    }
});

// Copy endpoint URL to clipboard
function copyEndpointUrl(elementId) {
    var element = document.getElementById(elementId);
    var text = element.textContent || element.innerText;

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function() {
            alert('Copied to clipboard!');
        }).catch(function(err) {
            fallbackCopy(text);
        });
    } else {
        fallbackCopy(text);
    }
}

function fallbackCopy(text) {
    var textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.left = '-999999px';
    document.body.appendChild(textArea);
    textArea.select();
    try {
        document.execCommand('copy');
        alert('Copied to clipboard!');
    } catch (err) {
        alert('Failed to copy. Please copy it manually.');
    }
    document.body.removeChild(textArea);
}
</script>

<style>
.tab-content {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-top: none;
    padding: 20px;
    margin-bottom: 20px;
}

.endpoint-box {
    background: #f5f5f5;
    padding: 15px;
    margin: 15px 0;
    border-left: 4px solid #2271b1;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
}

.endpoint-box code {
    flex: 1;
    word-break: break-all;
    font-size: 13px;
    background: transparent;
    padding: 0;
}

.endpoint-box pre {
    margin: 0;
    flex: 1 1 100%;
}

.insta-mcp-tab-link {
    color: #2271b1;
    text-decoration: none;
    font-weight: 600;
}

.insta-mcp-tab-link:hover {
    color: #135e96;
    text-decoration: underline;
}

#endpoints-info h2 {
    margin-top: 0;
    padding-top: 0;
    color: #1d2327;
}

#endpoints-info h3 {
    margin-top: 25px;
    color: #1d2327;
    font-size: 16px;
}

#endpoints-info h4 {
    margin-top: 20px;
    margin-bottom: 10px;
    color: #2c3338;
    font-size: 14px;
}

#endpoints-info .widefat code {
    font-size: 12px;
}
</style>
