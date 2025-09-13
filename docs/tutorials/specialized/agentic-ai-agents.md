# Build Your PHP MCP Powered Agentic AI Agent

Welcome to the future of AI development! In this comprehensive tutorial, you'll learn how to build **agentic AI agents** that can intelligently use MCP tools to accomplish complex, multi-step tasks autonomously.

## 🎯 What You'll Build

By the end of this tutorial, you'll have created:

- 🤖 **Intelligent AI Agent** - An agent that can reason and plan
- 🔧 **Tool Orchestration** - Smart tool selection and chaining
- 🧠 **Multi-Step Reasoning** - Breaking down complex tasks
- 🔄 **Self-Correction** - Learning from errors and adapting
- 📊 **Context Management** - Maintaining state across operations
- 🎛️ **Agent Orchestration** - Coordinating multiple agents

## 🧠 Understanding Agentic AI

### What Makes an AI "Agentic"?

**Traditional AI Tools:**

```
User Request → AI → Single Tool Call → Response
```

**Agentic AI:**

```
User Request → AI Agent → Planning → Tool Chain → Validation → Response
                ↑                      ↓
            Self-Correction ←── Error Handling
```

### Key Characteristics

1. **🎯 Goal-Oriented**: Focuses on achieving objectives, not just responding
2. **🧩 Problem Decomposition**: Breaks complex tasks into manageable steps
3. **🔄 Iterative Execution**: Learns and adapts during execution
4. **🛠️ Tool Mastery**: Intelligently selects and combines tools
5. **🔍 Self-Monitoring**: Validates results and corrects course

## 🏗️ Architecture Overview

### Agent Components

```php
// Core Agent Architecture
class AgenticAI {
    private PlanningEngine $planner;      // Breaks down tasks
    private ToolOrchestrator $orchestrator; // Manages tool calls
    private ContextManager $context;       // Maintains state
    private ErrorHandler $errorHandler;   // Handles failures
    private LLMProvider $llm;            // AI reasoning engine
}
```

### MCP Integration Points

1. **Tool Discovery**: Agent discovers available MCP tools
2. **Dynamic Planning**: Plans tool usage based on capabilities
3. **Execution Engine**: Executes tool chains intelligently
4. **Result Validation**: Validates outputs and adjusts plans
5. **Context Sharing**: Shares context between tool calls

## 🚀 Building Your First Agentic AI Agent

### Step 1: Create the Agent Foundation

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use MCP\Client\Client;
use MCP\Client\Transport\StdioClientTransport;
use MCP\Types\Implementation;
use OpenAI\Client as OpenAIClient;
use function Amp\async;

class AgenticMCPAgent
{
    private Client $mcpClient;
    private OpenAIClient $openaiClient;
    private array $availableTools = [];
    private array $conversationHistory = [];
    private array $executionContext = [];

    public function __construct(
        Client $mcpClient,
        OpenAIClient $openaiClient
    ) {
        $this->mcpClient = $mcpClient;
        $this->openaiClient = $openaiClient;
    }

    public async function initialize(): void
    {
        // Connect to MCP server
        await $this->mcpClient->initialize();

        // Discover available tools
        $toolsResponse = await $this->mcpClient->listTools();
        $this->availableTools = $toolsResponse['tools'];

        echo "🤖 Agent initialized with " . count($this->availableTools) . " tools\n";
    }

    public async function processRequest(string $userRequest): string
    {
        // 1. Plan the task
        $plan = await $this->createPlan($userRequest);

        // 2. Execute the plan
        $result = await $this->executePlan($plan);

        // 3. Generate response
        return await $this->generateResponse($userRequest, $result);
    }
}
```

### Step 2: Implement Planning Engine

```php
class PlanningEngine
{
    private OpenAIClient $llm;
    private array $availableTools;

