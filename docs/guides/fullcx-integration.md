# FullCX Integration Guide

This guide demonstrates how to connect to and interact with the FullCX MCP server using the PHP MCP SDK, including integration with OpenAI for AI-powered product management workflows.

## Overview

The FullCX MCP server provides comprehensive product management capabilities including:

- **Product Management**: List products, get detailed product information
- **Feature Tracking**: Manage features within products, track development progress
- **Requirement Management**: Create and track requirements with acceptance criteria
- **Idea Management**: Capture and manage product ideas and enhancements
- **Status Updates**: Update statuses across all entities
- **AI Integration**: Leverage OpenAI for intelligent content generation and analysis

## Prerequisites

- PHP 8.1+ with `json`, `mbstring` extensions
- Composer installed
- FullCX account with API access
- Valid FullCX authentication token
- OpenAI API key (optional, for AI features)

## Installation

If you haven't installed the PHP MCP SDK yet:

```bash
composer require dalehurley/php-mcp-sdk
```

For OpenAI integration, also install the OpenAI PHP client:

```bash
composer require openai-php/client
```

## Quick Start

### Step 1: Basic Connection

Create a simple connection to FullCX:

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use FullCX\MCP\FullCXClient;
use MCP\Shared\RequestOptions;

// Create client with correct FullCX URL
$client = new FullCXClient(
    url: 'https://full.cx/mcp',                    // Correct FullCX URL
    bearerToken: 'your-api-token-here'             // Replace with your token
);

// Connect to the server
$client->connect()->await();

// List available tools to verify connection
$tools = $client->listTools()->await();
echo "Connected! Available tools: " . count($tools->getTools()) . "\n";

// Close connection
$client->close()->await();
```

### Step 2: Exploring Products

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use FullCX\MCP\FullCXClient;
use Amp\Loop;

$client = new FullCXClient(
    url: 'https://full.cx/mcp',
    bearerToken: 'your-api-token-here'
);

Loop::run(function() use ($client) {
    try {
        // Connect to FullCX
        yield $client->connect();
        echo "✅ Connected to FullCX!\n\n";

        // List all products
        echo "📦 Products:\n";
        $products = yield $client->listProducts(limit: 10);

        $productData = json_decode($products['content'][0]['text'], true);
        foreach ($productData as $product) {
            echo "  - {$product['name']} (ID: {$product['id']})\n";
            echo "    {$product['description']}\n\n";
        }

        // Get detailed information about the first product
        if (!empty($productData)) {
            $firstProduct = $productData[0];
            $productId = $firstProduct['id'];

            echo "🔍 Product Details for: {$firstProduct['name']}\n";
            $details = yield $client->getProductDetails($productId, includeRelated: true);

            $detailData = json_decode($details['content'][0]['text'], true);
            echo "  Features: {$detailData['feature_count']}\n";
            echo "  Requirements: {$detailData['requirement_count']}\n";
            echo "  Ideas: {$detailData['idea_count']}\n\n";
        }

    } catch (\Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    } finally {
        yield $client->close();
    }
});
```

## AI-Enhanced Product Management

### Step 3: OpenAI Integration Setup

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use FullCX\MCP\FullCXClient;
use OpenAI;
use Amp\Loop;

class AIEnhancedProductManager
{
    private FullCXClient $fullcx;
    private OpenAI\Client $openai;

    public function __construct(string $fullcxToken, string $openaiKey)
    {
        $this->fullcx = new FullCXClient(
            url: 'https://full.cx/mcp',
            bearerToken: $fullcxToken
        );

        $this->openai = OpenAI::client($openaiKey);
    }

    /**
     * Generate AI-powered product descriptions
     */
    public function generateProductDescription(array $productData): string
    {
        $prompt = "Based on the following product information, create a compelling and detailed product description:\n\n";
        $prompt .= "Product Name: {$productData['name']}\n";
        $prompt .= "Current Description: {$productData['description']}\n";
        $prompt .= "Features: " . ($productData['feature_count'] ?? 0) . "\n";
        $prompt .= "Requirements: " . ($productData['requirement_count'] ?? 0) . "\n";
        $prompt .= "\nPlease create a marketing-focused description that highlights the value proposition.";

        $response = $this->openai->chat()->create([
            'model' => 'gpt-4.1',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a product marketing expert who creates compelling product descriptions.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => 300,
            'temperature' => 0.7
        ]);

        return $response->choices[0]->message->content;
    }

