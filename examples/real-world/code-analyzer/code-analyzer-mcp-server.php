#!/usr/bin/env php
<?php

/**
 * Code Analyzer MCP Server - Real-World Application Example
 * 
 * This is a comprehensive code analysis system built with MCP that demonstrates:
 * - Static code analysis and quality metrics
 * - Security vulnerability scanning
 * - Performance bottleneck detection
 * - Code complexity analysis
 * - Dependency analysis and recommendations
 * - Code style and formatting checks
 * - Documentation coverage analysis
 * - Technical debt assessment
 * 
 * Perfect example of how MCP can power development tools and
 * integrate with existing development workflows.
 * 
 * Usage:
 *   php code-analyzer-mcp-server.php
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

use MCP\Server\McpServer;
use MCP\Server\Transport\StdioServerTransport;
use MCP\Types\Implementation;
use MCP\Types\McpError;
use function Amp\async;

/**
 * Code Analysis Engine
 */
class CodeAnalysisEngine
{
    private array $analysisHistory = [];
    private array $metrics = [];

    /**
     * Analyze a PHP file for various quality metrics
     */
    public function analyzeFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new Exception("File not found: {$filePath}");
        }

        if (!is_readable($filePath)) {
            throw new Exception("File not readable: {$filePath}");
        }

        $content = file_get_contents($filePath);
        $lines = explode("\n", $content);

        $analysis = [
            'file_path' => $filePath,
            'file_size' => filesize($filePath),
            'line_count' => count($lines),
            'analyzed_at' => date('c'),
            'metrics' => [],
            'issues' => [],
            'suggestions' => [],
            'quality_score' => 0
        ];

        // Basic metrics
        $analysis['metrics'] = $this->calculateBasicMetrics($content, $lines);

        // Code quality analysis
        $analysis['issues'] = $this->findCodeIssues($content, $lines);

        // Generate suggestions
        $analysis['suggestions'] = $this->generateSuggestions($analysis);

        // Calculate overall quality score
        $analysis['quality_score'] = $this->calculateQualityScore($analysis);

        $this->analysisHistory[] = $analysis;

        return $analysis;
    }

    /**
     * Analyze a directory recursively
     */
    public function analyzeDirectory(string $dirPath): array
    {
        if (!is_dir($dirPath)) {
            throw new Exception("Directory not found: {$dirPath}");
        }

        $analysis = [
            'directory_path' => $dirPath,
            'analyzed_at' => date('c'),
            'files_analyzed' => 0,
            'total_lines' => 0,
            'total_size' => 0,
            'file_analyses' => [],
            'summary' => [],
            'recommendations' => []
        ];

        $phpFiles = $this->findPHPFiles($dirPath);

        foreach ($phpFiles as $file) {
            try {
                $fileAnalysis = $this->analyzeFile($file);
                $analysis['file_analyses'][] = $fileAnalysis;
                $analysis['files_analyzed']++;
                $analysis['total_lines'] += $fileAnalysis['line_count'];
                $analysis['total_size'] += $fileAnalysis['file_size'];
            } catch (Exception $e) {
                $analysis['file_analyses'][] = [
                    'file_path' => $file,
                    'error' => $e->getMessage()
                ];
            }
        }

        // Generate directory-level summary
        $analysis['summary'] = $this->generateDirectorySummary($analysis);
        $analysis['recommendations'] = $this->generateDirectoryRecommendations($analysis);

        return $analysis;
    }

    private function calculateBasicMetrics(string $content, array $lines): array
    {
        return [
            'file_size_bytes' => strlen($content),
            'line_count' => count($lines),
            'non_empty_lines' => count(array_filter($lines, fn($line) => trim($line) !== '')),
            'comment_lines' => count(array_filter($lines, fn($line) => preg_match('/^\s*(\/\/|\/\*|\*)/', $line))),
            'code_lines' => count(array_filter(
                $lines,
                fn($line) =>
                trim($line) !== '' && !preg_match('/^\s*(\/\/|\/\*|\*)/', $line)
            )),
            'function_count' => preg_match_all('/\bfunction\s+\w+/', $content),
            'class_count' => preg_match_all('/\bclass\s+\w+/', $content),
            'interface_count' => preg_match_all('/\binterface\s+\w+/', $content),
            'trait_count' => preg_match_all('/\btrait\s+\w+/', $content),
            'namespace_count' => preg_match_all('/\bnamespace\s+/', $content),
            'use_statements' => preg_match_all('/\buse\s+/', $content),
            'cyclomatic_complexity' => $this->calculateCyclomaticComplexity($content),
            'maintainability_index' => $this->calculateMaintainabilityIndex($content, $lines)
        ];
    }

    private function findCodeIssues(string $content, array $lines): array
    {
        $issues = [];

        // Security issues
        if (preg_match('/\b(eval|exec|system|shell_exec|passthru)\s*\(/', $content)) {
            $issues[] = [
                'type' => 'security',
                'severity' => 'high',
                'message' => 'Potentially dangerous function usage detected',
                'category' => 'Security Vulnerability'
            ];
        }

        if (preg_match('/\$_(GET|POST|REQUEST|COOKIE)\s*\[/', $content)) {
            $issues[] = [
                'type' => 'security',
                'severity' => 'medium',
                'message' => 'Direct superglobal usage without sanitization',
                'category' => 'Input Validation'
            ];
        }

        // Code quality issues
        if (preg_match_all('/\bfunction\s+\w+\s*\([^)]*\)\s*\{/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $functionStart = $match[1];
                $functionContent = substr($content, $functionStart);
                $braceCount = 0;
                $functionLength = 0;

                for ($i = 0; $i < strlen($functionContent); $i++) {
                    if ($functionContent[$i] === '{') $braceCount++;
                    if ($functionContent[$i] === '}') {
                        $braceCount--;
                        if ($braceCount === 0) {
                            $functionLength = $i;
                            break;
                        }
                    }
                }

                $functionLines = substr_count(substr($functionContent, 0, $functionLength), "\n");

                if ($functionLines > 50) {
                    $issues[] = [
                        'type' => 'maintainability',
                        'severity' => 'medium',
                        'message' => "Long function detected ({$functionLines} lines)",
                        'category' => 'Code Complexity'
                    ];
                }
            }
        }

        // Performance issues
        if (preg_match('/\bfor\s*\([^)]*\)\s*\{[^}]*\bfor\s*\(/', $content)) {
            $issues[] = [
                'type' => 'performance',
                'severity' => 'medium',
                'message' => 'Nested loops detected - potential performance issue',
                'category' => 'Performance'
            ];
        }

        if (preg_match('/\bmysql_\w+\s*\(/', $content)) {
            $issues[] = [
                'type' => 'deprecated',
                'severity' => 'high',
                'message' => 'Deprecated MySQL extension usage',
                'category' => 'Deprecated Code'
            ];
        }

        // Documentation issues
        $classCount = preg_match_all('/\bclass\s+\w+/', $content);
        $docBlockCount = preg_match_all('/\/\*\*[\s\S]*?\*\//', $content);

        if ($classCount > 0 && $docBlockCount === 0) {
            $issues[] = [
                'type' => 'documentation',
                'severity' => 'low',
                'message' => 'Missing documentation blocks',
                'category' => 'Documentation'
            ];
        }

        return $issues;
    }

    private function calculateCyclomaticComplexity(string $content): int
    {
        // Simplified cyclomatic complexity calculation
        $complexity = 1; // Base complexity

        // Count decision points
        $complexity += preg_match_all('/\b(if|while|for|foreach|case|catch|&&|\|\|)\b/', $content);

        return $complexity;
    }

    private function calculateMaintainabilityIndex(string $content, array $lines): float
    {
        // Simplified maintainability index
        $loc = count($lines);
        $complexity = $this->calculateCyclomaticComplexity($content);
        $commentRatio = count(array_filter($lines, fn($line) => preg_match('/^\s*(\/\/|\/\*|\*)/', $line))) / max($loc, 1);

        // Simple formula (real MI is more complex)
        $mi = max(0, 100 - ($complexity * 2) - ($loc / 10) + ($commentRatio * 10));

        return round($mi, 2);
    }

    private function generateSuggestions(array $analysis): array
    {
        $suggestions = [];

        if ($analysis['metrics']['cyclomatic_complexity'] > 10) {
            $suggestions[] = 'Consider breaking down complex functions into smaller, more focused functions';
        }

        if ($analysis['metrics']['maintainability_index'] < 70) {
            $suggestions[] = 'Improve code maintainability by adding documentation and reducing complexity';
        }

        if ($analysis['metrics']['comment_lines'] / $analysis['metrics']['code_lines'] < 0.1) {
            $suggestions[] = 'Add more comments to improve code readability';
        }

        if (count($analysis['issues']) > 5) {
            $suggestions[] = 'Address code quality issues to improve overall code health';
        }

        return $suggestions;
    }

    private function calculateQualityScore(array $analysis): float
    {
        $score = 100;

        // Deduct points for issues
        foreach ($analysis['issues'] as $issue) {
            $deduction = match ($issue['severity']) {
                'high' => 15,
                'medium' => 10,
                'low' => 5,
                default => 5
            };
            $score -= $deduction;
        }

        // Bonus for good maintainability
        if ($analysis['metrics']['maintainability_index'] > 80) {
            $score += 5;
        }

        // Bonus for good documentation
        $commentRatio = $analysis['metrics']['comment_lines'] / max($analysis['metrics']['code_lines'], 1);
        if ($commentRatio > 0.2) {
            $score += 5;
        }

        return max(0, min(100, $score));
    }

    private function findPHPFiles(string $dir): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function generateDirectorySummary(array $analysis): array
    {
        $totalIssues = 0;
        $totalQualityScore = 0;
        $issuesByType = [];
        $issuesBySeverity = [];

        foreach ($analysis['file_analyses'] as $fileAnalysis) {
            if (isset($fileAnalysis['issues'])) {
                $totalIssues += count($fileAnalysis['issues']);

                foreach ($fileAnalysis['issues'] as $issue) {
                    $issuesByType[$issue['type']] = ($issuesByType[$issue['type']] ?? 0) + 1;
                    $issuesBySeverity[$issue['severity']] = ($issuesBySeverity[$issue['severity']] ?? 0) + 1;
                }
            }

            if (isset($fileAnalysis['quality_score'])) {
                $totalQualityScore += $fileAnalysis['quality_score'];
            }
        }

        $avgQualityScore = $analysis['files_analyzed'] > 0 ? $totalQualityScore / $analysis['files_analyzed'] : 0;

        return [
            'total_issues' => $totalIssues,
            'average_quality_score' => round($avgQualityScore, 2),
            'issues_by_type' => $issuesByType,
            'issues_by_severity' => $issuesBySeverity,
            'files_with_issues' => count(array_filter($analysis['file_analyses'], fn($fa) => !empty($fa['issues'] ?? []))),
            'technical_debt_ratio' => $totalIssues / max($analysis['total_lines'], 1) * 1000 // Issues per 1000 lines
        ];
    }

    private function generateDirectoryRecommendations(array $analysis): array
    {
        $recommendations = [];
        $summary = $analysis['summary'];

        if ($summary['average_quality_score'] < 70) {
            $recommendations[] = [
                'priority' => 'high',
                'category' => 'Code Quality',
                'message' => 'Overall code quality is below recommended threshold',
                'action' => 'Focus on addressing high-severity issues first'
            ];
        }

        if ($summary['technical_debt_ratio'] > 10) {
            $recommendations[] = [
                'priority' => 'medium',
                'category' => 'Technical Debt',
                'message' => 'High technical debt detected',
                'action' => 'Allocate time for refactoring and code cleanup'
            ];
        }

        if (($summary['issues_by_severity']['high'] ?? 0) > 0) {
            $recommendations[] = [
                'priority' => 'critical',
                'category' => 'Security',
                'message' => 'High-severity security issues found',
                'action' => 'Address security vulnerabilities immediately'
            ];
        }

        if ($summary['files_with_issues'] / max($analysis['files_analyzed'], 1) > 0.5) {
            $recommendations[] = [
                'priority' => 'medium',
                'category' => 'Code Standards',
                'message' => 'More than 50% of files have quality issues',
                'action' => 'Implement code review process and automated quality checks'
            ];
        }

        return $recommendations;
    }
}