    public async function createPlan(string $request, array $tools): array
    {
        $toolDescriptions = $this->formatToolsForLLM($tools);

        $prompt = "
You are an AI planning agent. Given a user request and available tools,
create a step-by-step execution plan.

User Request: {$request}

Available Tools:
{$toolDescriptions}

Create a JSON plan with steps that use the available tools to fulfill the request.
Each step should specify:
- tool: name of the tool to use
- parameters: parameters to pass to the tool
- reasoning: why this step is needed
- depends_on: previous steps this depends on (if any)

Return only valid JSON.
        ";

        $response = await $this->llm->chat()->create([
            'model' => 'gpt-4',
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.1
        ]);

        return json_decode($response->choices[0]->message->content, true);
    }
}
```

### Step 3: Build Tool Orchestrator

```php
class ToolOrchestrator
{
    private Client $mcpClient;
    private array $executionResults = [];

    public async function executeStep(array $step): array
    {
        try {
            // Resolve parameters from previous steps
            $parameters = $this->resolveParameters(
                $step['parameters'],
                $this->executionResults
            );

            // Execute the tool
            $result = await $this->mcpClient->callTool(
                $step['tool'],
                $parameters
            );

            // Store result for future steps
            $this->executionResults[$step['id']] = $result;

            return [
                'success' => true,
                'result' => $result,
                'step' => $step
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'step' => $step
            ];
        }
    }

    private function resolveParameters(array $params, array $results): array
    {
        // Resolve references like {{step_1.result.data}}
        array_walk_recursive($params, function(&$value) use ($results) {
            if (is_string($value) && preg_match('/\{\{(.+)\}\}/', $value, $matches)) {
                $path = explode('.', $matches[1]);
                $resolved = $results;

                foreach ($path as $key) {
                    $resolved = $resolved[$key] ?? null;
                    if ($resolved === null) break;
                }

                $value = $resolved ?? $value;
            }
        });

        return $params;
    }
}
```

## 🎯 Real-World Example: Personal Assistant Agent

Let's build a complete personal assistant agent that can:

- Manage your calendar and tasks
- Research topics and summarize findings
- Analyze data and generate reports
- Coordinate multiple tools intelligently

### Complete Agent Implementation

```php
#!/usr/bin/env php
<?php

/**
 * Personal Assistant Agentic AI Agent
 *
 * This agent can:
 * - Plan and execute multi-step tasks
 * - Research topics using multiple sources
 * - Manage calendar and tasks
 * - Generate reports and summaries
 * - Learn from interactions and improve
 */

require_once __DIR__ . '/vendor/autoload.php';

use MCP\Client\Client;
use MCP\Client\Transport\StdioClientTransport;
use MCP\Types\Implementation;
use OpenAI\Client as OpenAIClient;
use function Amp\async;

class PersonalAssistantAgent
{
    private Client $mcpClient;
    private OpenAIClient $openaiClient;
    private array $tools = [];
    private array $context = [];
    private array $memory = [];

    public function __construct()
    {
        // Initialize OpenAI client
        $this->openaiClient = OpenAI::client($_ENV['OPENAI_API_KEY']);

        // Initialize MCP client
        $this->mcpClient = new Client(
            new Implementation('personal-assistant-agent', '1.0.0')
        );
    }

    public async function start(): void
    {
        echo "🤖 Personal Assistant Agent starting...\n";

        // Connect to MCP servers
        await $this->connectToMCPServers();

        // Start interactive mode
        await $this->interactiveMode();
    }

    private async function connectToMCPServers(): void
    {
        $servers = [
            'calculator' => ['php', __DIR__ . '/basic-calculator.php'],
            'file-reader' => ['php', __DIR__ . '/file-reader-server.php'],
            'blog-cms' => ['php', __DIR__ . '/blog-cms-server.php']
        ];

        foreach ($servers as $name => $command) {
            try {
                $transport = new StdioClientTransport($command);
                await $this->mcpClient->connect($transport);
                await $this->mcpClient->initialize();

                $tools = await $this->mcpClient->listTools();
                $this->tools[$name] = $tools['tools'];

                echo "✅ Connected to {$name} server\n";
            } catch (Exception $e) {
                echo "⚠️  Failed to connect to {$name}: {$e->getMessage()}\n";
            }
        }
    }

    public async function processComplexTask(string $task): string
    {
        echo "\n🎯 Processing complex task: {$task}\n";

        // 1. Create execution plan
        $plan = await $this->createExecutionPlan($task);
        echo "📋 Execution plan created with " . count($plan['steps']) . " steps\n";

        // 2. Execute plan step by step
        $results = [];
        foreach ($plan['steps'] as $step) {
            echo "⚡ Executing: {$step['description']}\n";

            $stepResult = await $this->executeStep($step);
            $results[] = $stepResult;

            // Update context for next steps
            $this->updateContext($step, $stepResult);
        }

        // 3. Synthesize final response
        return await $this->synthesizeResponse($task, $results);
    }