    /**
     * Generate user stories from requirements
     */
    public function generateUserStories(array $requirementData): array
    {
        $stories = [];

        foreach ($requirementData as $requirement) {
            $prompt = "Convert this requirement into a well-structured user story:\n\n";
            $prompt .= "Requirement: {$requirement['name']}\n";
            $prompt .= "Description: {$requirement['description']}\n";
            $prompt .= "\nFormat: As a [user type], I want [goal] so that [benefit].";

            $response = $this->openai->chat()->create([
                'model' => 'gpt-4.1',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a product manager who writes clear user stories following best practices.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'max_tokens' => 150,
                'temperature' => 0.5
            ]);

            $stories[] = [
                'requirement_id' => $requirement['id'],
                'requirement_name' => $requirement['name'],
                'user_story' => $response->choices[0]->message->content
            ];
        }

        return $stories;
    }

    /**
     * Analyze product portfolio and generate insights
     */
    public function analyzeProductPortfolio(): \Generator
    {
        yield $this->fullcx->connect();

        try {
            // Get all products
            $products = yield $this->fullcx->listProducts(limit: 50);
            $productData = json_decode($products['content'][0]['text'], true);

            // Prepare data for AI analysis
            $portfolioSummary = "Product Portfolio Analysis:\n\n";
            foreach ($productData as $product) {
                $details = yield $this->fullcx->getProductDetails($product['id']);
                $detailData = json_decode($details['content'][0]['text'], true);

                $portfolioSummary .= "Product: {$product['name']}\n";
                $portfolioSummary .= "Description: {$product['description']}\n";
                $portfolioSummary .= "Features: {$detailData['feature_count']}\n";
                $portfolioSummary .= "Requirements: {$detailData['requirement_count']}\n";
                $portfolioSummary .= "Ideas: {$detailData['idea_count']}\n\n";
            }

            // Get AI insights
            $response = $this->openai->chat()->create([
                'model' => 'gpt-4.1',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a senior product strategist who analyzes product portfolios and provides actionable insights.'],
                    ['role' => 'user', 'content' => $portfolioSummary . "\nProvide strategic insights, identify gaps, and suggest improvements for this product portfolio."]
                ],
                'max_tokens' => 800,
                'temperature' => 0.6
            ]);

            return $response->choices[0]->message->content;

        } finally {
            yield $this->fullcx->close();
        }
    }
}

// Usage example
$manager = new AIEnhancedProductManager(
    fullcxToken: $_ENV['FULLCX_API_TOKEN'],
    openaiKey: $_ENV['OPENAI_API_KEY']
);

