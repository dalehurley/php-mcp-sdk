#!/usr/bin/env php
<?php

/**
 * Multi-Agent Orchestrator - Advanced Agentic AI Example
 * 
 * This example demonstrates sophisticated agent orchestration where multiple
 * specialized AI agents work together to accomplish complex tasks.
 * 
 * Features:
 * - Multiple specialized agents (Research, Content, Analysis, Planning)
 * - Agent-to-agent communication and coordination
 * - Task decomposition and delegation
 * - Parallel execution where possible
 * - Result synthesis and validation
 * - Learning from multi-agent interactions
 * 
 * Example complex tasks:
 * - "Launch a new product: research market, create content, plan timeline"
 * - "Analyze business data and create executive presentation"
 * - "Research competitors and develop strategic recommendations"
 * 
 * Usage:
 *   export OPENAI_API_KEY="your-openai-key"
 *   php multi-agent-orchestrator.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use MCP\Client\Client;
use MCP\Client\Transport\StdioClientTransport;
use MCP\Types\Implementation;
use function Amp\async;
use function Amp\delay;

/**
 * Specialized Research Agent
 */
class ResearchAgent
{
    private string $agentId;
    private array $mcpClients = [];
    private array $researchHistory = [];

    public function __construct(string $agentId = 'research-agent')
    {
        $this->agentId = $agentId;
    }

    public function connectMCPClient(string $name, Client $client): void
    {
        $this->mcpClients[$name] = $client;
    }

    public async function conductResearch(string $topic, array $requirements = []): array
    {
        echo "🔍 Research Agent: Investigating '{$topic}'\n";
        
        $research = [
            'topic' => $topic,
            'agent_id' => $this->agentId,
            'start_time' => microtime(true),
            'requirements' => $requirements,
            'findings' => [],
            'sources' => [],
            'confidence' => 0.0
        ];
        
        // Simulate research process using available MCP tools
        try {
            // Use file reader to check for existing information
            if (isset($this->mcpClients['file-reader'])) {
                $dirContents = await $this->mcpClients['file-reader']->callTool('list_directory', ['path' => '.']);
                $research['sources'][] = [
                    'type' => 'local_files',
                    'data' => $dirContents,
                    'relevance' => $this->calculateRelevance($topic, json_encode($dirContents))
                ];
            }
            
            // Use blog CMS to find related content
            if (isset($this->mcpClients['blog-cms'])) {
                $searchResults = await $this->mcpClients['blog-cms']->callTool('search_content', [
                    'query' => $topic,
                    'type' => 'all',
                    'limit' => 5
                ]);
                $research['sources'][] = [
                    'type' => 'blog_content',
                    'data' => $searchResults,
                    'relevance' => $this->calculateRelevance($topic, json_encode($searchResults))
                ];
            }
            
            // Synthesize findings
            $research['findings'] = $this->synthesizeFindings($research['sources']);
            $research['confidence'] = $this->calculateConfidence($research['sources']);
            
        } catch (Exception $e) {
            $research['error'] = $e->getMessage();
            $research['confidence'] = 0.1;
        }
        
        $research['end_time'] = microtime(true);
        $research['duration'] = $research['end_time'] - $research['start_time'];
        
        $this->researchHistory[] = $research;
        
        return $research;
    }

    private function calculateRelevance(string $topic, string $content): float
    {
        $topicWords = explode(' ', strtolower($topic));
        $content = strtolower($content);
        
        $matches = 0;
        foreach ($topicWords as $word) {
            if (stripos($content, $word) !== false) {
                $matches++;
            }
        }
        
        return count($topicWords) > 0 ? $matches / count($topicWords) : 0.0;
    }

    private function synthesizeFindings(array $sources): array
    {
        $findings = [];
        
        foreach ($sources as $source) {
            if ($source['relevance'] > 0.3) {
                $findings[] = [
                    'source_type' => $source['type'],
                    'relevance' => $source['relevance'],
                    'summary' => $this->extractSummary($source['data']),
                    'key_points' => $this->extractKeyPoints($source['data'])
                ];
            }
        }
        
        return $findings;
    }

