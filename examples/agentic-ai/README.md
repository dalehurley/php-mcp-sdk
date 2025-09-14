# Agentic AI Examples - MCP Powered Intelligent Agents

Welcome to the cutting-edge world of **Agentic AI** powered by the Model Context Protocol! These examples demonstrate how to build intelligent AI agents that can reason, plan, and execute complex tasks autonomously using MCP tools.

## 🤖 What is Agentic AI?

**Agentic AI** goes beyond simple tool calling. These agents can:

- 🧠 **Reason and Plan**: Break down complex tasks into manageable steps
- 🔧 **Tool Orchestration**: Intelligently select and chain MCP tools
- 🔄 **Self-Correction**: Adapt when things go wrong
- 📊 **Context Management**: Maintain state across multi-step operations
- 🎯 **Goal Achievement**: Focus on accomplishing objectives, not just responding

## 📚 Examples Overview

### 1. Working Agentic Demo

**File:** `working-agentic-demo.php`

A simplified but functional demonstration of agentic AI concepts.

**Features:**

- Task analysis and classification
- Rule-based planning
- Simulated multi-step execution
- Result synthesis and learning

**Usage:**

```bash
php working-agentic-demo.php
```

**What it demonstrates:**

- How agents analyze tasks to understand intent
- Planning strategies for different task types
- Execution coordination and error handling
- Response synthesis from multiple steps

### 2. Personal Assistant Agent

**File:** `personal-assistant-agent.php`

A comprehensive personal assistant that can handle complex, real-world tasks.

**Features:**

- Multi-MCP server integration
- Dynamic tool discovery
- Complex task decomposition
- Interactive mode for testing
- Context-aware execution

**Requirements:**

```bash
export OPENAI_API_KEY="your-openai-key"  # Optional - uses mock responses if not set
```

**Usage:**

```bash
php personal-assistant-agent.php
```

**Example tasks:**

- "Create a blog post about MCP development"
- "Analyze the current directory and generate a report"
- "Research AI trends and summarize findings"

### 3. Multi-Agent Orchestrator

**File:** `multi-agent-orchestrator.php`

Advanced example showing how multiple specialized agents work together.

**Features:**

- Specialized agent roles (Research, Content, Analysis)
- Agent-to-agent communication
- Parallel task execution
- Workflow decomposition
- Result synthesis across agents

**Usage:**

```bash
php multi-agent-orchestrator.php
```

**Example workflows:**

- "Research market trends and create strategic report"
- "Analyze data and generate executive presentation"
- "Plan product launch with timeline and tasks"

### 4. Simple Agentic Demo (Legacy)

**Files:** `simple-agentic-demo.php`

More complex example with OpenAI integration (may need API fixes).

## 🚀 Getting Started

### Quick Start

1. **Run the Working Demo:**

   ```bash
   php examples/agentic-ai/working-agentic-demo.php
   ```

2. **Try the Personal Assistant:**

   ```bash
   # Optional: Set OpenAI API key for enhanced capabilities
   export OPENAI_API_KEY="your-key-here"

   php examples/agentic-ai/personal-assistant-agent.php
   ```

3. **Explore Multi-Agent Coordination:**
   ```bash
   php examples/agentic-ai/multi-agent-orchestrator.php
   ```

### Prerequisites

- PHP 8.1+
- Composer dependencies installed
- Access to MCP servers (included in examples)
- Optional: OpenAI API key for enhanced AI reasoning

## 🧠 Key Concepts Demonstrated

### 1. Task Analysis

```php
// Agent analyzes task to understand intent
$analysis = $agent->analyzeTask("Research AI trends and create a blog post");
// Result: type='research', complexity='complex', steps=3
```

### 2. Strategic Planning

```php
// Agent creates execution plan
$plan = $agent->createExecutionPlan($task, $analysis);
// Result: Multi-step plan with tool selection and reasoning
```

### 3. Tool Orchestration

```php
// Agent executes tools in sequence
foreach ($plan['steps'] as $step) {
    $result = await $agent->executeStep($step);
    $agent->updateContext($step, $result);
}
```

### 4. Error Adaptation

```php
// Agent handles failures and finds alternatives
try {
    $result = await $client->callTool($tool, $params);
} catch (Exception $e) {
    $alternative = $agent->findAlternativeApproach($tool, $params);
    $result = await $client->callTool($alternative['tool'], $alternative['params']);
}
```

### 5. Result Synthesis

```php
// Agent combines results into coherent response
$finalResponse = $agent->synthesizeResponse($task, $plan, $results);
```

## 🎯 Real-World Applications

### Business Automation