Loop::run(function() use ($manager) {
    $insights = yield $manager->analyzeProductPortfolio();
    echo "🤖 AI Portfolio Insights:\n";
    echo $insights . "\n";
});
```

### Step 4: AI-Powered Feature Planning

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use FullCX\MCP\FullCXClient;
use OpenAI;
use Amp\Loop;

class AIFeaturePlanner
{
    private FullCXClient $fullcx;
    private OpenAI\Client $openai;

    public function __construct(string $fullcxToken, string $openaiKey)
    {
        $this->fullcx = new FullCXClient(
            url: 'https://full.cx/mcp',
            bearerToken: $fullcxToken
        );

        $this->openai = OpenAI::client($openaiKey);
    }

    /**
     * Generate feature breakdown from high-level idea
     */
    public function planFeature(string $productId, string $featureIdea): \Generator
    {
        yield $this->fullcx->connect();

        try {
            // Get product context
            $product = yield $this->fullcx->getProductDetails($productId);
            $productData = json_decode($product['content'][0]['text'], true);

            // Generate comprehensive feature plan
            $prompt = "Create a detailed feature plan for the following idea:\n\n";
            $prompt .= "Product: {$productData['name']}\n";
            $prompt .= "Product Description: {$productData['description']}\n";
            $prompt .= "Feature Idea: {$featureIdea}\n\n";
            $prompt .= "Please provide:\n";
            $prompt .= "1. Feature name and description\n";
            $prompt .= "2. 3-5 specific requirements\n";
            $prompt .= "3. Acceptance criteria for each requirement\n";
            $prompt .= "4. Implementation priority (1-5)\n";
            $prompt .= "5. Estimated effort (1-10)\n";
            $prompt .= "6. Business impact (1-10)\n";
            $prompt .= "\nFormat as structured JSON.";

            $response = $this->openai->chat()->create([
                'model' => 'gpt-4.1',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a senior product manager who creates detailed feature specifications. Always respond with valid JSON.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'max_tokens' => 1500,
                'temperature' => 0.3
            ]);

            $featurePlan = json_decode($response->choices[0]->message->content, true);

            // Create the feature in FullCX
            $feature = yield $this->fullcx->createFeature(
                productId: $productId,
                name: $featurePlan['name'],
                description: $featurePlan['description'],
                summary: $featurePlan['summary'] ?? substr($featurePlan['description'], 0, 100)
            );

            $featureData = json_decode($feature['content'][0]['text'], true);
            $featureId = $featureData['id'];

            echo "✅ Created feature: {$featurePlan['name']} (ID: {$featureId})\n";

            // Create requirements
            $createdRequirements = [];
            foreach ($featurePlan['requirements'] as $reqData) {
                $requirement = yield $this->fullcx->createRequirement(
                    productId: $productId,
                    name: $reqData['name'],
                    description: $reqData['description'],
                    featureId: $featureId,
                    priority: $reqData['priority'] ?? 2,
                    status: 'Backlog',
                    userStory: $reqData['user_story'] ?? null
                );

                $reqResult = json_decode($requirement['content'][0]['text'], true);
                $createdRequirements[] = $reqResult;
                echo "  ✅ Created requirement: {$reqResult['name']}\n";

                // Create acceptance criteria
                if (!empty($reqData['acceptance_criteria'])) {
                    foreach ($reqData['acceptance_criteria'] as $criteriaData) {
                        $criteria = yield $this->fullcx->createAcceptanceCriteria(
                            featureId: $featureId,
                            scenario: $criteriaData['scenario'],
                            criteria: $criteriaData['criteria'],
                            requirementId: $reqResult['id']
                        );

                        $criteriaResult = json_decode($criteria['content'][0]['text'], true);
                        echo "    ✅ Created criteria: {$criteriaResult['scenario']}\n";
                    }
                }
            }

            return [
                'feature' => $featureData,
                'requirements' => $createdRequirements,
                'ai_plan' => $featurePlan
            ];

        } finally {
            yield $this->fullcx->close();
        }
    }

    /**
     * Generate ideas based on market trends and user feedback
     */
    public function generateProductIdeas(string $productId, array $marketTrends = [], array $userFeedback = []): \Generator
    {
        yield $this->fullcx->connect();

        try {
            // Get product context
            $product = yield $this->fullcx->getProductDetails($productId);
            $productData = json_decode($product['content'][0]['text'], true);

            $prompt = "Generate innovative product ideas for the following product:\n\n";
            $prompt .= "Product: {$productData['name']}\n";
            $prompt .= "Description: {$productData['description']}\n";

            if (!empty($marketTrends)) {
                $prompt .= "\nMarket Trends:\n";
                foreach ($marketTrends as $trend) {
                    $prompt .= "- {$trend}\n";
                }
            }

            if (!empty($userFeedback)) {
                $prompt .= "\nUser Feedback:\n";
                foreach ($userFeedback as $feedback) {
                    $prompt .= "- {$feedback}\n";
                }
            }

            $prompt .= "\nGenerate 5 innovative ideas with:\n";
            $prompt .= "1. Idea name and description\n";
            $prompt .= "2. Effort estimate (1-10)\n";
            $prompt .= "3. Impact estimate (1-10)\n";
            $prompt .= "4. Timeline (Now/Next/Later)\n";
            $prompt .= "5. Rationale\n";
            $prompt .= "\nFormat as JSON array.";

            $response = $this->openai->chat()->create([
                'model' => 'gpt-4.1',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are an innovative product strategist who generates creative, feasible product ideas. Always respond with valid JSON.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'max_tokens' => 1200,
                'temperature' => 0.8
            ]);

            $ideas = json_decode($response->choices[0]->message->content, true);

            // Create ideas in FullCX
            $createdIdeas = [];
            foreach ($ideas as $ideaData) {
                $idea = yield $this->fullcx->createIdea(
                    name: $ideaData['name'],
                    description: $ideaData['description'],
                    ideaableType: 'App\\Models\\Product',
                    ideaableId: $productId,
                    effort: $ideaData['effort'],
                    impact: $ideaData['impact'],
                    timeline: $ideaData['timeline'],
                    status: 'Concept',
                    summary: $ideaData['rationale']
                );

                $ideaResult = json_decode($idea['content'][0]['text'], true);
                $createdIdeas[] = $ideaResult;
                echo "💡 Created idea: {$ideaResult['name']} (Impact: {$ideaData['impact']}, Effort: {$ideaData['effort']})\n";
            }

            return $createdIdeas;

        } finally {
            yield $this->fullcx->close();
        }
    }
}

// Usage example
$planner = new AIFeaturePlanner(
    fullcxToken: $_ENV['FULLCX_API_TOKEN'],
    openaiKey: $_ENV['OPENAI_API_KEY']
);

Loop::run(function() use ($planner) {
    // Plan a new feature
    $result = yield $planner->planFeature(
        'product-123',
        'Add real-time collaboration features to allow multiple users to work on documents simultaneously'
    );

    echo "🚀 Feature planning completed!\n";
    print_r($result);
});
```