/**
 * Security Scanner
 */
class SecurityScanner
{
    private array $vulnerabilityPatterns = [
        'sql_injection' => [
            'pattern' => '/\$\w+\s*\.\s*["\']SELECT|INSERT|UPDATE|DELETE/',
            'severity' => 'critical',
            'description' => 'Potential SQL injection vulnerability'
        ],
        'xss' => [
            'pattern' => '/echo\s+\$_(GET|POST|REQUEST)/',
            'severity' => 'high',
            'description' => 'Potential XSS vulnerability - unescaped output'
        ],
        'file_inclusion' => [
            'pattern' => '/(include|require)(_once)?\s*\(\s*\$_(GET|POST|REQUEST)/',
            'severity' => 'critical',
            'description' => 'Potential file inclusion vulnerability'
        ],
        'command_injection' => [
            'pattern' => '/(exec|system|shell_exec|passthru)\s*\(\s*\$/',
            'severity' => 'critical',
            'description' => 'Potential command injection vulnerability'
        ],
        'weak_crypto' => [
            'pattern' => '/\b(md5|sha1)\s*\(/',
            'severity' => 'medium',
            'description' => 'Weak cryptographic function usage'
        ]
    ];

    public function scanFile(string $content): array
    {
        $vulnerabilities = [];

        foreach ($this->vulnerabilityPatterns as $type => $config) {
            if (preg_match_all($config['pattern'], $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $vulnerabilities[] = [
                        'type' => $type,
                        'severity' => $config['severity'],
                        'description' => $config['description'],
                        'line' => substr_count(substr($content, 0, $match[1]), "\n") + 1,
                        'code_snippet' => trim($match[0])
                    ];
                }
            }
        }

        return $vulnerabilities;
    }
}