- **Customer Support**: Multi-step issue resolution
- **Data Analysis**: Complex analytical workflows
- **Content Management**: Automated content creation
- **Project Management**: Task coordination and tracking

### Development Assistance

- **Code Review**: Multi-file analysis and suggestions
- **Documentation**: Auto-generation from codebases
- **Testing**: Intelligent test case generation
- **Deployment**: Automated deployment workflows

### Research and Analysis

- **Market Research**: Multi-source data gathering
- **Competitive Analysis**: Comprehensive competitor research
- **Trend Analysis**: Pattern identification and forecasting
- **Report Generation**: Automated insight compilation

## 🔧 Architecture Patterns

### Single Agent Pattern

```php
$agent = new AgenticAgent();
await $agent->initialize();
$result = await $agent->processComplexTask($userRequest);
```

### Multi-Agent Pattern

```php
$orchestrator = new MultiAgentOrchestrator();
$orchestrator->registerAgent('research', new ResearchAgent());
$orchestrator->registerAgent('content', new ContentAgent());
$result = await $orchestrator->executeWorkflow($complexWorkflow);
```

### Agent Specialization

```php
// Specialized agents for specific domains
$researchAgent = new ResearchAgent();      // Information gathering
$contentAgent = new ContentCreationAgent(); // Content generation
$analysisAgent = new DataAnalysisAgent();   // Data processing
$planningAgent = new PlanningAgent();       // Strategic planning
```

## 🧪 Testing and Development

### Running Tests

```bash
# Test individual examples
php working-agentic-demo.php

# Test with real MCP servers
php personal-assistant-agent.php

# Test multi-agent coordination
php multi-agent-orchestrator.php
```

### Development Tips

1. **Start Simple**: Begin with rule-based reasoning before adding LLM integration
2. **Test Incrementally**: Test each component (planning, execution, synthesis) separately
3. **Handle Failures**: Always plan for tool failures and network issues
4. **Context Management**: Keep context relevant and clean up stale data
5. **Performance**: Consider async execution for parallel operations

### Debugging

Enable verbose mode for detailed execution logs:

```php
$agent = new AgenticAgent(verbose: true);
```

Monitor tool execution:

```php
$agent->onToolExecution(function($tool, $params, $result) {
    echo "Tool: {$tool}, Result: " . json_encode($result) . "\n";
});
```

## 🔮 Advanced Patterns

### Learning Agents

```php
class LearningAgent extends AgenticAgent {
    public function learnFromExecution(array $execution) {
        // Analyze what worked and what didn't
        // Improve future planning based on results
    }
}
```

### Collaborative Agents

```php
class CollaborativeAgent extends AgenticAgent {
    public function shareKnowledge(AgenticAgent $otherAgent) {
        // Share execution history and learned patterns
        // Coordinate on complex tasks
    }
}
```

### Adaptive Agents

```php
class AdaptiveAgent extends AgenticAgent {
    public function adaptToEnvironment() {
        // Discover new tools and capabilities
        // Adjust strategies based on available resources
    }
}
```

## 📖 Related Documentation

- [Agentic AI Agents Tutorial](../../docs/tutorials/specialized/agentic-ai-agents.md)
- [OpenAI Tool Calling Guide](../../docs/guides/integrations/openai-tool-calling.md)
- [MCP Client Development](../../docs/guides/client-development/creating-clients.md)
- [Error Handling Patterns](../../docs/guides/client-development/error-handling.md)

## 🎓 Learning Path

1. **Start with Working Demo** - Understand basic concepts
2. **Try Personal Assistant** - See real MCP integration
3. **Explore Multi-Agent** - Learn coordination patterns
4. **Build Your Own** - Create domain-specific agents
5. **Add AI Reasoning** - Integrate with OpenAI or other LLMs

## 🌟 Next Steps

After exploring these examples:

1. **Integrate with your MCP servers** - Connect agents to your existing tools
2. **Add AI reasoning** - Integrate with OpenAI, Anthropic, or local LLMs
3. **Build specialized agents** - Create agents for your specific use cases
4. **Scale to production** - Use the patterns for real applications
5. **Contribute back** - Share your agent patterns with the community

## 🚨 Important Notes

- These examples use mock AI reasoning for demonstration
- Production agents should integrate with real LLM providers
- Always validate tool outputs and handle errors gracefully
- Consider rate limiting and cost management for LLM usage
- Security: Validate all inputs and outputs in production

---

**Welcome to the future of AI development!** 🚀

These agentic AI patterns represent the cutting edge of AI application development, showing how MCP enables truly intelligent, autonomous systems that can reason, plan, and execute complex tasks just like human assistants.

The combination of MCP's tool orchestration with agentic AI reasoning creates unprecedented possibilities for automation, assistance, and intelligent system integration.