## Advanced AI Workflows

### Step 5: Competitive Analysis and Market Intelligence

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use FullCX\MCP\FullCXClient;
use OpenAI;
use Amp\Loop;

class MarketIntelligenceAnalyzer
{
    private FullCXClient $fullcx;
    private OpenAI\Client $openai;

    public function __construct(string $fullcxToken, string $openaiKey)
    {
        $this->fullcx = new FullCXClient(
            url: 'https://full.cx/mcp',
            bearerToken: $fullcxToken
        );

        $this->openai = OpenAI::client($openaiKey);
    }

    /**
     * Analyze competitive landscape and suggest improvements
     */
    public function analyzeCompetitivePosition(string $productId, array $competitors = []): \Generator
    {
        yield $this->fullcx->connect();

        try {
            // Get current product data
            $product = yield $this->fullcx->getProductDetails($productId, true);
            $productData = json_decode($product['content'][0]['text'], true);

            // Get features and requirements
            $features = yield $this->fullcx->listFeatures($productId);
            $featureData = json_decode($features['content'][0]['text'], true);

            $requirements = yield $this->fullcx->listRequirements($productId);
            $reqData = json_decode($requirements['content'][0]['text'], true);

            // Prepare competitive analysis prompt
            $prompt = "Perform a competitive analysis for the following product:\n\n";
            $prompt .= "Our Product: {$productData['name']}\n";
            $prompt .= "Description: {$productData['description']}\n";
            $prompt .= "Current Features: " . count($featureData) . "\n";
            $prompt .= "Current Requirements: " . count($reqData) . "\n\n";

            $prompt .= "Key Features:\n";
            foreach (array_slice($featureData, 0, 5) as $feature) {
                $prompt .= "- {$feature['name']}: {$feature['description']}\n";
            }

            if (!empty($competitors)) {
                $prompt .= "\nKnown Competitors:\n";
                foreach ($competitors as $competitor) {
                    $prompt .= "- {$competitor}\n";
                }
            }

            $prompt .= "\nProvide:\n";
            $prompt .= "1. Competitive strengths and weaknesses\n";
            $prompt .= "2. Market gaps and opportunities\n";
            $prompt .= "3. Feature recommendations\n";
            $prompt .= "4. Positioning strategy\n";
            $prompt .= "5. Priority actions (top 3)\n";

            $response = $this->openai->chat()->create([
                'model' => 'gpt-4.1',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a senior product strategist and market analyst with deep expertise in competitive intelligence.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'max_tokens' => 1000,
                'temperature' => 0.4
            ]);

            $analysis = $response->choices[0]->message->content;

            // Generate strategic ideas based on analysis
            $ideaPrompt = "Based on this competitive analysis, generate 3 strategic product ideas:\n\n";
            $ideaPrompt .= $analysis;
            $ideaPrompt .= "\nFor each idea, provide: name, description, effort (1-10), impact (1-10), timeline, and competitive advantage.";
            $ideaPrompt .= "\nFormat as JSON array.";

            $ideaResponse = $this->openai->chat()->create([
                'model' => 'gpt-4.1',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a strategic product planner. Always respond with valid JSON.'],
                    ['role' => 'user', 'content' => $ideaPrompt]
                ],
                'max_tokens' => 800,
                'temperature' => 0.6
            ]);

            $strategicIdeas = json_decode($ideaResponse->choices[0]->message->content, true);

            // Create strategic ideas in FullCX
            foreach ($strategicIdeas as $ideaData) {
                $idea = yield $this->fullcx->createIdea(
                    name: $ideaData['name'],
                    description: $ideaData['description'],
                    ideaableType: 'App\\Models\\Product',
                    ideaableId: $productId,
                    effort: $ideaData['effort'],
                    impact: $ideaData['impact'],
                    timeline: $ideaData['timeline'],
                    status: 'In Review',
                    summary: $ideaData['competitive_advantage']
                );

                $ideaResult = json_decode($idea['content'][0]['text'], true);
                echo "🎯 Created strategic idea: {$ideaResult['name']}\n";
            }

            return [
                'analysis' => $analysis,
                'strategic_ideas' => $strategicIdeas
            ];

        } finally {
            yield $this->fullcx->close();
        }
    }

    /**
     * Generate product roadmap based on current state and market trends
     */
    public function generateRoadmap(string $productId, int $quarters = 4): \Generator
    {
        yield $this->fullcx->connect();

        try {
            // Get comprehensive product data
            $product = yield $this->fullcx->getProductDetails($productId, true);
            $productData = json_decode($product['content'][0]['text'], true);

            $features = yield $this->fullcx->listFeatures($productId);
            $featureData = json_decode($features['content'][0]['text'], true);

            $ideas = yield $this->fullcx->listIdeas();
            $allIdeas = json_decode($ideas['content'][0]['text'], true);

            // Filter ideas for this product
            $productIdeas = array_filter($allIdeas, function($idea) use ($productId) {
                return $idea['ideaable_type'] === 'App\\Models\\Product' &&
                       $idea['ideaable_id'] === $productId;
            });

            // Prepare roadmap generation prompt
            $prompt = "Create a strategic product roadmap for {$quarters} quarters:\n\n";
            $prompt .= "Product: {$productData['name']}\n";
            $prompt .= "Current State: {$productData['feature_count']} features, {$productData['requirement_count']} requirements\n\n";

            $prompt .= "Current Features:\n";
            foreach ($featureData as $feature) {
                $prompt .= "- {$feature['name']} (Status: {$feature['status']})\n";
            }

            $prompt .= "\nPending Ideas:\n";
            foreach ($productIdeas as $idea) {
                $effort = $idea['effort'] ?? 'N/A';
                $impact = $idea['impact'] ?? 'N/A';
                $prompt .= "- {$idea['name']} (Effort: {$effort}, Impact: {$impact})\n";
            }

            $prompt .= "\nGenerate a quarterly roadmap with:\n";
            $prompt .= "1. Theme for each quarter\n";
            $prompt .= "2. Key initiatives and features\n";
            $prompt .= "3. Success metrics\n";
            $prompt .= "4. Dependencies and risks\n";
            $prompt .= "5. Resource requirements\n";
            $prompt .= "\nFormat as structured JSON.";

            $response = $this->openai->chat()->create([
                'model' => 'gpt-4.1',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a senior product manager who creates strategic roadmaps. Always respond with valid JSON.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'max_tokens' => 1500,
                'temperature' => 0.5
            ]);

            $roadmap = json_decode($response->choices[0]->message->content, true);

            // Create roadmap items as features in FullCX
            foreach ($roadmap['quarters'] as $quarter => $quarterData) {
                echo "📅 {$quarter}: {$quarterData['theme']}\n";

                foreach ($quarterData['initiatives'] as $initiative) {
                    $feature = yield $this->fullcx->createFeature(
                        productId: $productId,
                        name: $initiative['name'],
                        description: $initiative['description'],
                        summary: "Q{$quarter} Initiative: {$quarterData['theme']}"
                    );

                    $featureResult = json_decode($feature['content'][0]['text'], true);
                    echo "  ✅ Created roadmap feature: {$featureResult['name']}\n";
                }
            }

            return $roadmap;

        } finally {
            yield $this->fullcx->close();
        }
    }
}

