<?php

declare(strict_types=1);

namespace MCP\Server\Auth\Providers;

use MCP\Server\Auth\AuthInfo;
use MCP\Server\Auth\AuthorizationParams;
use MCP\Server\Auth\Errors\ServerError;
use MCP\Server\Auth\OAuthRegisteredClientsStore;
use MCP\Server\Auth\OAuthServerProvider;
use MCP\Shared\OAuthClientInformation;
use MCP\Shared\OAuthTokenRevocationRequest;
use MCP\Shared\OAuthTokens;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use React\Promise\Promise;
use React\Promise\PromiseInterface;

/**
 * Configuration for proxy endpoints.
 */
final readonly class ProxyEndpoints
{
    public function __construct(
        public string $authorizationUrl,
        public string $tokenUrl,
        public ?string $revocationUrl = null,
        public ?string $registrationUrl = null
    ) {}
}

/**
 * Implements an OAuth server that proxies requests to another OAuth server.
 */
final class ProxyProvider implements OAuthServerProvider
{
    private OAuthRegisteredClientsStore $clientsStore;

    public function __construct(
        private readonly ProxyEndpoints $endpoints,
        private readonly \Closure $verifyAccessToken,
        private readonly \Closure $getClient,
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory
    ) {
        $this->clientsStore = new ProxyClientsStore(
            $this->getClient,
            $this->endpoints->registrationUrl,
            $this->httpClient,
            $this->requestFactory,
            $this->streamFactory
        );
    }

    public function getClientsStore(): OAuthRegisteredClientsStore
    {
        return $this->clientsStore;
    }

    public function skipLocalPkceValidation(): bool
    {
        return true; // Let upstream server handle PKCE validation
    }

    public function authorize(
        OAuthClientInformation $client,
        AuthorizationParams $params,
        ResponseInterface $response
    ): PromiseInterface {
        return new Promise(function ($resolve) use ($client, $params, $response) {
            // Build authorization URL with all required parameters
            $url = $this->endpoints->authorizationUrl;
            $queryParams = [
                'client_id' => $client->getClientId(),
                'response_type' => 'code',
                'redirect_uri' => $params->redirectUri,
                'code_challenge' => $params->codeChallenge,
                'code_challenge_method' => 'S256'
            ];

            // Add optional parameters
            if ($params->state !== null) {
                $queryParams['state'] = $params->state;
            }
            if (!empty($params->scopes)) {
                $queryParams['scope'] = implode(' ', $params->scopes);
            }
            if ($params->resource !== null) {
                $queryParams['resource'] = $params->resource;
            }

            $targetUrl = $url . '?' . http_build_query($queryParams);

            // Create redirect response
            $redirectResponse = $response
                ->withStatus(302)
                ->withHeader('Location', $targetUrl);

            $resolve($redirectResponse);
        });
    }

    public function challengeForAuthorizationCode(
        OAuthClientInformation $client,
        string $authorizationCode
    ): PromiseInterface {
        // In proxy setup, we don't store the code challenge ourselves
        // Instead, we proxy the token request and let the upstream server validate it
        return new Promise(function ($resolve) {
            $resolve('');
        });
    }

    public function exchangeAuthorizationCode(
        OAuthClientInformation $client,
        string $authorizationCode,
        ?string $codeVerifier = null,
        ?string $redirectUri = null,
        ?string $resource = null
    ): PromiseInterface {
        return new Promise(function ($resolve, $reject) use ($client, $authorizationCode, $codeVerifier, $redirectUri, $resource) {
            try {
                $params = [
                    'grant_type' => 'authorization_code',
                    'client_id' => $client->getClientId(),
                    'code' => $authorizationCode,
                ];

                if ($client->getClientSecret() !== null) {
                    $params['client_secret'] = $client->getClientSecret();
                }

                if ($codeVerifier !== null) {
                    $params['code_verifier'] = $codeVerifier;
                }

                if ($redirectUri !== null) {
                    $params['redirect_uri'] = $redirectUri;
                }

                if ($resource !== null) {
                    $params['resource'] = $resource;
                }

                $request = $this->requestFactory
                    ->createRequest('POST', $this->endpoints->tokenUrl)
                    ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
                    ->withBody($this->streamFactory->createStream(http_build_query($params)));

                $response = $this->httpClient->sendRequest($request);

                if ($response->getStatusCode() !== 200) {
                    $reject(new ServerError("Token exchange failed: {$response->getStatusCode()}"));
                    return;
                }

                $data = json_decode((string)$response->getBody(), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $reject(new ServerError('Invalid JSON response from token endpoint'));
                    return;
                }

                $tokens = new OAuthTokens(
                    $data['access_token'],
                    $data['token_type'] ?? 'Bearer',
                    $data['id_token'] ?? null,
                    $data['expires_in'] ?? null,
                    $data['scope'] ?? null,
                    $data['refresh_token'] ?? null
                );

                $resolve($tokens);
            } catch (\Throwable $e) {
                $reject(new ServerError('Token exchange failed', $e));
            }
        });
    }

