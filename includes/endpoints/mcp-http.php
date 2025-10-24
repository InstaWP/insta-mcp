<?php
/**
 * MCP HTTP Endpoint
 *
 * Handles HTTP requests to the MCP server
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', WP_CONTENT_DIR . '/debug.log');

// Import authentication and other classes
use InstaWP\MCP\PHP\Auth\AuthenticationManager;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Mcp\Server;
use Mcp\Server\Session\FileSessionStore;
use Mcp\Server\Transport\StreamableHttpTransport;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;

// Import our services and tools
use InstaWP\MCP\PHP\Services\ValidationService;
use InstaWP\MCP\PHP\Services\WordPressService;
use InstaWP\MCP\PHP\Logger\WordPressLogger;

// Content tools
use InstaWP\MCP\PHP\Tools\Content\ListContent;
use InstaWP\MCP\PHP\Tools\Content\GetContent;
use InstaWP\MCP\PHP\Tools\Content\CreateContent;
use InstaWP\MCP\PHP\Tools\Content\UpdateContent;
use InstaWP\MCP\PHP\Tools\Content\DeleteContent;
use InstaWP\MCP\PHP\Tools\Content\DiscoverContentTypes;
use InstaWP\MCP\PHP\Tools\Content\GetContentBySlug;
use InstaWP\MCP\PHP\Tools\Content\FindContentByUrl;

// Taxonomy tools
use InstaWP\MCP\PHP\Tools\Taxonomy\DiscoverTaxonomies;
use InstaWP\MCP\PHP\Tools\Taxonomy\GetTaxonomy;
use InstaWP\MCP\PHP\Tools\Taxonomy\ListTerms;
use InstaWP\MCP\PHP\Tools\Taxonomy\GetTerm;
use InstaWP\MCP\PHP\Tools\Taxonomy\CreateTerm;
use InstaWP\MCP\PHP\Tools\Taxonomy\UpdateTerm;
use InstaWP\MCP\PHP\Tools\Taxonomy\DeleteTerm;
use InstaWP\MCP\PHP\Tools\Taxonomy\AssignTermsToContent;
use InstaWP\MCP\PHP\Tools\Taxonomy\GetContentTerms;

// Plugin tools
use InstaWP\MCP\PHP\Tools\Plugin\PluginInfo;
use InstaWP\MCP\PHP\Tools\Plugin\PluginOperations;
use InstaWP\MCP\PHP\Tools\Plugin\PluginFiles;

// Theme tools
use InstaWP\MCP\PHP\Tools\Theme\ThemeInfo;
use InstaWP\MCP\PHP\Tools\Theme\ThemeOperations;
use InstaWP\MCP\PHP\Tools\Theme\ThemeFiles;

// Load configuration from WordPress options
$config = insta_mcp_get_config();

// Initialize authentication manager
$authManager = new AuthenticationManager($config);

// Validate authentication
$authResult = $authManager->authenticate();
if (!$authResult['authenticated']) {
    $authManager->sendUnauthorizedResponse($authResult['error'], $authResult['headers']);
    exit;
}

// Store user context for tools
$userContext = $authResult['user'];

// Set the current user in WordPress so capability checks work
if ($userContext !== null && isset($userContext['id'])) {
    wp_set_current_user($userContext['id']);
}

// Initialize services
$wpService = new WordPressService();
$validationService = new ValidationService();
$logger = new WordPressLogger();

// Log that endpoint was hit
$logger->info('MCP HTTP endpoint accessed', [
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
    'user_id' => $userContext['id'] ?? 'unknown'
]);

// Initialize all tools
$tools = [
    // Content tools
    new ListContent($wpService, $validationService, $logger),
    new GetContent($wpService, $validationService, $logger),
    new CreateContent($wpService, $validationService, $logger),
    new UpdateContent($wpService, $validationService, $logger),
    new DeleteContent($wpService, $validationService, $logger),
    new DiscoverContentTypes($wpService, $validationService, $logger),
    new GetContentBySlug($wpService, $validationService, $logger),
    new FindContentByUrl($wpService, $validationService, $logger),

    // Taxonomy tools
    new DiscoverTaxonomies($wpService, $validationService, $logger),
    new GetTaxonomy($wpService, $validationService, $logger),
    new ListTerms($wpService, $validationService, $logger),
    new GetTerm($wpService, $validationService, $logger),
    new CreateTerm($wpService, $validationService, $logger),
    new UpdateTerm($wpService, $validationService, $logger),
    new DeleteTerm($wpService, $validationService, $logger),
    new AssignTermsToContent($wpService, $validationService, $logger),
    new GetContentTerms($wpService, $validationService, $logger),

    // Plugin tools
    new PluginInfo($wpService, $validationService, $logger),
    new PluginOperations($wpService, $validationService, $logger),
    new PluginFiles($wpService, $validationService, $logger),

    // Theme tools
    new ThemeInfo($wpService, $validationService, $logger),
    new ThemeOperations($wpService, $validationService, $logger),
    new ThemeFiles($wpService, $validationService, $logger),
];

// Create PSR-7 request factory
$psr17Factory = new Psr17Factory();
$creator = new ServerRequestCreator($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);

// Get the incoming request
$request = $creator->fromGlobals();

// Build server info description with capabilities
$capabilities = [
    'safe_mode' => get_option('insta_mcp_safe_mode', false),
    'oauth_feature_enabled' => INSTA_MCP_OAUTH_FEATURE_ENABLED,
    'authentication_enabled' => $authManager->isAuthenticationEnabled(),
    'authentication_method' => $authManager->getAuthenticationMethod(),
    'oauth_enabled' => get_option('insta_mcp_oauth_enabled', false),
];

$description = 'InstaMCP - WordPress MCP Server - Capabilities: ' . json_encode($capabilities);

// Create sessions directory if it doesn't exist
$sessionsDir = WP_CONTENT_DIR . '/insta-mcp-sessions';
if (!is_dir($sessionsDir)) {
    wp_mkdir_p($sessionsDir);
}

// Create the MCP server
$serverBuilder = Server::builder()
    ->setServerInfo('InstaMCP', INSTA_MCP_VERSION, $description)
    ->setSession(new FileSessionStore($sessionsDir));

// Set user context on all tools (for OAuth scope validation)
if ($userContext !== null) {
    foreach ($tools as $tool) {
        $tool->setUserContext($userContext);
    }
}

// Register all tools with dynamic parameter handling
foreach ($tools as $tool) {
    $schema = $tool->getSchema();
    $params = [];
    $paramNames = [];

    foreach ($schema as $paramName => $rules) {
        // All parameters are optional with null default, we'll handle validation in the tool
        $params[] = "\${$paramName} = null";
        $paramNames[] = "'{$paramName}'";
    }

    $paramSignature = implode(', ', $params);
    $compactParams = implode(', ', $paramNames);

    // Create a wrapper function with proper named parameters
    if (empty($paramNames)) {
        // Tool has no parameters
        $wrapper = function() use ($tool, $logger) {
            try {
                $result = $tool->execute([]);
                return $result['data'] ?? $result;
            } catch (\Exception $e) {
                $logger->error("Tool wrapper error: " . $tool->getName(), [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        };
    } else {
        $code = <<<PHP
return function({$paramSignature}) use (\$tool, \$logger) {
    try {
        \$params = array_filter(compact({$compactParams}), fn(\$v) => \$v !== null);
        \$result = \$tool->execute(\$params);
        return \$result['data'] ?? \$result;
    } catch (\Exception \$e) {
        \$logger->error("Tool wrapper error: " . \$tool->getName(), [
            'error' => \$e->getMessage(),
            'trace' => \$e->getTraceAsString()
        ]);
        throw \$e;
    }
};
PHP;
        $wrapper = eval($code);
    }

    $serverBuilder->addTool(
        $wrapper,
        name: $tool->getName(),
        description: $tool->getDescription(),
        inputSchema: $tool->getInputSchema()
    );
}

// Add site info resource
$serverBuilder->addResource(
    function (): array {
        $theme = wp_get_theme();
        $timezone = get_option('timezone_string');

        // If timezone string is empty, try to get it from gmt_offset
        if (empty($timezone)) {
            $gmt_offset = get_option('gmt_offset');
            $timezone = $gmt_offset ? 'UTC' . ($gmt_offset >= 0 ? '+' : '') . $gmt_offset : 'UTC';
        }

        return [
            'site_name' => get_bloginfo('name'),
            'site_description' => get_bloginfo('description'),
            'site_url' => get_bloginfo('url'),
            'home_url' => home_url(),
            'admin_email' => get_bloginfo('admin_email'),
            'language' => get_bloginfo('language'),
            'timezone' => $timezone,
            'wordpress_version' => get_bloginfo('version'),
            'active_theme' => $theme->get('Name'),
            'active_theme_version' => $theme->get('Version'),
            'posts_count' => wp_count_posts('post')->publish,
            'pages_count' => wp_count_posts('page')->publish,
        ];
    },
    uri: 'wordpress://site/info',
    name: 'site_info',
    description: 'WordPress site information and statistics',
    mimeType: 'application/json'
);

$server = $serverBuilder->build();

// Create HTTP transport
$transport = new StreamableHttpTransport($request, $psr17Factory, $psr17Factory);

// Run the server and get response
$response = $server->run($transport);

// Emit the response using SAPI emitter
(new SapiEmitter())->emit($response);