// Usage example
$analyzer = new MarketIntelligenceAnalyzer(
    fullcxToken: $_ENV['FULLCX_API_TOKEN'],
    openaiKey: $_ENV['OPENAI_API_KEY']
);

Loop::run(function() use ($analyzer) {
    // Competitive analysis
    $analysis = yield $analyzer->analyzeCompetitivePosition(
        'product-123',
        ['Competitor A', 'Competitor B', 'Competitor C']
    );

    echo "📊 Competitive Analysis:\n";
    echo $analysis['analysis'] . "\n\n";

    // Generate roadmap
    $roadmap = yield $analyzer->generateRoadmap('product-123', 4);
    echo "🗺️ Product Roadmap Generated!\n";
});
```

## Configuration Options

### Custom Request Options

```php
use MCP\Shared\RequestOptions;

$options = new RequestOptions(
    timeoutMs: 30000,        // 30 second timeout
    retryCount: 3,           // Retry failed requests 3 times
    retryDelayMs: 1000,      // Wait 1 second between retries
    headers: [               // Additional headers
        'X-Custom-Header' => 'value'
    ]
);

$result = yield $client->listProducts(options: $options);
```

### Environment Configuration

```php
// .env file
FULLCX_API_TOKEN=your_fullcx_token_here
FULLCX_MCP_URL=https://full.cx/mcp
OPENAI_API_KEY=your_openai_key_here
OPENAI_ORGANIZATION=your_org_id  # Optional
```

```php
// Configuration class
class Config
{
    public static function getFullCXClient(): FullCXClient
    {
        return new FullCXClient(
            url: $_ENV['FULLCX_MCP_URL'] ?? 'https://full.cx/mcp',
            bearerToken: $_ENV['FULLCX_API_TOKEN'] ?? throw new \Exception('FULLCX_API_TOKEN required')
        );
    }

