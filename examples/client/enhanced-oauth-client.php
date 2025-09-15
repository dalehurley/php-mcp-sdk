<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use function Amp\async;

use Amp\Future;
use MCP\Client\Auth\FileTokenStorage;
use MCP\Client\Auth\OAuthClient;
use MCP\Client\Auth\OAuthClientProvider;
use MCP\Client\Auth\OAuthUtils;
use MCP\Client\Client;
use MCP\Client\ClientOptions;
use MCP\Client\Transport\StdioClientTransport;
use MCP\Shared\OAuthClientInformationFull;
use MCP\Shared\OAuthClientMetadata;
use MCP\Shared\OAuthTokens;
use MCP\Types\Capabilities\ClientCapabilities;
use MCP\Types\Implementation;
use Monolog\Handler\StreamHandler;

use Monolog\Logger;

/**
 * Example OAuth Client Provider implementation.
 */
class ExampleOAuthProvider implements OAuthClientProvider
{
    private ?OAuthClientInformationFull $clientInfo = null;

    private ?OAuthTokens $tokens = null;

    private ?string $codeVerifier = null;

    public function __construct(
        private readonly string $redirectUrl = 'http://localhost:8080/callback',
        private readonly string $clientId = 'mcp-client',
        private readonly ?string $clientSecret = null
    ) {
    }

    public function getRedirectUrl(): string
    {
        return $this->redirectUrl;
    }

    public function getClientMetadata(): OAuthClientMetadata
    {
        return new OAuthClientMetadata(
            clientName: 'Enhanced MCP Client',
            clientUri: 'https://example.com/mcp-client',
            redirectUris: [$this->redirectUrl],
            grantTypes: ['authorization_code', 'refresh_token'],
            responseTypes: ['code'],
            tokenEndpointAuthMethod: $this->clientSecret ? 'client_secret_post' : 'none',
            scope: 'mcp:read mcp:write'
        );
    }

    public function state(): ?string
    {
        return OAuthUtils::generateState();
    }

    public function loadClientInformation(): Future
    {
        return async(function () {
            // In practice, this would load from persistent storage
            return $this->clientInfo;
        });
    }

    public function storeClientInformation(OAuthClientInformationFull $info): Future
    {
        return async(function () use ($info) {
            $this->clientInfo = $info;
            echo "Stored client information: {$info->getClientId()}\n";
        });
    }

    public function loadTokens(): Future
    {
        return async(function () {
            // In practice, this would load from persistent storage
            return $this->tokens;
        });
    }

    public function storeTokens(OAuthTokens $tokens): Future
    {
        return async(function () use ($tokens) {
            $this->tokens = $tokens;
            echo "Stored OAuth tokens (expires in: {$tokens->getExpiresIn()}s)\n";
        });
    }

    public function clearTokens(): Future
    {
        return async(function () {
            $this->tokens = null;
            echo "Cleared OAuth tokens\n";
        });
    }

    public function tokens(): Future
    {
        return $this->loadTokens();
    }

    public function redirectToAuthorization(string $authorizationUrl): Future
    {
        return async(function () use ($authorizationUrl) {
            echo "Please visit this URL to authorize the application:\n";
            echo "{$authorizationUrl}\n";
            echo 'After authorization, enter the authorization code: ';

            // In a real application, this would handle the redirect properly
            $authCode = trim(fgets(STDIN) ?: '');

            if (empty($authCode)) {
                throw new \Exception('Authorization code is required');
            }

            // Store the auth code for processing (simplified example)
            $_SESSION['auth_code'] = $authCode;
        });
    }

    public function saveCodeVerifier(string $codeVerifier): Future
    {
        return async(function () use ($codeVerifier) {
            $this->codeVerifier = $codeVerifier;
        });
    }

    public function codeVerifier(): Future
    {
        return async(function () {
            return $this->codeVerifier ?? throw new \Exception('No code verifier found');
        });
    }

    public function addClientAuthentication(
        array &$headers,
        array &$params,
        string $url,
        ?array $metadata = null
    ): Future {
        return async(function () use (&$headers, &$params) {
            // Custom authentication logic can be implemented here
            if ($this->clientSecret) {
                $params['client_secret'] = $this->clientSecret;
            }
        });
    }

    public function validateResourceURL(string $serverUrl, ?string $resource = null): Future
    {
        return async(function () use ($serverUrl, $resource) {
            // Default validation
            $defaultResource = OAuthUtils::getResourceUrlFromServerUrl($serverUrl);

            if ($resource && !OAuthUtils::validateResourceUrl($serverUrl, $resource)) {
                throw new \Exception("Invalid resource URL: {$resource}");
            }

            return new \DateTimeImmutable($resource ?? $defaultResource);
        });
    }

