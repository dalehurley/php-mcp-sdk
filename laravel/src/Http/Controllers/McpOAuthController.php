<?php

declare(strict_types=1);

namespace MCP\Laravel\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use MCP\Server\Auth\OAuthServerProvider;
use MCP\Server\Auth\AuthInfo;
use MCP\Server\Auth\DefaultAuthInfo;
use MCP\Shared\AuthUtils;
use MCP\Types\McpError;

class McpOAuthController extends Controller
{
    public function __construct(
        private ?OAuthServerProvider $oauthProvider = null
    ) {
        // Use default OAuth provider if none injected
        if ($this->oauthProvider === null) {
            $this->oauthProvider = $this->createDefaultProvider();
        }
    }

    /**
     * OAuth 2.1 authorization endpoint.
     */
    public function authorize(Request $request): RedirectResponse|JsonResponse
    {
        try {
            $this->validateAuthorizeRequest($request);

            $clientId = $request->query('client_id');
            $redirectUri = $request->query('redirect_uri');
            $scopes = explode(' ', $request->query('scope', ''));
            $state = $request->query('state');
            $codeChallenge = $request->query('code_challenge');
            $codeChallengeMethod = $request->query('code_challenge_method', 'S256');

            // Validate client
            $client = $this->oauthProvider->validateClient($clientId, $redirectUri);
            if (!$client) {
                return $this->redirectWithError($redirectUri, 'invalid_client', $state);
            }

            // Validate scopes
            $validScopes = $this->validateScopes($scopes);
            if (empty($validScopes)) {
                return $this->redirectWithError($redirectUri, 'invalid_scope', $state);
            }

            // Store authorization request for later use
            $authCode = $this->generateAuthorizationCode();
            $this->storeAuthorizationRequest($authCode, [
                'client_id' => $clientId,
                'redirect_uri' => $redirectUri,
                'scopes' => $validScopes,
                'code_challenge' => $codeChallenge,
                'code_challenge_method' => $codeChallengeMethod,
                'user_id' => auth()->id() ?? 'anonymous',
                'expires_at' => now()->addMinutes(10)->timestamp,
            ]);

            // Auto-approve for development or redirect to consent screen
            if (config('mcp.auth.auto_approve', config('app.debug'))) {
                return redirect($redirectUri . '?' . http_build_query([
                    'code' => $authCode,
                    'state' => $state,
                ]));
            }

            // In a full implementation, this would redirect to a consent screen
            // For now, auto-approve
            return redirect($redirectUri . '?' . http_build_query([
                'code' => $authCode,
                'state' => $state,
            ]));

        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'invalid_request',
                'error_description' => $e->getMessage(),
            ], 400);
        } catch (\Throwable $e) {
            Log::channel(config('mcp.logging.channel'))->error('OAuth authorize error', [
                'error' => $e->getMessage(),
                'client_id' => $request->query('client_id'),
            ]);

            return response()->json([
                'error' => 'server_error',
                'error_description' => 'An internal error occurred',
            ], 500);
        }
    }

    /**
     * OAuth 2.1 token endpoint.
     */
    public function token(Request $request): JsonResponse
    {
        try {
            $grantType = $request->input('grant_type');

            return match ($grantType) {
                'authorization_code' => $this->handleAuthorizationCodeGrant($request),
                'refresh_token' => $this->handleRefreshTokenGrant($request),
                default => response()->json([
                    'error' => 'unsupported_grant_type',
                    'error_description' => 'Grant type not supported',
                ], 400),
            };

        } catch (\Throwable $e) {
            Log::channel(config('mcp.logging.channel'))->error('OAuth token error', [
                'error' => $e->getMessage(),
                'grant_type' => $request->input('grant_type'),
            ]);

            return response()->json([
                'error' => 'server_error',
                'error_description' => 'An internal error occurred',
            ], 500);
        }
    }

    /**
     * OAuth 2.1 token revocation endpoint.
     */
    public function revoke(Request $request): JsonResponse
    {
        try {
            $token = $request->input('token');
            $tokenTypeHint = $request->input('token_type_hint');

            if (empty($token)) {
                return response()->json([
                    'error' => 'invalid_request',
                    'error_description' => 'Token is required',
                ], 400);
            }

            // Revoke the token
            $this->revokeToken($token, $tokenTypeHint);

            return response()->json([], 200);

        } catch (\Throwable $e) {
            Log::channel(config('mcp.logging.channel'))->error('OAuth revoke error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([], 200); // Per spec, always return 200
        }
    }

    /**
     * OAuth 2.1 authorization server metadata endpoint.
     */
    public function metadata(Request $request): JsonResponse
    {
        $baseUrl = config('app.url');
        $prefix = config('mcp.routes.prefix', 'mcp');

        return response()->json([
            'issuer' => $baseUrl,
            'authorization_endpoint' => "{$baseUrl}/{$prefix}/oauth/authorize",
            'token_endpoint' => "{$baseUrl}/{$prefix}/oauth/token",
            'revocation_endpoint' => "{$baseUrl}/{$prefix}/oauth/revoke",
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'scopes_supported' => array_keys(config('mcp.auth.scopes', [])),
            'token_endpoint_auth_methods_supported' => ['client_secret_post', 'client_secret_basic', 'none'],
        ]);
    }

    /**
     * Handle authorization code grant.
     */
    protected function handleAuthorizationCodeGrant(Request $request): JsonResponse
    {
        $code = $request->input('code');
        $clientId = $request->input('client_id');
        $clientSecret = $request->input('client_secret');
        $redirectUri = $request->input('redirect_uri');
        $codeVerifier = $request->input('code_verifier');

        // Validate required parameters
        if (empty($code) || empty($clientId) || empty($redirectUri)) {
            return response()->json([
                'error' => 'invalid_request',
                'error_description' => 'Missing required parameters',
            ], 400);
        }

        // Retrieve and validate authorization request
        $authRequest = $this->getAuthorizationRequest($code);
        if (!$authRequest) {
            return response()->json([
                'error' => 'invalid_grant',
                'error_description' => 'Invalid authorization code',
            ], 400);
        }

        // Validate client
        if ($authRequest['client_id'] !== $clientId || $authRequest['redirect_uri'] !== $redirectUri) {
            return response()->json([
                'error' => 'invalid_grant',
                'error_description' => 'Authorization code mismatch',
            ], 400);
        }

        // Validate PKCE if code challenge was provided
        if (!empty($authRequest['code_challenge'])) {
            if (empty($codeVerifier)) {
                return response()->json([
                    'error' => 'invalid_request',
                    'error_description' => 'Code verifier required',
                ], 400);
            }

            if (!AuthUtils::verifyCodeChallenge($codeVerifier, $authRequest['code_challenge'], $authRequest['code_challenge_method'])) {
                return response()->json([
                    'error' => 'invalid_grant',
                    'error_description' => 'Invalid code verifier',
                ], 400);
            }
        }

        // Check expiration
        if ($authRequest['expires_at'] < now()->timestamp) {
            return response()->json([
                'error' => 'invalid_grant',
                'error_description' => 'Authorization code expired',
            ], 400);
        }

        // Generate tokens
        $accessToken = $this->generateAccessToken($authRequest);
        $refreshToken = $this->generateRefreshToken($authRequest);

        // Store tokens
        $this->storeTokens($accessToken, $refreshToken, $authRequest);

        // Clean up authorization code
        $this->deleteAuthorizationRequest($code);

        return response()->json([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => config('mcp.auth.tokens.access_lifetime', 3600),
            'refresh_token' => $refreshToken,
            'scope' => implode(' ', $authRequest['scopes']),
        ]);
    }

    /**
     * Handle refresh token grant.
     */
    protected function handleRefreshTokenGrant(Request $request): JsonResponse
    {
        $refreshToken = $request->input('refresh_token');
        $clientId = $request->input('client_id');

        if (empty($refreshToken)) {
            return response()->json([
                'error' => 'invalid_request',
                'error_description' => 'Refresh token is required',
            ], 400);
        }

        // Validate refresh token
        $tokenData = $this->getRefreshTokenData($refreshToken);
        if (!$tokenData || $tokenData['expires_at'] < now()->timestamp) {
            return response()->json([
                'error' => 'invalid_grant',
                'error_description' => 'Invalid refresh token',
            ], 400);
        }

        // Validate client
        if ($tokenData['client_id'] !== $clientId) {
            return response()->json([
                'error' => 'invalid_grant',
                'error_description' => 'Client mismatch',
            ], 400);
        }

        // Generate new access token
        $newAccessToken = $this->generateAccessToken($tokenData);
        
        // Update stored tokens
        $this->storeTokens($newAccessToken, $refreshToken, $tokenData);

        return response()->json([
            'access_token' => $newAccessToken,
            'token_type' => 'Bearer',
            'expires_in' => config('mcp.auth.tokens.access_lifetime', 3600),
            'scope' => implode(' ', $tokenData['scopes']),
        ]);
    }

    /**
     * Validate authorization request.
     */
    protected function validateAuthorizeRequest(Request $request): void
    {
        $request->validate([
            'response_type' => 'required|in:code',
            'client_id' => 'required|string',
            'redirect_uri' => 'required|url',
            'scope' => 'sometimes|string',
            'state' => 'sometimes|string',
            'code_challenge' => 'sometimes|string',
            'code_challenge_method' => 'sometimes|in:S256',
        ]);
    }

    /**
     * Validate requested scopes.
     */
    protected function validateScopes(array $scopes): array
    {
        $availableScopes = array_keys(config('mcp.auth.scopes', []));
        return array_intersect($scopes, $availableScopes);
    }

    /**
     * Generate authorization code.
     */
    protected function generateAuthorizationCode(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Generate access token.
     */
    protected function generateAccessToken(array $data): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Generate refresh token.
     */
    protected function generateRefreshToken(array $data): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Store authorization request.
     */
    protected function storeAuthorizationRequest(string $code, array $data): void
    {
        $driver = config('mcp.auth.tokens.storage_driver', 'cache');
        
        match ($driver) {
            'cache' => Cache::put("mcp:auth:code:{$code}", $data, now()->addMinutes(10)),
            'database' => \DB::table('mcp_authorization_codes')->insert([
                'code' => $code,
                'data' => json_encode($data),
                'expires_at' => now()->addMinutes(10),
                'created_at' => now(),
            ]),
            default => throw new \InvalidArgumentException("Unsupported storage driver: {$driver}"),
        };
    }

    /**
     * Get authorization request.
     */
    protected function getAuthorizationRequest(string $code): ?array
    {
        $driver = config('mcp.auth.tokens.storage_driver', 'cache');
        
        return match ($driver) {
            'cache' => Cache::get("mcp:auth:code:{$code}"),
            'database' => \DB::table('mcp_authorization_codes')
                ->where('code', $code)
                ->where('expires_at', '>', now())
                ->value('data') ? json_decode(\DB::table('mcp_authorization_codes')
                ->where('code', $code)
                ->value('data'), true) : null,
            default => null,
        };
    }

    /**
     * Delete authorization request.
     */
    protected function deleteAuthorizationRequest(string $code): void
    {
        $driver = config('mcp.auth.tokens.storage_driver', 'cache');
        
        match ($driver) {
            'cache' => Cache::forget("mcp:auth:code:{$code}"),
            'database' => \DB::table('mcp_authorization_codes')->where('code', $code)->delete(),
            default => null,
        };
    }

    /**
     * Store tokens.
     */
    protected function storeTokens(string $accessToken, string $refreshToken, array $data): void
    {
        $driver = config('mcp.auth.tokens.storage_driver', 'cache');
        $accessLifetime = config('mcp.auth.tokens.access_lifetime', 3600);
        $refreshLifetime = config('mcp.auth.tokens.refresh_lifetime', 86400 * 30);

        $tokenData = [
            'client_id' => $data['client_id'],
            'user_id' => $data['user_id'],
            'scopes' => $data['scopes'],
        ];

        match ($driver) {
            'cache' => [
                Cache::put("mcp:auth:access:{$accessToken}", $tokenData, $accessLifetime),
                Cache::put("mcp:auth:refresh:{$refreshToken}", array_merge($tokenData, [
                    'expires_at' => now()->addSeconds($refreshLifetime)->timestamp,
                ]), $refreshLifetime),
            ],
            'database' => [
                \DB::table('mcp_access_tokens')->updateOrInsert(
                    ['token' => $accessToken],
                    [
                        'token' => $accessToken,
                        'data' => json_encode($tokenData),
                        'expires_at' => now()->addSeconds($accessLifetime),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                ),
                \DB::table('mcp_refresh_tokens')->updateOrInsert(
                    ['token' => $refreshToken],
                    [
                        'token' => $refreshToken,
                        'data' => json_encode($tokenData),
                        'expires_at' => now()->addSeconds($refreshLifetime),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                ),
            ],
            default => throw new \InvalidArgumentException("Unsupported storage driver: {$driver}"),
        };
    }

    /**
     * Get refresh token data.
     */
    protected function getRefreshTokenData(string $refreshToken): ?array
    {
        $driver = config('mcp.auth.tokens.storage_driver', 'cache');
        
        return match ($driver) {
            'cache' => Cache::get("mcp:auth:refresh:{$refreshToken}"),
            'database' => \DB::table('mcp_refresh_tokens')
                ->where('token', $refreshToken)
                ->where('expires_at', '>', now())
                ->value('data') ? json_decode(\DB::table('mcp_refresh_tokens')
                ->where('token', $refreshToken)
                ->value('data'), true) : null,
            default => null,
        };
    }

    /**
     * Revoke token.
     */
    protected function revokeToken(string $token, ?string $hint): void
    {
        $driver = config('mcp.auth.tokens.storage_driver', 'cache');
        
        match ($driver) {
            'cache' => [
                Cache::forget("mcp:auth:access:{$token}"),
                Cache::forget("mcp:auth:refresh:{$token}"),
            ],
            'database' => [
                \DB::table('mcp_access_tokens')->where('token', $token)->delete(),
                \DB::table('mcp_refresh_tokens')->where('token', $token)->delete(),
            ],
            default => null,
        };
    }

    /**
     * Redirect with error.
     */
    protected function redirectWithError(string $redirectUri, string $error, ?string $state = null): RedirectResponse
    {
        $params = ['error' => $error];
        if ($state) {
            $params['state'] = $state;
        }

        return redirect($redirectUri . '?' . http_build_query($params));
    }

    /**
     * Create default OAuth provider.
     */
    protected function createDefaultProvider(): OAuthServerProvider
    {
        return new class implements OAuthServerProvider {
            public function validateClient(string $clientId, ?string $redirectUri = null): bool
            {
                // Basic validation - in production, validate against stored clients
                return !empty($clientId);
            }

            public function getClientSecret(string $clientId): ?string
            {
                // Return null for public clients (PKCE-only)
                return null;
            }
        };
    }
}