    public static function getOpenAIClient(): OpenAI\Client
    {
        return OpenAI::factory()
            ->withApiKey($_ENV['OPENAI_API_KEY'] ?? throw new \Exception('OPENAI_API_KEY required'))
            ->withOrganization($_ENV['OPENAI_ORGANIZATION'] ?? null)
            ->withHttpHeader('User-Agent', 'FullCX-Integration/1.0')
            ->make();
    }
}
```

## Security Best Practices

1. **Environment Variables**: Never hardcode API keys

```php
// ❌ Bad
$client = new FullCXClient('https://full.cx/mcp', 'hardcoded-token');

// ✅ Good
$client = new FullCXClient(
    $_ENV['FULLCX_MCP_URL'],
    $_ENV['FULLCX_API_TOKEN'] ?? throw new \Exception('Token required')
);
```

2. **Input Validation**: Validate all inputs before processing

```php
function validateProductId(string $productId): string
{
    if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $productId)) {
        throw new \InvalidArgumentException('Invalid product ID format');
    }
    return $productId;
}
```

3. **Rate Limiting**: Implement proper rate limiting for both APIs

```php
class RateLimiter
{
    private array $requests = [];
    private int $maxRequests;
    private int $windowSeconds;

    public function __construct(int $maxRequests = 100, int $windowSeconds = 60)
    {
        $this->maxRequests = $maxRequests;
        $this->windowSeconds = $windowSeconds;
    }