    private async function createExecutionPlan(string $task): array
    {
        $toolsDescription = $this->formatToolsForPlanning();

        $planningPrompt = "
You are an AI planning agent. Create a detailed execution plan for this task:

TASK: {$task}

AVAILABLE TOOLS:
{$toolsDescription}

Create a step-by-step plan that uses the available tools effectively.
Return a JSON plan with this structure:
{
    \"analysis\": \"your analysis of the task\",
    \"strategy\": \"your overall strategy\",
    \"steps\": [
        {
            \"id\": 1,
            \"description\": \"what this step does\",
            \"tool\": \"tool_name\",
            \"parameters\": {\"param\": \"value\"},
            \"reasoning\": \"why this step is needed\",
            \"depends_on\": []
        }
    ]
}

Think step by step and be thorough.
        ";

        $response = $this->openaiClient->chat()->create([
            'model' => 'gpt-4',
            'messages' => [
                ['role' => 'system', 'content' => 'You are an expert AI planning agent.'],
                ['role' => 'user', 'content' => $planningPrompt]
            ],
            'temperature' => 0.2
        ]);

        return json_decode($response->choices[0]->message->content, true);
    }

    private async function executeStep(array $step): array
    {
        try {
            // Resolve dynamic parameters
            $parameters = $this->resolveDynamicParameters($step['parameters']);

            // Execute the tool
            $result = await $this->mcpClient->callTool($step['tool'], $parameters);

            return [
                'step_id' => $step['id'],
                'success' => true,
                'result' => $result,
                'execution_time' => microtime(true)
            ];

        } catch (Exception $e) {
            // Handle errors and potentially retry or adapt
            return await $this->handleStepError($step, $e);
        }
    }

    private async function handleStepError(array $step, Exception $error): array
    {
        echo "❌ Step failed: {$error->getMessage()}\n";

        // Try to create an alternative approach
        $adaptationPrompt = "
The following step failed:
Step: {$step['description']}
Tool: {$step['tool']}
Error: {$error->getMessage()}

Available tools: " . json_encode(array_keys($this->tools)) . "

Suggest an alternative approach or modified parameters.
Return JSON with: {\"alternative\": \"description\", \"tool\": \"tool_name\", \"parameters\": {}}
        ";

        $response = $this->openaiClient->chat()->create([
            'model' => 'gpt-4',
            'messages' => [
                ['role' => 'user', 'content' => $adaptationPrompt]
            ],
            'temperature' => 0.3
        ]);

        $adaptation = json_decode($response->choices[0]->message->content, true);

        if ($adaptation && isset($adaptation['tool'])) {
            echo "🔄 Trying alternative approach: {$adaptation['alternative']}\n";

            try {
                $result = await $this->mcpClient->callTool(
                    $adaptation['tool'],
                    $adaptation['parameters']
                );

                return [
                    'step_id' => $step['id'],
                    'success' => true,
                    'result' => $result,
                    'adapted' => true,
                    'original_error' => $error->getMessage()
                ];
            } catch (Exception $e2) {
                // Final failure
                return [
                    'step_id' => $step['id'],
                    'success' => false,
                    'error' => $error->getMessage(),
                    'adaptation_error' => $e2->getMessage()
                ];
            }
        }

        return [
            'step_id' => $step['id'],
            'success' => false,
            'error' => $error->getMessage()
        ];
    }
}
```

## 🔧 Advanced Agent Patterns

### 1. Research and Analysis Agent

```php
class ResearchAgent extends AgenticMCPAgent
{
    public async function conductResearch(string $topic): array
    {
        $plan = [
            'search_web' => ['query' => $topic, 'sources' => 5],
            'analyze_content' => ['content' => '{{search_web.results}}'],
            'extract_insights' => ['analysis' => '{{analyze_content.summary}}'],
            'generate_report' => ['insights' => '{{extract_insights.data}}']
        ];

        return await $this->executePlan($plan);
    }
}
```

### 2. Content Creation Agent

```php
class ContentCreationAgent extends AgenticMCPAgent
{
    public async function createBlogPost(string $topic, string $audience): array
    {
        return await $this->processComplexTask("
Create a comprehensive blog post about '{$topic}' for {$audience}.

Requirements:
1. Research the topic thoroughly
2. Create an engaging title and outline
3. Write the full content with examples
4. Optimize for SEO
5. Generate meta descriptions and tags
6. Create a publishing schedule
        ");
    }
}
```

### 3. Data Analysis Agent

```php
class DataAnalysisAgent extends AgenticMCPAgent
{
    public async function analyzeDataset(string $dataPath): array
    {
        $plan = [
            'load_data' => ['path' => $dataPath],
            'examine_structure' => ['data' => '{{load_data.content}}'],
            'statistical_analysis' => ['dataset' => '{{examine_structure.parsed}}'],
            'identify_patterns' => ['stats' => '{{statistical_analysis.results}}'],
            'generate_insights' => ['patterns' => '{{identify_patterns.findings}}'],
            'create_visualizations' => ['insights' => '{{generate_insights.summary}}'],
            'compile_report' => ['analysis' => '{{create_visualizations.charts}}']
        ];

        return await $this->executePlan($plan);
    }
}
```

## 🎛️ Agent Orchestration

### Multi-Agent Coordination

```php
class AgentOrchestrator
{
    private array $agents = [];
    private array $taskQueue = [];
    private array $results = [];

