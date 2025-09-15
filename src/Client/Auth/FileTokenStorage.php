<?php

declare(strict_types=1);

namespace MCP\Client\Auth;

use MCP\Shared\OAuthTokens;

/**
 * File-based token storage implementation.
 */
final class FileTokenStorage implements TokenStorage
{
    public function __construct(
        private readonly string $storageDir
    ) {
        if (!is_dir($this->storageDir)) {
            if (!mkdir($this->storageDir, 0700, true)) {
                throw new \RuntimeException("Cannot create storage directory: {$this->storageDir}");
            }
        }
    }

    public function storeTokens(string $clientId, OAuthTokens $tokens): void
    {
        $filename = $this->getTokenFilename($clientId);
        $data = json_encode($tokens->jsonSerialize(), JSON_THROW_ON_ERROR);

        if (file_put_contents($filename, $data, LOCK_EX) === false) {
            throw new \RuntimeException("Cannot write token file: {$filename}");
        }

        // Set restrictive permissions
        chmod($filename, 0600);
    }

    public function getTokens(string $clientId): ?OAuthTokens
    {
        $filename = $this->getTokenFilename($clientId);

        if (!file_exists($filename)) {
            return null;
        }

        $data = file_get_contents($filename);
        if ($data === false) {
            return null;
        }

        try {
            $decoded = json_decode($data, true, 512, JSON_THROW_ON_ERROR);

            return new OAuthTokens(
                $decoded['access_token'],
                $decoded['token_type'] ?? 'Bearer',
                $decoded['id_token'] ?? null,
                $decoded['expires_in'] ?? null,
                $decoded['scope'] ?? null,
                $decoded['refresh_token'] ?? null
            );
        } catch (\JsonException) {
            return null;
        }
    }

    public function clearTokens(string $clientId): void
    {
        $filename = $this->getTokenFilename($clientId);
        if (file_exists($filename)) {
            unlink($filename);
        }
    }

    public function clearAllTokens(): void
    {
        $pattern = $this->storageDir . '/tokens_*.json';
        foreach (glob($pattern) as $filename) {
            unlink($filename);
        }
    }

    private function getTokenFilename(string $clientId): string
    {
        $safeClientId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $clientId);

        return $this->storageDir . "/tokens_{$safeClientId}.json";
    }
}