    public function canMakeRequest(): bool
    {
        $now = time();
        $this->requests = array_filter($this->requests, fn($time) => $now - $time < $this->windowSeconds);

        return count($this->requests) < $this->maxRequests;
    }

    public function recordRequest(): void
    {
        if (!$this->canMakeRequest()) {
            throw new \Exception('Rate limit exceeded');
        }
        $this->requests[] = time();
    }
}
```

4. **Error Handling**: Implement comprehensive error handling

```php
class AIProductManager
{
    public function safeAICall(callable $aiOperation, int $maxRetries = 3): mixed
    {
        $retries = 0;

        while ($retries < $maxRetries) {
            try {
                return $aiOperation();
            } catch (\OpenAI\Exceptions\ErrorException $e) {
                $retries++;

                if ($e->getCode() === 429) { // Rate limit
                    sleep(pow(2, $retries)); // Exponential backoff
                    continue;
                }

                if ($retries >= $maxRetries) {
                    throw $e;
                }
            }
        }
    }
}
```

## Integration Patterns

### Laravel Integration

```php
// Service Provider
use FullCX\MCP\FullCXClient;
use OpenAI;

class FullCXServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FullCXClient::class, function ($app) {
            return new FullCXClient(
                url: config('services.fullcx.mcp_url'),
                bearerToken: config('services.fullcx.api_token')
            );
        });

        $this->app->singleton(OpenAI\Client::class, function ($app) {
            return OpenAI::client(config('services.openai.api_key'));
        });
    }
}

// Controller
class ProductController extends Controller
{
    public function __construct(
        private FullCXClient $fullcx,
        private OpenAI\Client $openai
    ) {}

    public function generateDescription(Request $request)
    {
        $productId = $request->input('product_id');

        // Get product from FullCX
        $product = $this->fullcx->getProductDetails($productId)->await();
        $productData = json_decode($product['content'][0]['text'], true);

        // Generate AI description
        $response = $this->openai->chat()->create([
            'model' => 'gpt-4.1',
            'messages' => [
                ['role' => 'user', 'content' => "Create a description for: {$productData['name']}"]
            ]
        ]);

        return response()->json([
            'description' => $response->choices[0]->message->content
        ]);
    }
}
```

### Async Processing

```php
use Amp\Parallel\Worker;

// Process multiple products with AI concurrently
async function processProductsWithAI(array $productIds): array
{
    $promises = [];

    foreach ($productIds as $productId) {
        $promises[$productId] = async(function() use ($productId) {
            $manager = new AIEnhancedProductManager(
                $_ENV['FULLCX_API_TOKEN'],
                $_ENV['OPENAI_API_KEY']
            );

            return yield $manager->analyzeProduct($productId);
        });
    }

    $results = [];
    foreach ($promises as $productId => $promise) {
        try {
            $results[$productId] = yield $promise;
        } catch (\Exception $e) {
            error_log("Failed to process {$productId}: {$e->getMessage()}");
        }
    }

    return $results;
}
```

## Testing

### Unit Testing with Mocks

```php
use PHPUnit\Framework\TestCase;
use FullCX\MCP\FullCXClient;
use OpenAI\Testing\ClientFake;
use OpenAI\Responses\Chat\CreateResponse;

class AIProductManagerTest extends TestCase
{
    public function testGenerateProductDescription(): void
    {
        // Mock OpenAI client
        $openai = new ClientFake([
            CreateResponse::fake([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Generated product description'
                        ]
                    ]
                ]
            ])
        ]);

        // Mock FullCX client would require custom implementation

        $manager = new AIEnhancedProductManager('test-token', 'test-key');
        // Test implementation here

        $this->assertTrue(true); // Placeholder
    }
}
```

### Integration Testing

```php
class FullCXIntegrationTest extends TestCase
{
    private FullCXClient $client;
    private OpenAI\Client $openai;