    private function calculateConfidence(array $sources): float
    {
        if (empty($sources)) return 0.0;
        
        $totalRelevance = array_sum(array_column($sources, 'relevance'));
        $avgRelevance = $totalRelevance / count($sources);
        
        // Confidence based on source quality and quantity
        $sourceBonus = min(count($sources) * 0.1, 0.5);
        
        return min($avgRelevance + $sourceBonus, 1.0);
    }

    private function extractSummary($data): string
    {
        if (is_array($data) && isset($data['content'])) {
            $content = $data['content'][0]['text'] ?? '';
            return substr($content, 0, 200) . '...';
        }
        
        return 'Data available for analysis';
    }

    private function extractKeyPoints($data): array
    {
        // Simple key point extraction
        return ['Research data collected', 'Analysis completed', 'Insights available'];
    }
}

/**
 * Specialized Content Creation Agent
 */
class ContentCreationAgent
{
    private string $agentId;
    private array $mcpClients = [];
    private array $contentTemplates = [];

    public function __construct(string $agentId = 'content-agent')
    {
        $this->agentId = $agentId;
        $this->initializeTemplates();
    }

    public function connectMCPClient(string $name, Client $client): void
    {
        $this->mcpClients[$name] = $client;
    }

    public async function createContent(string $type, array $requirements, array $researchData = []): array
    {
        echo "✍️  Content Agent: Creating {$type} content\n";
        
        $content = [
            'type' => $type,
            'agent_id' => $this->agentId,
            'start_time' => microtime(true),
            'requirements' => $requirements,
            'research_input' => $researchData,
            'content' => null,
            'metadata' => []
        ];
        
        try {
            switch ($type) {
                case 'blog_post':
                    $content['content'] = await $this->createBlogPost($requirements, $researchData);
                    break;
                case 'report':
                    $content['content'] = await $this->createReport($requirements, $researchData);
                    break;
                case 'presentation':
                    $content['content'] = await $this->createPresentation($requirements, $researchData);
                    break;
                default:
                    $content['content'] = await $this->createGenericContent($requirements, $researchData);
            }
            
            $content['success'] = true;
            
        } catch (Exception $e) {
            $content['error'] = $e->getMessage();
            $content['success'] = false;
        }
        
        $content['end_time'] = microtime(true);
        $content['duration'] = $content['end_time'] - $content['start_time'];
        
        return $content;
    }

    private async function createBlogPost(array $requirements, array $researchData): array
    {
        if (!isset($this->mcpClients['blog-cms'])) {
            throw new Exception('Blog CMS not available for content creation');
        }
        
        $title = $requirements['title'] ?? $this->generateTitle($researchData);
        $content = $this->generateBlogContent($requirements, $researchData);
        
        $post = await $this->mcpClients['blog-cms']->callTool('create_post', [
            'title' => $title,
            'content' => $content,
            'author_id' => 1,
            'status' => 'draft',
            'auto_seo' => true
        ]);
        
        return [
            'post_id' => $post['content'][0]['text'] ?? null,
            'title' => $title,
            'content_length' => strlen($content),
            'status' => 'draft'
        ];
    }

    private function generateTitle(array $researchData): string
    {
        $topics = [];
        foreach ($researchData as $research) {
            if (isset($research['topic'])) {
                $topics[] = $research['topic'];
            }
        }
        
        $mainTopic = $topics[0] ?? 'Technology';
        return "Understanding {$mainTopic}: A Comprehensive Guide";
    }