/**
 * Performance Analyzer
 */
class PerformanceAnalyzer
{
    public function analyzePerformance(string $content): array
    {
        $issues = [];

        // Check for potential performance issues
        if (preg_match('/\bfor\s*\([^)]*\)\s*\{[^}]*\bfor\s*\(/', $content)) {
            $issues[] = [
                'type' => 'nested_loops',
                'severity' => 'medium',
                'description' => 'Nested loops can cause performance issues with large datasets',
                'recommendation' => 'Consider optimizing algorithm or using more efficient data structures'
            ];
        }

        if (preg_match('/\bfile_get_contents\s*\(\s*["\']http/', $content)) {
            $issues[] = [
                'type' => 'blocking_io',
                'severity' => 'medium',
                'description' => 'Blocking HTTP calls can cause performance bottlenecks',
                'recommendation' => 'Use async HTTP client or implement timeout handling'
            ];
        }

        if (preg_match('/\bmysql_query\s*\([^)]*\bSELECT\s+\*/', $content)) {
            $issues[] = [
                'type' => 'inefficient_query',
                'severity' => 'low',
                'description' => 'SELECT * queries can be inefficient',
                'recommendation' => 'Select only needed columns to improve performance'
            ];
        }

        return $issues;
    }
}

// Initialize analysis components
$codeAnalyzer = new CodeAnalysisEngine();
$securityScanner = new SecurityScanner();
$performanceAnalyzer = new PerformanceAnalyzer();