    protected function setUp(): void
    {
        $this->client = new FullCXClient(
            $_ENV['FULLCX_TEST_URL'] ?? 'https://full.cx/mcp',
            $_ENV['FULLCX_TEST_TOKEN']
        );

        $this->openai = OpenAI::client($_ENV['OPENAI_TEST_KEY']);
    }

    public function testFullWorkflow(): void
    {
        Loop::run(function() {
            yield $this->client->connect();

            // Test FullCX operations
            $products = yield $this->client->listProducts(limit: 1);
            $this->assertIsArray($products);

            // Test AI integration
            $response = $this->openai->chat()->create([
                'model' => 'gpt-4.1',
                'messages' => [['role' => 'user', 'content' => 'Test']]
            ]);
            $this->assertNotEmpty($response->choices[0]->message->content);

            yield $this->client->close();
        });
    }
}
```

## Troubleshooting

### Common Issues

**FullCX Connection Issues:**

```php
// Test connection
try {
    $client = new FullCXClient('https://full.cx/mcp', $token);
    yield $client->connect();
    $ping = yield $client->ping();
    echo "✅ FullCX connection successful\n";
} catch (\Exception $e) {
    echo "❌ FullCX connection failed: {$e->getMessage()}\n";
}
```

**OpenAI API Issues:**

```php
// Test OpenAI connection
try {
    $openai = OpenAI::client($apiKey);
    $response = $openai->chat()->create([
        'model' => 'gpt-4.1',
        'messages' => [['role' => 'user', 'content' => 'Hello']]
    ]);
    echo "✅ OpenAI connection successful\n";
} catch (\Exception $e) {
    echo "❌ OpenAI connection failed: {$e->getMessage()}\n";
}
```

**Memory Issues with Large Datasets:**

```php
// Process in batches
function processInBatches(array $items, int $batchSize = 10): \Generator
{
    $batches = array_chunk($items, $batchSize);

    foreach ($batches as $batch) {
        yield $batch;

        // Force garbage collection between batches
        gc_collect_cycles();
    }
}
```

## Performance Optimization

1. **Caching AI Responses**:

```php
class CachedAIManager
{
    private array $cache = [];

    public function getCachedResponse(string $key, callable $generator): mixed
    {
        if (!isset($this->cache[$key])) {
            $this->cache[$key] = $generator();
        }

        return $this->cache[$key];
    }
}
```

2. **Batch Operations**:

```php
// Batch multiple FullCX operations
async function batchCreateRequirements(FullCXClient $client, array $requirements): array
{
    $promises = [];

    foreach ($requirements as $req) {
        $promises[] = $client->createRequirement(...$req);
    }

    return yield $promises;
}
```

## Next Steps

1. **Advanced AI Features**: Implement sentiment analysis, trend prediction, and automated testing
2. **Workflow Automation**: Create automated pipelines for feature planning and requirement generation
3. **Custom Integrations**: Build custom tools for your specific product management needs
4. **Monitoring and Analytics**: Implement tracking for AI-generated content performance

## Additional Resources

- [FullCX Documentation](https://full.cx/docs)
- [OpenAI PHP Client Documentation](https://github.com/openai-php/client)
- [PHP MCP SDK Documentation](../../README.md)
- [MCP Protocol Specification](https://modelcontextprotocol.io)
- [OpenAI API Documentation](https://platform.openai.com/docs)

## Support

For FullCX-specific questions:

- [FullCX Support](https://full.cx/support)

For OpenAI PHP client questions:

- [OpenAI PHP Client Issues](https://github.com/openai-php/client/issues)

For PHP MCP SDK questions:

- [GitHub Issues](https://github.com/dalehurley/php-mcp-sdk/issues)
- [GitHub Discussions](https://github.com/dalehurley/php-mcp-sdk/discussions)