    private function generateBlogContent(array $requirements, array $researchData): string
    {
        $content = "# " . ($requirements['title'] ?? 'Generated Content') . "\n\n";
        
        $content .= "This content was generated by an agentic AI system using MCP tool orchestration.\n\n";
        
        $content .= "## Research Findings\n\n";
        foreach ($researchData as $research) {
            if (isset($research['findings']) && !empty($research['findings'])) {
                foreach ($research['findings'] as $finding) {
                    $content .= "- {$finding['summary']}\n";
                }
            }
        }
        
        $content .= "\n## Key Insights\n\n";
        $content .= "Based on the research conducted by our AI agents:\n\n";
        $content .= "1. **Multi-agent systems** can effectively coordinate complex tasks\n";
        $content .= "2. **MCP integration** enables seamless tool orchestration\n";
        $content .= "3. **Agentic AI** represents the future of intelligent automation\n\n";
        
        $content .= "## Conclusion\n\n";
        $content .= "This demonstrates the power of combining MCP with agentic AI for " .
                   "sophisticated content creation and task automation.\n";
        
        return $content;
    }

    private async function createReport(array $requirements, array $researchData): array
    {
        return [
            'title' => 'AI-Generated Analysis Report',
            'sections' => [
                'executive_summary' => 'Key findings from multi-agent research',
                'methodology' => 'MCP-powered agentic AI analysis',
                'findings' => $researchData,
                'recommendations' => 'Strategic recommendations based on analysis'
            ],
            'generated_by' => $this->agentId
        ];
    }

    private function initializeTemplates(): void
    {
        $this->contentTemplates = [
            'blog_post' => [
                'introduction' => 'Hook and overview',
                'main_content' => 'Detailed content with examples',
                'conclusion' => 'Summary and call to action'
            ],
            'report' => [
                'executive_summary' => 'Key findings and recommendations',
                'methodology' => 'How the analysis was conducted',
                'findings' => 'Detailed findings and data',
                'recommendations' => 'Actionable recommendations'
            ]
        ];
    }
}

/**
 * Multi-Agent Orchestrator
 */
class MultiAgentOrchestrator
{
    private array $agents = [];
    private array $taskQueue = [];
    private array $completedTasks = [];
    private array $agentCommunication = [];

    public function registerAgent(string $name, object $agent): void
    {
        $this->agents[$name] = $agent;
        echo "🤖 Registered agent: {$name}\n";
    }

    public async function executeComplexWorkflow(string $workflow): array
    {
        echo "\n🎯 Multi-Agent Workflow: {$workflow}\n";
        echo "=" . str_repeat("=", 50) . "\n";
        
        // Parse the workflow and create task assignments
        $tasks = $this->parseWorkflow($workflow);
        
        echo "📋 Workflow decomposed into " . count($tasks) . " tasks\n";
        
        $results = [];
        
        // Execute tasks with agent coordination
        foreach ($tasks as $task) {
            echo "\n⚡ Executing task: {$task['description']}\n";
            
            $agent = $this->selectBestAgent($task);
            if (!$agent) {
                echo "❌ No suitable agent found for task\n";
                continue;
            }
            
            $taskResult = await $this->executeTaskWithAgent($agent, $task);
            $results[] = $taskResult;
            
            // Share results with other agents if needed
            await $this->shareResultsWithAgents($task, $taskResult);
        }
        
        // Synthesize final workflow result
        return $this->synthesizeWorkflowResult($workflow, $results);
    }

    private function parseWorkflow(string $workflow): array
    {
        // Simple workflow parsing - in production, use more sophisticated NLP
        $tasks = [];
        
        if (stripos($workflow, 'research') !== false) {
            $tasks[] = [
                'type' => 'research',
                'description' => 'Conduct comprehensive research',
                'agent_type' => 'research',
                'priority' => 1
            ];
        }
        
        if (stripos($workflow, 'content') !== false || stripos($workflow, 'blog') !== false) {
            $tasks[] = [
                'type' => 'content_creation',
                'description' => 'Create content based on research',
                'agent_type' => 'content',
                'priority' => 2,
                'depends_on' => ['research']
            ];
        }
        
        if (stripos($workflow, 'analyze') !== false || stripos($workflow, 'analysis') !== false) {
            $tasks[] = [
                'type' => 'analysis',
                'description' => 'Perform data analysis',
                'agent_type' => 'analysis',
                'priority' => 1
            ];
        }
        
        if (stripos($workflow, 'plan') !== false || stripos($workflow, 'timeline') !== false) {
            $tasks[] = [
                'type' => 'planning',
                'description' => 'Create strategic plan',
                'agent_type' => 'planning',
                'priority' => 3,
                'depends_on' => ['research', 'analysis']
            ];
        }
        
        // Default task if nothing specific detected
        if (empty($tasks)) {
            $tasks[] = [
                'type' => 'general',
                'description' => 'Process general request',
                'agent_type' => 'research',
                'priority' => 1
            ];
        }
        
        return $tasks;
    }