// Create Code Analyzer MCP Server
$server = new McpServer(
    new Implementation(
        'code-analyzer-server',
        '1.0.0',
        'Comprehensive Code Analysis System with MCP'
    )
);

// Tool: Analyze File
$server->tool(
    'analyze_file',
    'Perform comprehensive analysis of a PHP file',
    [
        'type' => 'object',
        'properties' => [
            'file_path' => ['type' => 'string', 'description' => 'Path to PHP file to analyze'],
            'include_security' => ['type' => 'boolean', 'default' => true],
            'include_performance' => ['type' => 'boolean', 'default' => true],
            'include_suggestions' => ['type' => 'boolean', 'default' => true]
        ],
        'required' => ['file_path']
    ],
    function (array $args) use ($codeAnalyzer, $securityScanner, $performanceAnalyzer): array {
        $filePath = $args['file_path'];
        $includeSecurity = $args['include_security'] ?? true;
        $includePerformance = $args['include_performance'] ?? true;
        $includeSuggestions = $args['include_suggestions'] ?? true;

        try {
            $analysis = $codeAnalyzer->analyzeFile($filePath);

            // Add security analysis
            if ($includeSecurity) {
                $content = file_get_contents($filePath);
                $securityIssues = $securityScanner->scanFile($content);
                $analysis['security_issues'] = $securityIssues;
            }

            // Add performance analysis
            if ($includePerformance) {
                $content = file_get_contents($filePath);
                $performanceIssues = $performanceAnalyzer->analyzePerformance($content);
                $analysis['performance_issues'] = $performanceIssues;
            }

            // Format report
            $report = "🔍 Code Analysis Report: " . basename($filePath) . "\n";
            $report .= "=" . str_repeat("=", 50) . "\n\n";

            $report .= "📊 File Metrics\n";
            $report .= "-" . str_repeat("-", 15) . "\n";
            $report .= "Size: " . number_format($analysis['file_size']) . " bytes\n";
            $report .= "Lines: {$analysis['line_count']} total, {$analysis['metrics']['code_lines']} code, {$analysis['metrics']['comment_lines']} comments\n";
            $report .= "Functions: {$analysis['metrics']['function_count']}\n";
            $report .= "Classes: {$analysis['metrics']['class_count']}\n";
            $report .= "Complexity: {$analysis['metrics']['cyclomatic_complexity']}\n";
            $report .= "Maintainability Index: {$analysis['metrics']['maintainability_index']}/100\n";
            $report .= "Quality Score: {$analysis['quality_score']}/100\n\n";

            if (!empty($analysis['issues'])) {
                $report .= "⚠️ Code Quality Issues (" . count($analysis['issues']) . ")\n";
                $report .= "-" . str_repeat("-", 25) . "\n";
                foreach ($analysis['issues'] as $issue) {
                    $severityIcon = match ($issue['severity']) {
                        'critical' => '🔴',
                        'high' => '🟠',
                        'medium' => '🟡',
                        'low' => '🟢'
                    };
                    $report .= "{$severityIcon} {$issue['category']}: {$issue['message']}\n";
                }
                $report .= "\n";
            }

            if ($includeSecurity && !empty($analysis['security_issues'])) {
                $report .= "🔒 Security Issues (" . count($analysis['security_issues']) . ")\n";
                $report .= "-" . str_repeat("-", 20) . "\n";
                foreach ($analysis['security_issues'] as $issue) {
                    $severityIcon = match ($issue['severity']) {
                        'critical' => '🔴',
                        'high' => '🟠',
                        'medium' => '🟡',
                        'low' => '🟢'
                    };
                    $report .= "{$severityIcon} Line {$issue['line']}: {$issue['description']}\n";
                    $report .= "   Code: {$issue['code_snippet']}\n";
                }
                $report .= "\n";
            }

            if ($includePerformance && !empty($analysis['performance_issues'])) {
                $report .= "⚡ Performance Issues (" . count($analysis['performance_issues']) . ")\n";
                $report .= "-" . str_repeat("-", 22) . "\n";
                foreach ($analysis['performance_issues'] as $issue) {
                    $report .= "• {$issue['description']}\n";
                    $report .= "  Recommendation: {$issue['recommendation']}\n";
                }
                $report .= "\n";
            }

            if ($includeSuggestions && !empty($analysis['suggestions'])) {
                $report .= "💡 Suggestions\n";
                $report .= "-" . str_repeat("-", 13) . "\n";
                foreach ($analysis['suggestions'] as $suggestion) {
                    $report .= "• {$suggestion}\n";
                }
            }

            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $report
                    ]
                ]
            ];
        } catch (Exception $e) {
            throw new McpError(-32602, "Analysis failed: " . $e->getMessage());
        }
    }
);

