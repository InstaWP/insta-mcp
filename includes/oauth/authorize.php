<?php
/**
 * OAuth 2.1 Authorization Endpoint
 *
 * Handles authorization requests with WordPress user authentication and consent
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../load-wordpress.php';

use InstaWP\MCP\PHP\OAuth\ClientRepository;
use InstaWP\MCP\PHP\OAuth\AuthCodeRepository;
use InstaWP\MCP\PHP\OAuth\ScopeRepository;

// Load configuration
$config = require __DIR__ . '/../config.php';
$oauthConfig = $config['oauth'] ?? [];

if (empty($oauthConfig['enabled'])) {
    http_response_code(503);
    die('OAuth is not enabled');
}

// Initialize repositories
$clientRepo = new ClientRepository();
$authCodeRepo = new AuthCodeRepository();
$scopeRepo = new ScopeRepository();

// Get query parameters
$clientId = $_GET['client_id'] ?? '';
$redirectUri = $_GET['redirect_uri'] ?? '';
$responseType = $_GET['response_type'] ?? '';
$state = $_GET['state'] ?? '';
$scope = $_GET['scope'] ?? 'mcp:read';
$codeChallenge = $_GET['code_challenge'] ?? '';
$codeChallengeMethod = $_GET['code_challenge_method'] ?? 'S256';

// Error helper
function sendError(string $redirectUri, string $error, string $description, string $state = ''): void
{
    $params = [
        'error' => $error,
        'error_description' => $description
    ];
    if (!empty($state)) {
        $params['state'] = $state;
    }

    $separator = strpos($redirectUri, '?') !== false ? '&' : '?';
    header('Location: ' . $redirectUri . $separator . http_build_query($params));
    exit;
}

// Validate request
if (empty($clientId)) {
    http_response_code(400);
    die('Missing client_id parameter');
}

if ($responseType !== 'code') {
    http_response_code(400);
    die('Unsupported response_type. Only "code" is supported.');
}

// Validate client
$client = $clientRepo->getClient($clientId);
if (!$client) {
    http_response_code(400);
    die('Invalid client_id');
}

// Validate redirect URI
if (empty($redirectUri)) {
    http_response_code(400);
    die('Missing redirect_uri parameter');
}

if (!$clientRepo->validateRedirectUri($clientId, $redirectUri)) {
    http_response_code(400);
    die('Invalid redirect_uri');
}

// Parse requested scopes
$requestedScopes = array_filter(explode(' ', $scope));
if (!$scopeRepo->validateScopes($requestedScopes)) {
    sendError($redirectUri, 'invalid_scope', 'One or more requested scopes are invalid', $state);
}

// Check if user is logged in
if (!is_user_logged_in()) {
    // Redirect to WordPress login, then back here
    $loginUrl = wp_login_url($_SERVER['REQUEST_URI']);
    header('Location: ' . $loginUrl);
    exit;
}

// Get current user
$currentUserId = get_current_user_id();

// Handle consent form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify nonce
    if (!isset($_POST['oauth_consent_nonce']) || !wp_verify_nonce($_POST['oauth_consent_nonce'], 'oauth_consent')) {
        http_response_code(403);
        die('Invalid nonce');
    }

    // Check if user approved
    if (isset($_POST['approve'])) {
        // Filter scopes based on user's WordPress role
        $grantedScopes = $scopeRepo->filterScopesForUser($currentUserId, $requestedScopes);

        if (empty($grantedScopes)) {
            sendError($redirectUri, 'access_denied', 'You do not have permission for any requested scopes', $state);
        }

        // Generate authorization code
        $code = bin2hex(random_bytes(32));

        // Store authorization code
        $success = $authCodeRepo->createCode(
            $code,
            $clientId,
            $currentUserId,
            $redirectUri,
            $grantedScopes,
            $codeChallenge,
            $codeChallengeMethod,
            600 // 10 minutes
        );

        if (!$success) {
            sendError($redirectUri, 'server_error', 'Failed to create authorization code', $state);
        }

        // Redirect back to client with authorization code
        $params = ['code' => $code];
        if (!empty($state)) {
            $params['state'] = $state;
        }

        $separator = strpos($redirectUri, '?') !== false ? '&' : '?';
        header('Location: ' . $redirectUri . $separator . http_build_query($params));
        exit;
    } elseif (isset($_POST['deny'])) {
        // User denied authorization
        sendError($redirectUri, 'access_denied', 'User denied authorization', $state);
    }
}

// Show consent screen
$user = wp_get_current_user();
$userScopes = $scopeRepo->getScopesForUser($currentUserId);
$filteredScopes = $scopeRepo->filterScopesForUser($currentUserId, $requestedScopes);
$allScopes = $scopeRepo->getAvailableScopes();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authorize <?php echo esc_html($client['client_name']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, sans-serif;
            background: #f6f7f9;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 100%;
            padding: 40px;
        }
        h1 {
            font-size: 24px;
            margin-bottom: 10px;
            color: #1e293b;
        }
        .client-info {
            background: #f8fafc;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .user-info {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 20px;
        }
        .scopes {
            margin: 20px 0;
        }
        .scopes h3 {
            font-size: 16px;
            margin-bottom: 12px;
            color: #334155;
        }
        .scope-item {
            display: flex;
            align-items: flex-start;
            padding: 10px;
            background: #f8fafc;
            border-radius: 4px;
            margin-bottom: 8px;
        }
        .scope-item.granted {
            background: #dcfce7;
            border-left: 3px solid #22c55e;
        }
        .scope-item.denied {
            background: #fee2e2;
            border-left: 3px solid #ef4444;
        }
        .scope-icon {
            margin-right: 10px;
            font-size: 18px;
        }
        .scope-text {
            flex: 1;
        }
        .scope-name {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 4px;
        }
        .scope-desc {
            font-size: 13px;
            color: #64748b;
        }
        .buttons {
            display: flex;
            gap: 12px;
            margin-top: 30px;
        }
        button {
            flex: 1;
            padding: 12px 24px;
            font-size: 15px;
            font-weight: 600;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-approve {
            background: #3b82f6;
            color: white;
        }
        .btn-approve:hover {
            background: #2563eb;
        }
        .btn-deny {
            background: #e2e8f0;
            color: #334155;
        }
        .btn-deny:hover {
            background: #cbd5e1;
        }
        .warning {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 12px;
            margin: 20px 0;
            border-radius: 4px;
            font-size: 14px;
            color: #92400e;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Authorize Application</h1>

        <div class="client-info">
            <strong><?php echo esc_html($client['client_name']); ?></strong> is requesting access to your WordPress site
        </div>

        <div class="user-info">
            Logged in as: <strong><?php echo esc_html($user->user_login); ?></strong>
        </div>

        <div class="scopes">
            <h3>Requested Permissions:</h3>

            <?php foreach ($requestedScopes as $scopeName): ?>
                <?php
                $isGranted = in_array($scopeName, $filteredScopes);
                $scopeClass = $isGranted ? 'granted' : 'denied';
                $scopeIcon = $isGranted ? '✓' : '✗';
                ?>
                <div class="scope-item <?php echo $scopeClass; ?>">
                    <div class="scope-icon"><?php echo $scopeIcon; ?></div>
                    <div class="scope-text">
                        <div class="scope-name"><?php echo esc_html($scopeName); ?></div>
                        <div class="scope-desc"><?php echo esc_html($allScopes[$scopeName] ?? 'Unknown scope'); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (count($filteredScopes) < count($requestedScopes)): ?>
            <div class="warning">
                ⚠️ Some requested permissions are not available based on your WordPress role. Only granted permissions will be included.
            </div>
        <?php endif; ?>

        <?php if (empty($filteredScopes)): ?>
            <div class="warning">
                ⚠️ You do not have permission to grant any of the requested scopes. Contact your site administrator if you believe this is an error.
            </div>
        <?php endif; ?>

        <form method="POST">
            <?php wp_nonce_field('oauth_consent', 'oauth_consent_nonce'); ?>
            <div class="buttons">
                <button type="submit" name="approve" class="btn-approve" <?php echo empty($filteredScopes) ? 'disabled' : ''; ?>>
                    Authorize
                </button>
                <button type="submit" name="deny" class="btn-deny">
                    Deny
                </button>
            </div>
        </form>
    </div>
</body>
</html>