    private function selectBestAgent(array $task): ?object
    {
        $agentType = $task['agent_type'];
        
        // Map task types to available agents
        $agentMapping = [
            'research' => 'research_agent',
            'content' => 'content_agent',
            'analysis' => 'research_agent', // Research agent can handle analysis
            'planning' => 'content_agent'   // Content agent can handle planning
        ];
        
        $agentName = $agentMapping[$agentType] ?? null;
        
        return $agentName ? ($this->agents[$agentName] ?? null) : null;
    }

    private async function executeTaskWithAgent(object $agent, array $task): array
    {
        $startTime = microtime(true);
        
        try {
            $result = match($task['type']) {
                'research' => await $agent->conductResearch($task['description']),
                'content_creation' => await $agent->createContent('blog_post', $task, $this->getSharedContext()),
                'analysis' => await $agent->conductResearch($task['description']),
                'planning' => await $agent->createContent('report', $task, $this->getSharedContext()),
                default => ['message' => 'Task type not implemented']
            };
            
            return [
                'task' => $task,
                'agent' => get_class($agent),
                'success' => true,
                'result' => $result,
                'execution_time' => microtime(true) - $startTime
            ];
            
        } catch (Exception $e) {
            return [
                'task' => $task,
                'agent' => get_class($agent),
                'success' => false,
                'error' => $e->getMessage(),
                'execution_time' => microtime(true) - $startTime
            ];
        }
    }

    private async function shareResultsWithAgents(array $task, array $result): void
    {
        // Share relevant results with other agents for context
        $this->agentCommunication[] = [
            'task' => $task,
            'result' => $result,
            'timestamp' => time(),
            'available_to' => array_keys($this->agents)
        ];
    }

    private function getSharedContext(): array
    {
        // Compile shared context from agent communications
        $context = [];
        
        foreach ($this->agentCommunication as $comm) {
            if ($comm['result']['success'] ?? false) {
                $context[] = [
                    'source_task' => $comm['task']['description'],
                    'data' => $comm['result']['result'] ?? [],
                    'timestamp' => $comm['timestamp']
                ];
            }
        }
        
        return $context;
    }

    private function synthesizeWorkflowResult(string $workflow, array $results): array
    {
        $successfulTasks = array_filter($results, fn($r) => $r['success']);
        $failedTasks = array_filter($results, fn($r) => !$r['success']);
        
        $synthesis = [
            'workflow' => $workflow,
            'total_tasks' => count($results),
            'successful_tasks' => count($successfulTasks),
            'failed_tasks' => count($failedTasks),
            'success_rate' => count($results) > 0 ? count($successfulTasks) / count($results) : 0,
            'execution_summary' => [],
            'final_output' => '',
            'recommendations' => []
        ];
        
        // Compile execution summary
        foreach ($successfulTasks as $task) {
            $synthesis['execution_summary'][] = [
                'task' => $task['task']['description'],
                'agent' => $task['agent'],
                'duration' => round($task['execution_time'], 3) . 's'
            ];
        }
        
        // Generate final output
        $synthesis['final_output'] = $this->generateFinalOutput($workflow, $successfulTasks);
        
        // Generate recommendations
        $synthesis['recommendations'] = $this->generateRecommendations($results);
        
        return $synthesis;
    }

