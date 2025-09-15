#!/usr/bin/env php
<?php

/**
 * Test Runner for PHP MCP SDK Examples.
 *
 * This script tests the basic functionality of all examples to ensure they work properly.
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Test configuration
$tests = [
    'syntax_check' => [
        'name' => 'PHP Syntax Check',
        'files' => [
            'examples/server/simple-server.php',
            'examples/client/simple-stdio-client.php',
            'examples/utils/inspector.php',
            'examples/utils/monitor.php',
        ],
    ],
    'basic_server' => [
        'name' => 'Basic Server Test',
        'timeout' => 5,
    ],
    'client_connection' => [
        'name' => 'Client Connection Test',
        'timeout' => 10,
    ],
];

// Colors for output
const RED = "\033[31m";
const GREEN = "\033[32m";
const YELLOW = "\033[33m";
const BLUE = "\033[34m";
const RESET = "\033[0m";

echo BLUE . '🧪 PHP MCP SDK Examples Test Runner' . RESET . "\n";
echo "====================================\n\n";

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

// Test 1: Syntax Check
echo YELLOW . '1. PHP Syntax Check' . RESET . "\n";
foreach ($tests['syntax_check']['files'] as $file) {
    $totalTests++;
    echo "   Checking: $file ... ";

    $output = [];
    $returnCode = 0;
    exec('php -l ' . escapeshellarg($file) . ' 2>&1', $output, $returnCode);

    if ($returnCode === 0) {
        echo GREEN . '✅ PASS' . RESET . "\n";
        $passedTests++;
    } else {
        echo RED . '❌ FAIL' . RESET . "\n";
        echo '      Error: ' . implode("\n      ", $output) . "\n";
        $failedTests++;
    }
}
echo "\n";

// Test 2: Basic Server Startup Test
echo YELLOW . '2. Basic Server Startup Test' . RESET . "\n";
$totalTests++;
echo '   Testing server startup ... ';

try {
    // Start server in background and test if it starts without immediate errors
    $serverProcess = proc_open(
        'php examples/server/simple-server.php',
        [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],   // stderr
        ],
        $pipes,
        dirname(__DIR__)
    );

    if (!is_resource($serverProcess)) {
        throw new Exception('Failed to start server process');
    }

    // Give server a moment to start
    usleep(500000); // 0.5 seconds

    // Check if process is still running
    $status = proc_get_status($serverProcess);

    if ($status['running']) {
        echo GREEN . '✅ PASS' . RESET . "\n";
        $passedTests++;

        // Clean up
        proc_terminate($serverProcess);
        proc_close($serverProcess);
    } else {
        // Read any error output
        $stderr = stream_get_contents($pipes[2]);

        throw new Exception('Server exited immediately. Error: ' . $stderr);
    }
} catch (Exception $e) {
    echo RED . '❌ FAIL' . RESET . "\n";
    echo '      Error: ' . $e->getMessage() . "\n";
    $failedTests++;

    // Clean up if needed
    if (isset($serverProcess) && is_resource($serverProcess)) {
        proc_terminate($serverProcess);
        proc_close($serverProcess);
    }
}
echo "\n";

// Test 3: Inspector Tool Test
echo YELLOW . '3. Inspector Tool Test' . RESET . "\n";
$totalTests++;
echo '   Testing inspector with help flag ... ';

try {
    $output = [];
    $returnCode = 0;
    exec('php examples/utils/inspector.php --help 2>&1', $output, $returnCode);

    if ($returnCode === 0 && !empty($output)) {
        // Check if help output contains expected content
        $helpText = implode("\n", $output);
        if (
            strpos($helpText, 'MCP Server Inspector') !== false &&
            strpos($helpText, '--server=PATH') !== false
        ) {
            echo GREEN . '✅ PASS' . RESET . "\n";
            $passedTests++;
        } else {
            throw new Exception("Help output doesn't contain expected content");
        }
    } else {
        throw new Exception('Inspector help command failed or produced no output');
    }
} catch (Exception $e) {
    echo RED . '❌ FAIL' . RESET . "\n";
    echo '      Error: ' . $e->getMessage() . "\n";
    $failedTests++;
}
echo "\n";

// Test 4: Monitor Tool Test
echo YELLOW . '4. Monitor Tool Test' . RESET . "\n";
$totalTests++;
echo '   Testing monitor with help flag ... ';

try {
    $output = [];
    $returnCode = 0;
    exec('php examples/utils/monitor.php --help 2>&1', $output, $returnCode);

    if ($returnCode === 0 && !empty($output)) {
        $helpText = implode("\n", $output);
        if (
            strpos($helpText, 'MCP Server Monitor') !== false &&
            strpos($helpText, '--server=PATH') !== false
        ) {
            echo GREEN . '✅ PASS' . RESET . "\n";
            $passedTests++;
        } else {
            throw new Exception("Help output doesn't contain expected content");
        }
    } else {
        throw new Exception('Monitor help command failed or produced no output');
    }
} catch (Exception $e) {
    echo RED . '❌ FAIL' . RESET . "\n";
    echo '      Error: ' . $e->getMessage() . "\n";
    $failedTests++;
}
echo "\n";

// Test 5: Docker Configuration Test
echo YELLOW . '5. Docker Configuration Test' . RESET . "\n";
$totalTests++;
echo '   Validating docker-compose.yml ... ';

try {
    if (!file_exists('examples/docker/docker-compose.yml')) {
        throw new Exception('Docker compose file not found');
    }

    // Basic YAML syntax check (if available)
    if (function_exists('yaml_parse_file')) {
        $parsed = yaml_parse_file('examples/docker/docker-compose.yml');
        if ($parsed === false) {
            throw new Exception('Invalid YAML syntax');
        }
    }

    // Check for required services
    $content = file_get_contents('examples/docker/docker-compose.yml');
    $requiredServices = ['mcp-simple-server', 'mcp-client'];

    foreach ($requiredServices as $service) {
        if (strpos($content, $service . ':') === false) {
            throw new Exception("Required service '$service' not found");
        }
    }

    echo GREEN . '✅ PASS' . RESET . "\n";
    $passedTests++;
} catch (Exception $e) {
    echo RED . '❌ FAIL' . RESET . "\n";
    echo '      Error: ' . $e->getMessage() . "\n";
    $failedTests++;
}
echo "\n";

// Test 6: Example README Test
echo YELLOW . '6. Documentation Test' . RESET . "\n";
$totalTests++;
echo '   Checking examples README ... ';

try {
    if (!file_exists('examples/README.md')) {
        throw new Exception('Examples README not found');
    }

    $readme = file_get_contents('examples/README.md');
    $requiredSections = [
        '# PHP MCP SDK Examples',
        '## 🔧 Server Examples',
        '## 💻 Client Examples',
        '## 🛠️ Utility Tools',
        '## 🐳 Docker Examples',
    ];

    foreach ($requiredSections as $section) {
        if (strpos($readme, $section) === false) {
            throw new Exception("Required section '$section' not found in README");
        }
    }

    echo GREEN . '✅ PASS' . RESET . "\n";
    $passedTests++;
} catch (Exception $e) {
    echo RED . '❌ FAIL' . RESET . "\n";
    echo '      Error: ' . $e->getMessage() . "\n";
    $failedTests++;
}
echo "\n";

// Summary
echo "====================================\n";
echo BLUE . 'Test Summary:' . RESET . "\n";
echo "  Total Tests: $totalTests\n";
echo '  ' . GREEN . "Passed: $passedTests" . RESET . "\n";
echo '  ' . RED . "Failed: $failedTests" . RESET . "\n";

if ($failedTests === 0) {
    echo "\n" . GREEN . '🎉 All tests passed!' . RESET . "\n";
    echo "\nYou can now run the examples:\n";
    echo "  • Start a server: php examples/server/simple-server.php\n";
    echo "  • Connect a client: php examples/client/simple-stdio-client.php\n";
    echo "  • Inspect a server: php examples/utils/inspector.php --server=examples/server/simple-server.php --interactive\n";
    echo "  • Monitor a server: php examples/utils/monitor.php --server=examples/server/simple-server.php --dashboard\n";
    echo "  • Use Docker: cd examples/docker && docker-compose up\n";
    exit(0);
} else {
    echo "\n" . RED . '❌ Some tests failed. Please fix the issues above before running examples.' . RESET . "\n";
    exit(1);
}