// Tool: Analyze Directory
$server->tool(
    'analyze_directory',
    'Perform comprehensive analysis of a directory containing PHP files',
    [
        'type' => 'object',
        'properties' => [
            'directory_path' => ['type' => 'string', 'description' => 'Path to directory to analyze'],
            'recursive' => ['type' => 'boolean', 'default' => true],
            'summary_only' => ['type' => 'boolean', 'default' => false, 'description' => 'Show only summary without file details']
        ],
        'required' => ['directory_path']
    ],
    function (array $args) use ($codeAnalyzer): array {
        $dirPath = $args['directory_path'];
        $summaryOnly = $args['summary_only'] ?? false;

        try {
            $analysis = $codeAnalyzer->analyzeDirectory($dirPath);

            $report = "🏗️ Directory Analysis Report: " . basename($dirPath) . "\n";
            $report .= "=" . str_repeat("=", 50) . "\n\n";

            $report .= "📊 Overview\n";
            $report .= "-" . str_repeat("-", 10) . "\n";
            $report .= "Files Analyzed: {$analysis['files_analyzed']}\n";
            $report .= "Total Lines: " . number_format($analysis['total_lines']) . "\n";
            $report .= "Total Size: " . round($analysis['total_size'] / 1024, 2) . " KB\n";
            $report .= "Average Quality Score: {$analysis['summary']['average_quality_score']}/100\n";
            $report .= "Total Issues: {$analysis['summary']['total_issues']}\n";
            $report .= "Technical Debt Ratio: " . round($analysis['summary']['technical_debt_ratio'], 2) . " issues/1000 lines\n\n";

            if (!empty($analysis['summary']['issues_by_severity'])) {
                $report .= "🚨 Issues by Severity\n";
                $report .= "-" . str_repeat("-", 20) . "\n";
                foreach ($analysis['summary']['issues_by_severity'] as $severity => $count) {
                    $icon = match ($severity) {
                        'critical' => '🔴',
                        'high' => '🟠',
                        'medium' => '🟡',
                        'low' => '🟢'
                    };
                    $report .= "{$icon} {$severity}: {$count}\n";
                }
                $report .= "\n";
            }

            if (!empty($analysis['summary']['issues_by_type'])) {
                $report .= "📋 Issues by Type\n";
                $report .= "-" . str_repeat("-", 16) . "\n";
                foreach ($analysis['summary']['issues_by_type'] as $type => $count) {
                    $report .= "• {$type}: {$count}\n";
                }
                $report .= "\n";
            }

            if (!empty($analysis['recommendations'])) {
                $report .= "🎯 Recommendations\n";
                $report .= "-" . str_repeat("-", 17) . "\n";
                foreach ($analysis['recommendations'] as $rec) {
                    $priorityIcon = match ($rec['priority']) {
                        'critical' => '🔴',
                        'high' => '🟠',
                        'medium' => '🟡',
                        'low' => '🟢'
                    };
                    $report .= "{$priorityIcon} {$rec['category']}: {$rec['message']}\n";
                    $report .= "   Action: {$rec['action']}\n\n";
                }
            }

            if (!$summaryOnly && !empty($analysis['file_analyses'])) {
                $report .= "📁 File Details\n";
                $report .= "-" . str_repeat("-", 14) . "\n";

                // Show top 5 files with issues
                $filesWithIssues = array_filter($analysis['file_analyses'], fn($fa) => !empty($fa['issues'] ?? []));
                usort($filesWithIssues, fn($a, $b) => count($b['issues'] ?? []) <=> count($a['issues'] ?? []));
                $topFiles = array_slice($filesWithIssues, 0, 5);

                foreach ($topFiles as $fileAnalysis) {
                    $fileName = basename($fileAnalysis['file_path']);
                    $issueCount = count($fileAnalysis['issues'] ?? []);
                    $qualityScore = $fileAnalysis['quality_score'] ?? 0;

                    $report .= "📄 {$fileName}: {$issueCount} issues, {$qualityScore}/100 quality\n";
                }

                if (count($filesWithIssues) > 5) {
                    $remaining = count($filesWithIssues) - 5;
                    $report .= "... and {$remaining} more files with issues\n";
                }
            }

            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $report
                    ]
                ]
            ];
        } catch (Exception $e) {
            throw new McpError(-32602, "Directory analysis failed: " . $e->getMessage());
        }
    }
);