    public function exchangeRefreshToken(
        OAuthClientInformation $client,
        string $refreshToken,
        array $scopes = [],
        ?string $resource = null
    ): PromiseInterface {
        return new Promise(function ($resolve, $reject) use ($client, $refreshToken, $scopes, $resource) {
            try {
                $params = [
                    'grant_type' => 'refresh_token',
                    'client_id' => $client->getClientId(),
                    'refresh_token' => $refreshToken,
                ];

                if ($client->getClientSecret() !== null) {
                    $params['client_secret'] = $client->getClientSecret();
                }

                if (!empty($scopes)) {
                    $params['scope'] = implode(' ', $scopes);
                }

                if ($resource !== null) {
                    $params['resource'] = $resource;
                }

                $request = $this->requestFactory
                    ->createRequest('POST', $this->endpoints->tokenUrl)
                    ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
                    ->withBody($this->streamFactory->createStream(http_build_query($params)));

                $response = $this->httpClient->sendRequest($request);

                if ($response->getStatusCode() !== 200) {
                    $reject(new ServerError("Token refresh failed: {$response->getStatusCode()}"));
                    return;
                }

                $data = json_decode((string)$response->getBody(), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $reject(new ServerError('Invalid JSON response from token endpoint'));
                    return;
                }

                $tokens = new OAuthTokens(
                    $data['access_token'],
                    $data['token_type'] ?? 'Bearer',
                    $data['id_token'] ?? null,
                    $data['expires_in'] ?? null,
                    $data['scope'] ?? null,
                    $data['refresh_token'] ?? null
                );

                $resolve($tokens);
            } catch (\Throwable $e) {
                $reject(new ServerError('Token refresh failed', $e));
            }
        });
    }

    public function verifyAccessToken(string $token): PromiseInterface
    {
        return ($this->verifyAccessToken)($token);
    }

    public function revokeToken(
        OAuthClientInformation $client,
        OAuthTokenRevocationRequest $request
    ): PromiseInterface {
        return new Promise(function ($resolve, $reject) use ($client, $request) {
            if ($this->endpoints->revocationUrl === null) {
                $reject(new ServerError('No revocation endpoint configured'));
                return;
            }

            try {
                $params = [
                    'token' => $request->token,
                    'client_id' => $client->getClientId(),
                ];

                if ($client->getClientSecret() !== null) {
                    $params['client_secret'] = $client->getClientSecret();
                }

                if ($request->tokenTypeHint !== null) {
                    $params['token_type_hint'] = $request->tokenTypeHint;
                }

                $httpRequest = $this->requestFactory
                    ->createRequest('POST', $this->endpoints->revocationUrl)
                    ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
                    ->withBody($this->streamFactory->createStream(http_build_query($params)));

                $response = $this->httpClient->sendRequest($httpRequest);

                if ($response->getStatusCode() !== 200) {
                    $reject(new ServerError("Token revocation failed: {$response->getStatusCode()}"));
                    return;
                }

                /** @phpstan-ignore-next-line */
                $resolve();
            } catch (\Throwable $e) {
                $reject(new ServerError('Token revocation failed', $e));
            }
        });
    }
}

/**
 * Client store for proxy provider.
 */
final class ProxyClientsStore implements OAuthRegisteredClientsStore
{
    public function __construct(
        private readonly \Closure $getClient,
        private readonly ?string $registrationUrl,
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory
    ) {}

    public function getClient(string $clientId): PromiseInterface
    {
        return ($this->getClient)($clientId);
    }

    public function registerClient(OAuthClientInformation $client): PromiseInterface
    {
        return new Promise(function ($resolve, $reject) use ($client) {
            if ($this->registrationUrl === null) {
                $reject(new ServerError('No registration endpoint configured'));
                return;
            }

            try {
                $request = $this->requestFactory
                    ->createRequest('POST', $this->registrationUrl)
                    ->withHeader('Content-Type', 'application/json')
                    ->withBody($this->streamFactory->createStream(json_encode($client->jsonSerialize())));

                $response = $this->httpClient->sendRequest($request);

                if ($response->getStatusCode() !== 201) {
                    $reject(new ServerError("Client registration failed: {$response->getStatusCode()}"));
                    return;
                }

                $data = json_decode((string)$response->getBody(), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $reject(new ServerError('Invalid JSON response from registration endpoint'));
                    return;
                }

                $registeredClient = new OAuthClientInformation(
                    $data['client_id'],
                    $data['client_secret'] ?? null,
                    $data['client_id_issued_at'] ?? null,
                    $data['client_secret_expires_at'] ?? null
                );

                $resolve($registeredClient);
            } catch (\Throwable $e) {
                $reject(new ServerError('Client registration failed', $e));
            }
        });
    }
}
