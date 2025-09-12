<?php

declare(strict_types=1);

namespace MCP\Server;

/**
 * Definition for a completable value
 * 
 * @template T
 */
class CompletableDef
{
    /**
     * @param mixed $type The underlying type
     * @param CompleteCallback $complete The completion callback
     */
    public function __construct(
        public mixed $type,
        public CompleteCallback $complete,
        public string $typeName = 'Completable'
    ) {}
}