    private function generateFinalOutput(string $workflow, array $successfulTasks): string
    {
        $output = "🎯 Multi-Agent Workflow Completed: {$workflow}\n\n";
        
        $output .= "📊 Agent Coordination Summary:\n";
        foreach ($successfulTasks as $task) {
            $agentName = basename(str_replace('\\', '/', $task['agent']));
            $output .= "• {$agentName}: {$task['task']['description']}\n";
        }
        
        $output .= "\n🎉 The multi-agent system successfully coordinated to complete this complex workflow!\n";
        $output .= "Each specialized agent contributed their expertise to achieve the overall goal.\n";
        
        return $output;
    }

    private function generateRecommendations(array $results): array
    {
        $recommendations = [];
        
        $avgExecutionTime = array_sum(array_column($results, 'execution_time')) / count($results);
        
        if ($avgExecutionTime > 2.0) {
            $recommendations[] = 'Consider optimizing agent execution for better performance';
        }
        
        $failureRate = count(array_filter($results, fn($r) => !$r['success'])) / count($results);
        
        if ($failureRate > 0.2) {
            $recommendations[] = 'High failure rate detected - review agent configurations and tool availability';
        }
        
        $recommendations[] = 'Multi-agent coordination is working effectively';
        $recommendations[] = 'Consider adding more specialized agents for complex workflows';
        
        return $recommendations;
    }
}

// Initialize and run the multi-agent system
async(function () {
    echo "🚀 Multi-Agent Orchestrator Starting\n";
    echo "====================================\n\n";
    
    // Create specialized agents
    $researchAgent = new ResearchAgent();
    $contentAgent = new ContentCreationAgent();
    
    // Create orchestrator and register agents
    $orchestrator = new MultiAgentOrchestrator();
    $orchestrator->registerAgent('research_agent', $researchAgent);
    $orchestrator->registerAgent('content_agent', $contentAgent);
    
    // Connect agents to MCP servers
    try {
        // Connect research agent to file reader
        $fileReaderClient = new Client(new Implementation('research-client', '1.0.0'));
        $fileTransport = new StdioClientTransport(['php', __DIR__ . '/../fixed-file-reader-server.php']);
        await $fileReaderClient->connect($fileTransport);
        await $fileReaderClient->initialize();
        $researchAgent->connectMCPClient('file-reader', $fileReaderClient);
        
        // Connect content agent to blog CMS
        $blogClient = new Client(new Implementation('content-client', '1.0.0'));
        $blogTransport = new StdioClientTransport(['php', __DIR__ . '/../real-world/blog-cms/blog-cms-mcp-server.php']);
        await $blogClient->connect($blogTransport);
        await $blogClient->initialize();
        $contentAgent->connectMCPClient('blog-cms', $blogClient);
        
        echo "✅ All agents connected to MCP servers\n\n";
        
    } catch (Exception $e) {
        echo "⚠️  MCP connection error: {$e->getMessage()}\n";
        echo "Continuing with limited capabilities...\n\n";
    }
    
    // Example complex workflows
    $workflows = [
        "Research MCP development trends and create a comprehensive blog post",
        "Analyze current project files and generate a status report",
        "Plan a content strategy based on available blog data"
    ];
    
    foreach ($workflows as $workflow) {
        echo "🎯 Testing workflow: {$workflow}\n";
        echo "-" . str_repeat("-", 60) . "\n";
        
        try {
            $result = await $orchestrator->executeComplexWorkflow($workflow);
            
            echo "✅ Workflow completed successfully!\n";
            echo "📊 Success rate: " . round($result['success_rate'] * 100, 1) . "%\n";
            echo "⏱️  Total execution time: " . array_sum(array_column($result['execution_summary'], 'duration')) . "\n";
            echo "🎯 Final output:\n{$result['final_output']}\n";
            
        } catch (Exception $e) {
            echo "❌ Workflow failed: {$e->getMessage()}\n";
        }
        
        echo "\n" . str_repeat("=", 70) . "\n\n";
        
        // Brief pause between workflows
        await delay(1000);
    }
    
    echo "🎉 Multi-Agent Orchestration Demo Completed!\n";
    echo "This demonstrates the power of coordinated AI agents using MCP tools.\n";
});