    public function invalidateCredentials(string $scope): Future
    {
        return async(function () use ($scope) {
            echo "Invalidating credentials: {$scope}\n";

            switch ($scope) {
                case 'all':
                    $this->clientInfo = null;
                    $this->tokens = null;
                    $this->codeVerifier = null;
                    break;
                case 'client':
                    $this->clientInfo = null;
                    break;
                case 'tokens':
                    $this->tokens = null;
                    break;
                case 'verifier':
                    $this->codeVerifier = null;
                    break;
            }
        });
    }
}

/**
 * Enhanced OAuth client example.
 */
function runEnhancedOAuthExample(): void
{
    // Start session for demo purposes
    session_start();

    // Create logger
    $logger = new Logger('mcp-client');
    $logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

    // Create OAuth provider
    $oauthProvider = new ExampleOAuthProvider();

    // Create client with capabilities
    $capabilities = new ClientCapabilities(
        sampling: null,
        elicitation: null,
        roots: null
    );

    $clientInfo = new Implementation(
        name: 'Enhanced MCP Client',
        version: '1.0.0'
    );

    $options = new ClientOptions($capabilities);

    // Create client with middleware
    $client = Client::builder($clientInfo, $options)
        ->withOAuth($oauthProvider)
        ->withRetry(3, 1.0)
        ->withLogging($logger)
        ->build();

    echo "Enhanced OAuth MCP Client Example\n";
    echo "=================================\n\n";

    // Example server command (replace with actual MCP server)
    $serverCommand = ['node', 'example-server.js'];
    $transport = new StdioClientTransport($serverCommand);

    try {
        // Connect to server
        echo "Connecting to MCP server...\n";
        $client->connect($transport)->await();
        echo "Connected successfully!\n\n";

        // The OAuth middleware will automatically handle authentication
        // if the server requires it

        // List available tools
        echo "Listing available tools...\n";
        $toolsResult = $client->listTools()->await();

        echo "Available tools:\n";
        foreach ($toolsResult->getTools() as $tool) {
            echo "- {$tool->getName()}: {$tool->getDescription()}\n";
        }

        // Call a tool (OAuth middleware will handle auth automatically)
        if (!empty($toolsResult->getTools())) {
            $firstTool = $toolsResult->getTools()[0];
            echo "\nCalling tool: {$firstTool->getName()}\n";

            $toolResult = $client->callToolByName($firstTool->getName(), [])->await();
            echo 'Tool result: ' . json_encode($toolResult->getContent(), JSON_PRETTY_PRINT) . "\n";
        }

        // Demonstrate middleware in action
        echo "\nMiddleware statistics:\n";
        echo "- Middleware count: {$client->getMiddlewareCount()}\n";
        echo '- Has middleware: ' . ($client->hasMiddleware() ? 'Yes' : 'No') . "\n";
    } catch (\Throwable $e) {
        echo "Error: {$e->getMessage()}\n";

        if ($e->getPrevious()) {
            echo "Caused by: {$e->getPrevious()->getMessage()}\n";
        }
    } finally {
        // Clean up
        echo "\nCleaning up...\n";
        $client->close()->await();
        echo "Client closed.\n";
    }
}

/**
 * OAuth flow demonstration.
 */
function demonstrateOAuthFlow(): void
{
    echo "\nOAuth Flow Demonstration\n";
    echo "========================\n";

    // Create OAuth client
    $httpClient = new \GuzzleHttp\Client();
    $requestFactory = new \GuzzleHttp\Psr7\HttpFactory();
    $streamFactory = new \GuzzleHttp\Psr7\HttpFactory();
    $tokenStorage = new FileTokenStorage('/tmp/mcp-oauth-tokens.json');

    $oauthClient = new OAuthClient($httpClient, $requestFactory, $streamFactory, $tokenStorage);
    $provider = new ExampleOAuthProvider();

    try {
        // Demonstrate the complete OAuth flow
        $serverUrl = 'https://example.com/mcp';

        echo "Starting OAuth flow for server: {$serverUrl}\n";

        $result = $oauthClient->auth($provider, $serverUrl)->await();

        echo "OAuth flow result: {$result}\n";

        if ($result === 'AUTHORIZED') {
            echo "Successfully authorized!\n";

            // Check if client is authorized
            $clientInfo = $provider->loadClientInformation()->await();
            if ($clientInfo && $oauthClient->isAuthorized($clientInfo->getClientId())) {
                echo "Client is authorized and ready to make requests.\n";
            }
        } elseif ($result === 'REDIRECT') {
            echo "User authorization required. Please complete the authorization flow.\n";
        }
    } catch (\Throwable $e) {
        echo "OAuth flow error: {$e->getMessage()}\n";
    }
}

// Run the examples
if (php_sapi_name() === 'cli') {
    echo "Enhanced MCP Client Examples\n";
    echo "============================\n\n";

    try {
        // Run OAuth flow demonstration
        demonstrateOAuthFlow();

        // Run enhanced client example
        runEnhancedOAuthExample();
    } catch (\Throwable $e) {
        echo "Example failed: {$e->getMessage()}\n";
        exit(1);
    }

    echo "\nExamples completed successfully!\n";
} else {
    echo "This example must be run from the command line.\n";
}