    public function registerAgent(string $name, AgenticMCPAgent $agent): void
    {
        $this->agents[$name] = $agent;
    }

    public async function coordinateTask(string $complexTask): array
    {
        // Break down into agent-specific subtasks
        $subtasks = await $this->decomposeTask($complexTask);

        // Assign tasks to appropriate agents
        $assignments = await $this->assignTasks($subtasks);

        // Execute tasks in parallel where possible
        $results = await $this->executeCoordinatedTasks($assignments);

        // Synthesize final result
        return await $this->synthesizeResults($results);
    }
}
```

## 🧪 Practical Examples

### Example 1: "Plan my product launch"

**User Request:** "Help me plan a product launch for my new MCP library"

**Agent Execution:**

1. **Research Phase**

   - Analyze competitor launches
   - Research target audience
   - Identify key channels

2. **Planning Phase**

   - Create timeline
   - Define milestones
   - Allocate resources

3. **Content Phase**

   - Generate launch materials
   - Create social media content
   - Draft press releases

4. **Coordination Phase**
   - Schedule activities
   - Set up tracking
   - Prepare metrics

### Example 2: "Analyze my blog performance"

**User Request:** "Analyze my blog's performance and suggest improvements"

**Agent Execution:**

1. **Data Collection**

   - Gather blog statistics
   - Collect user engagement data
   - Analyze content performance

2. **Analysis Phase**

   - Identify trending topics
   - Analyze user behavior
   - Compare with benchmarks

3. **Insight Generation**

   - Find optimization opportunities
   - Identify content gaps
   - Suggest improvements

4. **Action Planning**
   - Create improvement roadmap
   - Generate content ideas
   - Set performance targets

## 🔄 Advanced Features

### Self-Learning and Adaptation

```php
class LearningAgent extends AgenticMCPAgent
{
    private array $experienceMemory = [];

    public function recordExperience(string $task, array $plan, array $results): void
    {
        $this->experienceMemory[] = [
            'task_type' => $this->classifyTask($task),
            'plan' => $plan,
            'results' => $results,
            'success_rate' => $this->calculateSuccessRate($results),
            'lessons_learned' => $this->extractLessons($plan, $results),
            'timestamp' => time()
        ];
    }

    public function improveFromExperience(string $newTask): array
    {
        $similarExperiences = $this->findSimilarExperiences($newTask);
        $improvements = $this->synthesizeLearnings($similarExperiences);

        return $improvements;
    }
}
```

### Context-Aware Execution

```php
class ContextAwareAgent extends AgenticMCPAgent
{
    private array $contextLayers = [
        'user_preferences' => [],
        'session_state' => [],
        'historical_context' => [],
        'environmental_context' => []
    ];

