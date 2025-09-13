<?php

declare(strict_types=1);

namespace MCP\Types\Supporting;

/**
 * Extra information about a message.
 */
final class MessageExtraInfo
{
    /**
     * @param array<string, mixed>|null $authInfo
     */
    public function __construct(
        private readonly ?RequestInfo $requestInfo = null,
        private readonly ?array $authInfo = null
    ) {
    }

    /**
     * Get the request information.
     */
    public function getRequestInfo(): ?RequestInfo
    {
        return $this->requestInfo;
    }

    /**
     * Get the authentication information.
     *
     * @return array<string, mixed>|null
     */
    public function getAuthInfo(): ?array
    {
        return $this->authInfo;
    }

    /**
     * Create from an array of data.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $requestInfo = null;
        if (isset($data['requestInfo']) && is_array($data['requestInfo'])) {
            $requestInfo = RequestInfo::fromArray($data['requestInfo']);
        }

        $authInfo = null;
        if (isset($data['authInfo']) && is_array($data['authInfo'])) {
            $authInfo = $data['authInfo'];
        }

        return new self($requestInfo, $authInfo);
    }
}
