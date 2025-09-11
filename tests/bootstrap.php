<?php

declare(strict_types=1);

// Bootstrap file for PHPUnit tests

require_once __DIR__ . '/../vendor/autoload.php';

// Explicitly load required files to ensure all classes are available
require_once __DIR__ . '/../src/Shared/Protocol.php';
require_once __DIR__ . '/../src/Server/Server.php';
require_once __DIR__ . '/../src/Server/RegisteredItems.php';
require_once __DIR__ . '/../src/Server/ResourceTemplate.php';