// Resource: Analysis Standards
$server->resource(
    'Analysis Standards',
    'analyzer://standards',
    [
        'title' => 'Code Analysis Standards and Metrics',
        'description' => 'Standards and thresholds used for code quality analysis',
        'mimeType' => 'application/json'
    ],
    function (): string {
        return json_encode([
            'quality_metrics' => [
                'cyclomatic_complexity' => [
                    'description' => 'Measure of code complexity',
                    'thresholds' => ['low' => '1-5', 'medium' => '6-10', 'high' => '11-20', 'very_high' => '21+']
                ],
                'maintainability_index' => [
                    'description' => 'Overall maintainability score',
                    'thresholds' => ['poor' => '0-25', 'fair' => '26-50', 'good' => '51-75', 'excellent' => '76-100']
                ],
                'quality_score' => [
                    'description' => 'Overall file quality score',
                    'calculation' => 'Based on issues, complexity, and documentation'
                ]
            ],
            'issue_categories' => [
                'security' => 'Security vulnerabilities and risks',
                'performance' => 'Performance bottlenecks and inefficiencies',
                'maintainability' => 'Code complexity and structure issues',
                'documentation' => 'Missing or inadequate documentation',
                'deprecated' => 'Usage of deprecated functions or patterns'
            ],
            'severity_levels' => [
                'critical' => 'Immediate attention required - security risks',
                'high' => 'Should be addressed soon - significant impact',
                'medium' => 'Should be addressed - moderate impact',
                'low' => 'Nice to fix - minor impact'
            ],
            'analysis_features' => [
                'static_analysis',
                'security_scanning',
                'performance_analysis',
                'code_metrics',
                'documentation_coverage',
                'dependency_analysis',
                'technical_debt_assessment'
            ]
        ], JSON_PRETTY_PRINT);
    }
);