    public function updateContext(string $layer, array $data): void
    {
        $this->contextLayers[$layer] = array_merge(
            $this->contextLayers[$layer],
            $data
        );
    }

    public function getRelevantContext(string $task): array
    {
        // AI-powered context selection
        $relevantContext = [];

        foreach ($this->contextLayers as $layer => $data) {
            $relevance = $this->calculateContextRelevance($task, $data);
            if ($relevance > 0.5) {
                $relevantContext[$layer] = $data;
            }
        }

        return $relevantContext;
    }
}
```

## 🚀 Getting Started

### Quick Start: Build Your First Agent

1. **Set up the environment:**

   ```bash
   composer require dalehurley/php-mcp-sdk openai-php/client
   ```

2. **Create your agent:**

   ```php
   $agent = new PersonalAssistantAgent();
   await $agent->initialize();
   ```

3. **Give it a complex task:**
   ```php
   $result = await $agent->processComplexTask(
       "Research the latest trends in AI development and create a summary report"
   );
   ```

### Integration with Existing MCP Servers

Your agent can work with any MCP server:

```php
// Connect to multiple specialized servers
$agent->connectToServer('database', ['php', 'database-server.php']);
$agent->connectToServer('email', ['php', 'email-server.php']);
$agent->connectToServer('analytics', ['php', 'analytics-server.php']);

// Agent automatically discovers and uses all available tools
$result = await $agent->processComplexTask(
    "Analyze user engagement data and send a weekly report to the team"
);
```

## 🎯 Use Cases

### Business Automation

- **Customer Support**: Multi-step issue resolution
- **Data Analysis**: Complex analytical workflows
- **Content Management**: Automated content workflows
- **Project Management**: Task coordination and tracking

### Development Assistance

- **Code Review**: Multi-file analysis and suggestions
- **Documentation**: Auto-generation from codebases
- **Testing**: Intelligent test case generation
- **Deployment**: Automated deployment workflows

### Personal Productivity

- **Research Assistant**: Deep topic research and synthesis
- **Task Management**: Intelligent task prioritization
- **Calendar Management**: Smart scheduling and planning
- **Information Processing**: Document analysis and summarization

## 🧠 Best Practices

### 1. Planning Strategy

- **Break down complex tasks** into manageable steps
- **Consider dependencies** between steps
- **Plan for error scenarios** and alternatives
- **Validate assumptions** before execution

### 2. Tool Selection

- **Understand tool capabilities** deeply
- **Consider tool combinations** for complex operations
- **Handle tool failures** gracefully
- **Optimize tool usage** for performance

### 3. Context Management

- **Maintain relevant context** across operations
- **Clean up stale context** to prevent confusion
- **Share context appropriately** between agents
- **Protect sensitive information** in context

### 4. Error Handling

- **Implement retry logic** with exponential backoff
- **Provide alternative approaches** when tools fail
- **Learn from failures** to improve future performance
- **Graceful degradation** when partial failure occurs

## 🔮 Advanced Topics

### Multi-Modal Agents

- Combining text, image, and data processing
- Cross-modal reasoning and synthesis
- Unified context across modalities

### Distributed Agent Systems

- Agent-to-agent communication
- Hierarchical agent structures
- Consensus and coordination protocols

### Learning and Adaptation

- Experience-based improvement
- Dynamic strategy adjustment
- Performance optimization over time

## 🎉 Next Steps

After completing this tutorial:

1. **Experiment** with different agent architectures
2. **Build specialized agents** for your specific use cases
3. **Create agent networks** for complex coordination
4. **Integrate with production systems** using the patterns shown
5. **Contribute** your agent patterns to the community

Welcome to the future of AI development with MCP-powered agentic systems! 🚀

## 📚 Additional Resources

- [OpenAI Function Calling Guide](../guides/integrations/openai-tool-calling.md)
- [MCP Client Development](../guides/client-development/creating-clients.md)
- [Error Handling Patterns](../guides/client-development/error-handling.md)
- [Working Examples](../../examples/agentic-ai/)

---

_This tutorial demonstrates the cutting-edge intersection of MCP and agentic AI, showing how to build intelligent systems that can reason, plan, and execute complex tasks autonomously._