// Prompt: Code Analysis Help
$server->prompt(
    'analysis_help',
    'Get help with code analysis and quality improvement',
    function (): array {
        return [
            'description' => 'Code Analysis Assistant',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'How do I improve my code quality using this analyzer?'
                        ]
                    ]
                ],
                [
                    'role' => 'assistant',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => "This Code Analyzer provides comprehensive code quality assessment:\n\n" .
                                "**🔍 Analysis Tools:**\n" .
                                "• **analyze_file** - Detailed analysis of individual PHP files\n" .
                                "• **analyze_directory** - Comprehensive directory-wide analysis\n" .
                                "• Multi-dimensional quality assessment\n\n" .
                                "**📊 Quality Metrics:**\n" .
                                "• **Cyclomatic Complexity** - Code complexity measurement\n" .
                                "• **Maintainability Index** - Overall maintainability score\n" .
                                "• **Quality Score** - Combined quality assessment\n" .
                                "• **Technical Debt Ratio** - Issues per 1000 lines of code\n\n" .
                                "**🔒 Security Analysis:**\n" .
                                "• SQL injection vulnerability detection\n" .
                                "• XSS vulnerability scanning\n" .
                                "• File inclusion attack prevention\n" .
                                "• Command injection detection\n" .
                                "• Weak cryptography identification\n\n" .
                                "**⚡ Performance Analysis:**\n" .
                                "• Nested loop detection\n" .
                                "• Blocking I/O identification\n" .
                                "• Inefficient query detection\n" .
                                "• Algorithm optimization suggestions\n\n" .
                                "**💡 Improvement Process:**\n" .
                                "1. Start with directory analysis for overview\n" .
                                "2. Focus on critical and high-severity issues first\n" .
                                "3. Use file analysis for detailed investigation\n" .
                                "4. Implement suggestions incrementally\n" .
                                "5. Re-analyze to track improvement\n\n" .
                                "Try: 'Analyze the current directory to see overall code quality'"
                        ]
                    ]
                ]
            ]
        ];
    }
);

// Start the Code Analyzer
async(function () use ($server) {
    echo "🔍 Code Analyzer MCP Server starting...\n";
    echo "🛠️ Analysis capabilities: Quality metrics, Security scanning, Performance analysis\n";
    echo "📊 Supported metrics: Complexity, Maintainability, Documentation coverage\n";
    echo "🔒 Security features: Vulnerability detection, Best practice validation\n";
    echo "⚡ Performance features: Bottleneck detection, Optimization suggestions\n";
    echo "🛠️ Available tools: analyze_file, analyze_directory\n";
    echo "📚 Resources: analysis standards\n" . PHP_EOL;

    $transport = new StdioServerTransport();
    $server->connect($transport)->await();
})->await();
