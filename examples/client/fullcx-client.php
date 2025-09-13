#!/usr/bin/env php
<?php

/**
 * FullCX MCP Client with OpenAI Integration Example
 * 
 * This example demonstrates how to connect to and interact with the FullCX MCP server
 * for AI-enhanced product management operations including products, features, requirements, 
 * and ideas with intelligent content generation.
 * 
 * Usage:
 *   php examples/client/fullcx-client.php [command] [options]
 * 
 * Commands:
 *   connect        - Test connection to FullCX server
 *   products       - List and explore products
 *   features       - Manage features for a product
 *   requirements   - Work with requirements
 *   ideas          - Manage ideas
 *   create         - Create new entities (interactive)
 *   ai-plan        - AI-powered feature planning
 *   ai-ideas       - Generate AI-powered product ideas
 *   ai-analysis    - AI competitive analysis
 *   ai-roadmap     - Generate AI-powered roadmap
 *   analyze        - Comprehensive product analysis
 *   demo           - Run full demonstration
 * 
 * Environment Variables:
 *   FULLCX_API_TOKEN   - Your FullCX API token (required)
 *   FULLCX_MCP_URL     - FullCX MCP server URL (default: https://full.cx/mcp)
 *   OPENAI_API_KEY     - OpenAI API key (required for AI features)
 *   OPENAI_ORG_ID      - OpenAI organization ID (optional)
 * 
 * Examples:
 *   export FULLCX_API_TOKEN=your-token-here
 *   export OPENAI_API_KEY=your-openai-key-here
 *   php examples/client/fullcx-client.php demo
 *   php examples/client/fullcx-client.php ai-plan product-123 "Add real-time collaboration"
 *   php examples/client/fullcx-client.php ai-analysis product-123 "Slack,Teams,Discord"
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use FullCX\MCP\FullCXClient;
use MCP\Shared\RequestOptions;
use MCP\Types\McpError;
use OpenAI;
use Amp\Loop;

class AIEnhancedFullCXClient
{
    private FullCXClient $fullcx;
    private ?OpenAI\Client $openai = null;
    private array $config;

    public function __construct()
    {
        $this->config = [
            'fullcx_url' => $_ENV['FULLCX_MCP_URL'] ?? 'https://full.cx/mcp',
            'fullcx_token' => $_ENV['FULLCX_API_TOKEN'] ?? null,
            'openai_key' => $_ENV['OPENAI_API_KEY'] ?? null,
            'openai_org' => $_ENV['OPENAI_ORG_ID'] ?? null,
            'timeout' => 30000,
            'retries' => 3
        ];

        if (!$this->config['fullcx_token']) {
            throw new \Exception('FULLCX_API_TOKEN environment variable is required');
        }

        $this->fullcx = new FullCXClient(
            url: $this->config['fullcx_url'],
            bearerToken: $this->config['fullcx_token']
        );

        // Initialize OpenAI client if API key is available
        if ($this->config['openai_key']) {
            $factory = OpenAI::factory()->withApiKey($this->config['openai_key']);

            if ($this->config['openai_org']) {
                $factory = $factory->withOrganization($this->config['openai_org']);
            }

            $this->openai = $factory
                ->withHttpHeader('User-Agent', 'FullCX-MCP-Client/1.0.0')
                ->make();
        }
    }

    /**
     * Test connection to FullCX server
     */
    public function testConnection(): \Generator
    {
        echo "🔌 Testing connection to FullCX...\n";
        echo "URL: {$this->config['fullcx_url']}\n";
        echo "Token: " . substr($this->config['fullcx_token'], 0, 8) . "...\n";

        if ($this->openai) {
            echo "OpenAI: Enabled ✅\n";
        } else {
            echo "OpenAI: Disabled (set OPENAI_API_KEY to enable AI features) ⚠️\n";
        }
        echo "\n";

        try {
            yield $this->fullcx->connect();
            echo "✅ Successfully connected to FullCX!\n\n";

            // Test server capabilities
            echo "🔧 Available Tools:\n";
            $tools = yield $this->fullcx->listTools();

            foreach ($tools->getTools() as $tool) {
                echo "  - {$tool->getName()}: {$tool->getDescription()}\n";
            }

            echo "\n📡 Testing server ping...\n";
            $pingResult = yield $this->fullcx->ping();
            echo "✅ Ping successful!\n\n";

            // Test OpenAI if available
            if ($this->openai) {
                echo "🤖 Testing OpenAI connection...\n";
                $response = $this->openai->chat()->create([
                    'model' => 'gpt-4.1',
                    'messages' => [
                        ['role' => 'user', 'content' => 'Hello! Just testing the connection.']
                    ],
                    'max_tokens' => 10
                ]);
                echo "✅ OpenAI connection successful!\n";
                echo "Response: " . $response->choices[0]->message->content . "\n\n";
            }
        } catch (\Exception $e) {
            echo "❌ Connection failed: {$e->getMessage()}\n";
            throw $e;
        }
    }

    /**
     * AI-powered feature planning
     */
    public function aiFeaturePlanning(string $productId, string $featureIdea): \Generator
    {
        if (!$this->openai) {
            throw new \Exception('OpenAI API key required for AI features. Set OPENAI_API_KEY environment variable.');
        }

        echo "🤖 AI-Powered Feature Planning\n";
        echo "=" . str_repeat("=", 35) . "\n\n";

        try {
            // Get product context
            echo "📦 Getting product context...\n";
            $product = yield $this->fullcx->getProductDetails($productId);
            $productData = json_decode($product['content'][0]['text'], true);

            echo "Product: {$productData['name']}\n";
            echo "Feature Idea: {$featureIdea}\n\n";

            // Generate comprehensive feature plan with AI
            echo "🧠 Generating AI-powered feature plan...\n";
            $prompt = $this->buildFeaturePlanPrompt($productData, $featureIdea);

            $response = $this->openai->chat()->create([
                'model' => 'gpt-4.1',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a senior product manager who creates detailed feature specifications. Always respond with valid JSON.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'max_tokens' => 2000,
                'temperature' => 0.3
            ]);

            $featurePlan = json_decode($response->choices[0]->message->content, true);

            if (!$featurePlan) {
                throw new \Exception('Failed to parse AI response as JSON');
            }

            echo "✅ AI plan generated!\n\n";

            // Create the feature in FullCX
            echo "🚀 Creating feature in FullCX...\n";
            $feature = yield $this->fullcx->createFeature(
                productId: $productId,
                name: $featurePlan['name'],
                description: $featurePlan['description'],
                summary: $featurePlan['summary'] ?? substr($featurePlan['description'], 0, 100)
            );

            $featureData = json_decode($feature['content'][0]['text'], true);
            $featureId = $featureData['id'];

            echo "✅ Created feature: {$featurePlan['name']} (ID: {$featureId})\n\n";

            // Create requirements
            echo "📋 Creating AI-generated requirements...\n";
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
                echo "  ✅ {$reqResult['name']}\n";

                // Create acceptance criteria
                if (!empty($reqData['acceptance_criteria'])) {
                    foreach ($reqData['acceptance_criteria'] as $criteriaData) {
                        $criteria = yield $this->fullcx->createAcceptanceCriteria(
                            featureId: $featureId,
                            scenario: $criteriaData['scenario'],
                            criteria: $criteriaData['criteria'],
                            requirementId: $reqResult['id']
                        );

                        echo "    ✅ Criteria: {$criteriaData['scenario']}\n";
                    }
                }
            }

            echo "\n🎯 Feature Planning Summary:\n";
            echo "  Feature: {$featurePlan['name']}\n";
            echo "  Requirements: " . count($createdRequirements) . "\n";
            echo "  Estimated Effort: {$featurePlan['effort']}/10\n";
            echo "  Expected Impact: {$featurePlan['impact']}/10\n";
            echo "  Priority: {$featurePlan['priority']}\n\n";

            return [
                'feature' => $featureData,
                'requirements' => $createdRequirements,
                'ai_plan' => $featurePlan
            ];
        } catch (\Exception $e) {
            echo "❌ AI feature planning failed: {$e->getMessage()}\n";
            throw $e;
        }
    }

    /**
     * Generate AI-powered product ideas
     */
    public function generateAIIdeas(string $productId, array $marketTrends = [], array $userFeedback = []): \Generator
    {
        if (!$this->openai) {
            throw new \Exception('OpenAI API key required for AI features.');
        }

        echo "💡 AI-Powered Idea Generation\n";
        echo "=" . str_repeat("=", 32) . "\n\n";

        try {
            // Get product context
            $product = yield $this->fullcx->getProductDetails($productId);
            $productData = json_decode($product['content'][0]['text'], true);

            echo "🎯 Generating ideas for: {$productData['name']}\n\n";

            // Build context prompt
            $prompt = $this->buildIdeaGenerationPrompt($productData, $marketTrends, $userFeedback);

            echo "🧠 AI is analyzing market trends and generating ideas...\n";
            $response = $this->openai->chat()->create([
                'model' => 'gpt-4.1',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are an innovative product strategist who generates creative, feasible product ideas based on market analysis. Always respond with valid JSON.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'max_tokens' => 1500,
                'temperature' => 0.8
            ]);

            $ideas = json_decode($response->choices[0]->message->content, true);

            if (!$ideas || !is_array($ideas)) {
                throw new \Exception('Failed to parse AI ideas response');
            }

            echo "✅ Generated " . count($ideas) . " innovative ideas!\n\n";

            // Create ideas in FullCX
            echo "📝 Creating ideas in FullCX...\n";
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
                    summary: $ideaData['rationale'] ?? substr($ideaData['description'], 0, 100)
                );

                $ideaResult = json_decode($idea['content'][0]['text'], true);
                $createdIdeas[] = $ideaResult;

                $ratio = round($ideaData['impact'] / max($ideaData['effort'], 1), 2);
                echo "  💡 {$ideaResult['name']}\n";
                echo "     Impact: {$ideaData['impact']}/10, Effort: {$ideaData['effort']}/10, Ratio: {$ratio}\n";
                echo "     Timeline: {$ideaData['timeline']}\n";
                echo "     " . substr($ideaData['description'], 0, 80) . "...\n\n";
            }

            // Sort by impact/effort ratio
            usort($createdIdeas, function ($a, $b) {
                $ratioA = $a['impact'] / max($a['effort'], 1);
                $ratioB = $b['impact'] / max($b['effort'], 1);
                return $ratioB <=> $ratioA;
            });

            echo "🏆 Top Priority Ideas (by Impact/Effort ratio):\n";
            foreach (array_slice($createdIdeas, 0, 3) as $index => $idea) {
                $ratio = round($idea['impact'] / max($idea['effort'], 1), 2);
                echo "  " . ($index + 1) . ". {$idea['name']} (Ratio: {$ratio})\n";
            }
            echo "\n";

            return $createdIdeas;
        } catch (\Exception $e) {
            echo "❌ AI idea generation failed: {$e->getMessage()}\n";
            throw $e;
        }
    }

    /**
     * AI competitive analysis
     */
    public function aiCompetitiveAnalysis(string $productId, array $competitors = []): \Generator
    {
        if (!$this->openai) {
            throw new \Exception('OpenAI API key required for AI features.');
        }

        echo "📊 AI-Powered Competitive Analysis\n";
        echo "=" . str_repeat("=", 38) . "\n\n";

        try {
            // Get comprehensive product data
            $product = yield $this->fullcx->getProductDetails($productId, true);
            $productData = json_decode($product['content'][0]['text'], true);

            $features = yield $this->fullcx->listFeatures($productId);
            $featureData = json_decode($features['content'][0]['text'], true);

            echo "🎯 Analyzing: {$productData['name']}\n";
            echo "Competitors: " . implode(', ', $competitors) . "\n\n";

            // Build analysis prompt
            $prompt = $this->buildCompetitiveAnalysisPrompt($productData, $featureData, $competitors);

            echo "🧠 AI is performing competitive analysis...\n";
            $response = $this->openai->chat()->create([
                'model' => 'gpt-4.1',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a senior product strategist and market analyst with deep expertise in competitive intelligence and strategic positioning.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'max_tokens' => 1200,
                'temperature' => 0.4
            ]);

            $analysis = $response->choices[0]->message->content;

            echo "✅ Analysis complete!\n\n";
            echo "📋 Competitive Analysis Report:\n";
            echo str_repeat("-", 50) . "\n";
            echo $analysis . "\n\n";

            // Generate strategic ideas based on analysis
            echo "🚀 Generating strategic recommendations...\n";
            $ideaPrompt = "Based on this competitive analysis, generate 3 strategic product ideas that would give us competitive advantage:\n\n";
            $ideaPrompt .= $analysis;
            $ideaPrompt .= "\n\nFor each idea, provide: name, description, effort (1-10), impact (1-10), timeline (Now/Next/Later), and competitive_advantage explanation.";
            $ideaPrompt .= "\nFormat as JSON array.";

            $ideaResponse = $this->openai->chat()->create([
                'model' => 'gpt-4.1',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a strategic product planner. Always respond with valid JSON.'],
                    ['role' => 'user', 'content' => $ideaPrompt]
                ],
                'max_tokens' => 1000,
                'temperature' => 0.6
            ]);

            $strategicIdeas = json_decode($ideaResponse->choices[0]->message->content, true);

            if ($strategicIdeas && is_array($strategicIdeas)) {
                echo "💡 Creating strategic ideas in FullCX...\n";
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
                    echo "  🎯 {$ideaResult['name']} (Impact: {$ideaData['impact']}, Effort: {$ideaData['effort']})\n";
                    echo "     Advantage: {$ideaData['competitive_advantage']}\n\n";
                }
            }

            return [
                'analysis' => $analysis,
                'strategic_ideas' => $strategicIdeas ?? []
            ];
        } catch (\Exception $e) {
            echo "❌ Competitive analysis failed: {$e->getMessage()}\n";
            throw $e;
        }
    }

    /**
     * Generate AI-powered product roadmap
     */
    public function generateAIRoadmap(string $productId, int $quarters = 4): \Generator
    {
        if (!$this->openai) {
            throw new \Exception('OpenAI API key required for AI features.');
        }

        echo "🗺️ AI-Powered Roadmap Generation\n";
        echo "=" . str_repeat("=", 36) . "\n\n";

        try {
            // Get comprehensive product data
            $product = yield $this->fullcx->getProductDetails($productId, true);
            $productData = json_decode($product['content'][0]['text'], true);

            $features = yield $this->fullcx->listFeatures($productId);
            $featureData = json_decode($features['content'][0]['text'], true);

            $ideas = yield $this->fullcx->listIdeas();
            $allIdeas = json_decode($ideas['content'][0]['text'], true);

            // Filter ideas for this product
            $productIdeas = array_filter($allIdeas, function ($idea) use ($productId) {
                return $idea['ideaable_type'] === 'App\\Models\\Product' &&
                    $idea['ideaable_id'] === $productId;
            });

            echo "📊 Generating {$quarters}-quarter roadmap for: {$productData['name']}\n\n";

            // Build roadmap prompt
            $prompt = $this->buildRoadmapPrompt($productData, $featureData, $productIdeas, $quarters);

            echo "🧠 AI is creating strategic roadmap...\n";
            $response = $this->openai->chat()->create([
                'model' => 'gpt-4.1',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a senior product manager who creates strategic roadmaps. Always respond with valid JSON.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'max_tokens' => 2000,
                'temperature' => 0.5
            ]);

            $roadmap = json_decode($response->choices[0]->message->content, true);

            if (!$roadmap || !isset($roadmap['quarters'])) {
                throw new \Exception('Failed to parse AI roadmap response');
            }

            echo "✅ Roadmap generated!\n\n";

            // Display and create roadmap items
            echo "📅 Roadmap Overview:\n";
            echo str_repeat("-", 50) . "\n";

            foreach ($roadmap['quarters'] as $quarterKey => $quarterData) {
                echo "🗓️ {$quarterKey}: {$quarterData['theme']}\n";
                echo "   Focus: {$quarterData['focus']}\n";

                if (!empty($quarterData['initiatives'])) {
                    echo "   Initiatives:\n";
                    foreach ($quarterData['initiatives'] as $initiative) {
                        echo "     • {$initiative['name']}\n";
                        echo "       {$initiative['description']}\n";

                        // Create as feature in FullCX
                        $feature = yield $this->fullcx->createFeature(
                            productId: $productId,
                            name: "[{$quarterKey}] {$initiative['name']}",
                            description: $initiative['description'],
                            summary: "Roadmap Initiative: {$quarterData['theme']}"
                        );

                        $featureResult = json_decode($feature['content'][0]['text'], true);
                        echo "       ✅ Created roadmap feature (ID: {$featureResult['id']})\n";
                    }
                }

                if (!empty($quarterData['success_metrics'])) {
                    echo "   Success Metrics:\n";
                    foreach ($quarterData['success_metrics'] as $metric) {
                        echo "     📊 {$metric}\n";
                    }
                }

                echo "\n";
            }

            // Display risks and dependencies
            if (!empty($roadmap['risks'])) {
                echo "⚠️ Identified Risks:\n";
                foreach ($roadmap['risks'] as $risk) {
                    echo "  • {$risk}\n";
                }
                echo "\n";
            }

            if (!empty($roadmap['dependencies'])) {
                echo "🔗 Key Dependencies:\n";
                foreach ($roadmap['dependencies'] as $dependency) {
                    echo "  • {$dependency}\n";
                }
                echo "\n";
            }

            return $roadmap;
        } catch (\Exception $e) {
            echo "❌ Roadmap generation failed: {$e->getMessage()}\n";
            throw $e;
        }
    }

    /**
     * Enhanced product analysis with AI insights
     */
    public function enhancedAnalysis(string $productId): \Generator
    {
        echo "🔍 Enhanced Product Analysis with AI\n";
        echo "=" . str_repeat("=", 40) . "\n\n";

        try {
            // Get comprehensive data
            $product = yield $this->fullcx->getProductDetails($productId, true, true);
            $productData = json_decode($product['content'][0]['text'], true);

            echo "📊 Product: {$productData['name']}\n";
            echo "   Features: {$productData['feature_count']}\n";
            echo "   Requirements: {$productData['requirement_count']}\n";
            echo "   Ideas: {$productData['idea_count']}\n\n";

            // Basic analysis
            $features = yield $this->fullcx->listFeatures($productId);
            $featureData = json_decode($features['content'][0]['text'], true);

            $requirements = yield $this->fullcx->listRequirements($productId);
            $reqData = json_decode($requirements['content'][0]['text'], true);

            $ideas = yield $this->fullcx->listIdeas();
            $allIdeas = json_decode($ideas['content'][0]['text'], true);
            $productIdeas = array_filter($allIdeas, function ($idea) use ($productId) {
                return $idea['ideaable_type'] === 'App\\Models\\Product' &&
                    $idea['ideaable_id'] === $productId;
            });

            // Display basic analytics
            $this->displayBasicAnalytics($featureData, $reqData, $productIdeas);

            // AI-powered insights
            if ($this->openai) {
                echo "🤖 Generating AI insights...\n\n";

                $prompt = $this->buildAnalysisPrompt($productData, $featureData, $reqData, $productIdeas);

                $response = $this->openai->chat()->create([
                    'model' => 'gpt-4.1',
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a senior product strategist who provides actionable insights and recommendations based on product data analysis.'],
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'max_tokens' => 1000,
                    'temperature' => 0.6
                ]);

                $insights = $response->choices[0]->message->content;

                echo "🧠 AI Strategic Insights:\n";
                echo str_repeat("-", 50) . "\n";
                echo $insights . "\n\n";
            }

            echo "✅ Analysis complete!\n";
        } catch (\Exception $e) {
            echo "❌ Enhanced analysis failed: {$e->getMessage()}\n";
        }
    }

    /**
     * Run comprehensive demonstration
     */
    public function runDemo(): \Generator
    {
        echo "🎬 FullCX + OpenAI Integration Demonstration\n";
        echo "=" . str_repeat("=", 50) . "\n\n";

        try {
            // 1. Test connections
            yield $this->testConnection();

            echo "\n" . str_repeat("-", 60) . "\n\n";

            // 2. Get a product to work with
            echo "📦 Getting product for demonstration...\n";
            $products = yield $this->fullcx->listProducts(limit: 1);
            $productData = json_decode($products['content'][0]['text'], true);

            if (empty($productData)) {
                echo "❌ No products available for demonstration\n";
                return;
            }

            $productId = $productData[0]['id'];
            $productName = $productData[0]['name'];

            echo "Using product: {$productName} (ID: {$productId})\n\n";

            echo str_repeat("-", 60) . "\n\n";

            // 3. Enhanced analysis
            yield $this->enhancedAnalysis($productId);

            // Only run AI features if OpenAI is available
            if ($this->openai) {
                echo "\n" . str_repeat("-", 60) . "\n\n";

                // 4. AI idea generation
                echo "🤖 AI Feature Demonstration\n\n";

                yield $this->generateAIIdeas(
                    $productId,
                    ['Real-time collaboration', 'AI automation', 'Mobile-first design'],
                    ['Need better performance', 'Want more integrations', 'Mobile app requested']
                );

                echo "\n" . str_repeat("-", 60) . "\n\n";

                // 5. AI feature planning
                yield $this->aiFeaturePlanning(
                    $productId,
                    'Advanced analytics dashboard with real-time insights and customizable reports'
                );

                echo "\n" . str_repeat("-", 60) . "\n\n";

                // 6. Competitive analysis
                yield $this->aiCompetitiveAnalysis(
                    $productId,
                    ['Notion', 'Asana', 'Monday.com']
                );
            } else {
                echo "\n⚠️ OpenAI features skipped (set OPENAI_API_KEY to enable)\n";
            }

            echo "\n🎉 Demonstration completed successfully!\n\n";

            echo "🚀 Next steps you can try:\n";
            echo "- Generate AI roadmap: php {$_SERVER['argv'][0]} ai-roadmap {$productId}\n";
            echo "- Plan specific feature: php {$_SERVER['argv'][0]} ai-plan {$productId} \"your feature idea\"\n";
            echo "- Analyze competitors: php {$_SERVER['argv'][0]} ai-analysis {$productId} \"Competitor1,Competitor2\"\n";
        } catch (\Exception $e) {
            echo "❌ Demo failed: {$e->getMessage()}\n";
        }
    }

    // Helper methods

    private function buildFeaturePlanPrompt(array $productData, string $featureIdea): string
    {
        $prompt = "Create a detailed feature plan for the following idea:\n\n";
        $prompt .= "Product: {$productData['name']}\n";
        $prompt .= "Product Description: {$productData['description']}\n";
        $prompt .= "Feature Idea: {$featureIdea}\n\n";
        $prompt .= "Please provide a JSON response with:\n";
        $prompt .= "{\n";
        $prompt .= "  \"name\": \"Feature name\",\n";
        $prompt .= "  \"description\": \"Detailed description\",\n";
        $prompt .= "  \"summary\": \"Brief summary\",\n";
        $prompt .= "  \"priority\": 1-5,\n";
        $prompt .= "  \"effort\": 1-10,\n";
        $prompt .= "  \"impact\": 1-10,\n";
        $prompt .= "  \"requirements\": [\n";
        $prompt .= "    {\n";
        $prompt .= "      \"name\": \"Requirement name\",\n";
        $prompt .= "      \"description\": \"Detailed description\",\n";
        $prompt .= "      \"priority\": 1-5,\n";
        $prompt .= "      \"user_story\": \"As a... I want... so that...\",\n";
        $prompt .= "      \"acceptance_criteria\": [\n";
        $prompt .= "        {\"scenario\": \"Scenario name\", \"criteria\": \"Given... When... Then...\"}\n";
        $prompt .= "      ]\n";
        $prompt .= "    }\n";
        $prompt .= "  ]\n";
        $prompt .= "}";

        return $prompt;
    }

    private function buildIdeaGenerationPrompt(array $productData, array $marketTrends, array $userFeedback): string
    {
        $prompt = "Generate 5 innovative product ideas for:\n\n";
        $prompt .= "Product: {$productData['name']}\n";
        $prompt .= "Description: {$productData['description']}\n";
        $prompt .= "Current Features: {$productData['feature_count']}\n";

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

        $prompt .= "\nRespond with JSON array of ideas:\n";
        $prompt .= "[\n";
        $prompt .= "  {\n";
        $prompt .= "    \"name\": \"Idea name\",\n";
        $prompt .= "    \"description\": \"Detailed description\",\n";
        $prompt .= "    \"effort\": 1-10,\n";
        $prompt .= "    \"impact\": 1-10,\n";
        $prompt .= "    \"timeline\": \"Now|Next|Later\",\n";
        $prompt .= "    \"rationale\": \"Why this idea is valuable\"\n";
        $prompt .= "  }\n";
        $prompt .= "]";

        return $prompt;
    }

    private function buildCompetitiveAnalysisPrompt(array $productData, array $featureData, array $competitors): string
    {
        $prompt = "Perform a competitive analysis for:\n\n";
        $prompt .= "Our Product: {$productData['name']}\n";
        $prompt .= "Description: {$productData['description']}\n";
        $prompt .= "Features: " . count($featureData) . "\n\n";

        $prompt .= "Key Features:\n";
        foreach (array_slice($featureData, 0, 5) as $feature) {
            $prompt .= "- {$feature['name']}\n";
        }

        if (!empty($competitors)) {
            $prompt .= "\nCompetitors to analyze: " . implode(', ', $competitors) . "\n";
        }

        $prompt .= "\nProvide analysis covering:\n";
        $prompt .= "1. Our competitive strengths\n";
        $prompt .= "2. Areas where we may be behind\n";
        $prompt .= "3. Market opportunities and gaps\n";
        $prompt .= "4. Strategic recommendations\n";
        $prompt .= "5. Top 3 priority actions\n";

        return $prompt;
    }

    private function buildRoadmapPrompt(array $productData, array $featureData, array $productIdeas, int $quarters): string
    {
        $prompt = "Create a strategic {$quarters}-quarter product roadmap for:\n\n";
        $prompt .= "Product: {$productData['name']}\n";
        $prompt .= "Current Features: " . count($featureData) . "\n";
        $prompt .= "Pending Ideas: " . count($productIdeas) . "\n\n";

        if (!empty($featureData)) {
            $prompt .= "Current Features:\n";
            foreach (array_slice($featureData, 0, 5) as $feature) {
                $prompt .= "- {$feature['name']}\n";
            }
        }

        if (!empty($productIdeas)) {
            $prompt .= "\nPending Ideas:\n";
            foreach (array_slice($productIdeas, 0, 5) as $idea) {
                $effort = $idea['effort'] ?? 'N/A';
                $impact = $idea['impact'] ?? 'N/A';
                $prompt .= "- {$idea['name']} (Effort: {$effort}, Impact: {$impact})\n";
            }
        }

        $prompt .= "\nGenerate JSON roadmap:\n";
        $prompt .= "{\n";
        $prompt .= "  \"quarters\": {\n";
        $prompt .= "    \"Q1 2024\": {\n";
        $prompt .= "      \"theme\": \"Quarter theme\",\n";
        $prompt .= "      \"focus\": \"Primary focus area\",\n";
        $prompt .= "      \"initiatives\": [{\"name\": \"Initiative\", \"description\": \"Description\"}],\n";
        $prompt .= "      \"success_metrics\": [\"Metric 1\", \"Metric 2\"]\n";
        $prompt .= "    }\n";
        $prompt .= "  },\n";
        $prompt .= "  \"risks\": [\"Risk 1\", \"Risk 2\"],\n";
        $prompt .= "  \"dependencies\": [\"Dependency 1\", \"Dependency 2\"]\n";
        $prompt .= "}";

        return $prompt;
    }

    private function buildAnalysisPrompt(array $productData, array $featureData, array $reqData, array $productIdeas): string
    {
        $prompt = "Analyze this product portfolio and provide strategic insights:\n\n";
        $prompt .= "Product: {$productData['name']}\n";
        $prompt .= "Description: {$productData['description']}\n";
        $prompt .= "Features: " . count($featureData) . "\n";
        $prompt .= "Requirements: " . count($reqData) . "\n";
        $prompt .= "Ideas: " . count($productIdeas) . "\n\n";

        // Analyze feature status distribution
        $featureStatus = [];
        foreach ($featureData as $feature) {
            $status = $feature['status'] ?? 'Unknown';
            $featureStatus[$status] = ($featureStatus[$status] ?? 0) + 1;
        }

        if (!empty($featureStatus)) {
            $prompt .= "Feature Status Distribution:\n";
            foreach ($featureStatus as $status => $count) {
                $prompt .= "- {$status}: {$count}\n";
            }
        }

        // Analyze requirement priorities
        $reqPriorities = [];
        foreach ($reqData as $req) {
            $priority = $req['priority'] ?? 0;
            $reqPriorities[$priority] = ($reqPriorities[$priority] ?? 0) + 1;
        }

        if (!empty($reqPriorities)) {
            $prompt .= "\nRequirement Priorities:\n";
            ksort($reqPriorities);
            foreach ($reqPriorities as $priority => $count) {
                $prompt .= "- Priority {$priority}: {$count}\n";
            }
        }

        $prompt .= "\nProvide insights on:\n";
        $prompt .= "1. Product portfolio health\n";
        $prompt .= "2. Development focus areas\n";
        $prompt .= "3. Potential bottlenecks or risks\n";
        $prompt .= "4. Strategic recommendations\n";
        $prompt .= "5. Next steps for optimization\n";

        return $prompt;
    }

    private function displayBasicAnalytics(array $featureData, array $reqData, array $productIdeas): void
    {
        echo "📈 Analytics Summary:\n";

        // Feature analysis
        if (!empty($featureData)) {
            $featureStatus = [];
            foreach ($featureData as $feature) {
                $status = $feature['status'] ?? 'Unknown';
                $featureStatus[$status] = ($featureStatus[$status] ?? 0) + 1;
            }

            echo "   Features by Status:\n";
            foreach ($featureStatus as $status => $count) {
                $percentage = round(($count / count($featureData)) * 100, 1);
                echo "     {$status}: {$count} ({$percentage}%)\n";
            }
        }

        // Requirements analysis
        if (!empty($reqData)) {
            $reqStatus = [];
            $reqPriorities = [];

            foreach ($reqData as $req) {
                $status = $req['status'] ?? 'Unknown';
                $priority = $req['priority'] ?? 0;

                $reqStatus[$status] = ($reqStatus[$status] ?? 0) + 1;
                $reqPriorities[$priority] = ($reqPriorities[$priority] ?? 0) + 1;
            }

            echo "   Requirements by Status:\n";
            foreach ($reqStatus as $status => $count) {
                $percentage = round(($count / count($reqData)) * 100, 1);
                echo "     {$status}: {$count} ({$percentage}%)\n";
            }

            echo "   Requirements by Priority:\n";
            ksort($reqPriorities);
            foreach ($reqPriorities as $priority => $count) {
                echo "     Priority {$priority}: {$count}\n";
            }
        }

        // Ideas analysis
        if (!empty($productIdeas)) {
            $avgEffort = 0;
            $avgImpact = 0;
            $scoredIdeas = 0;

            foreach ($productIdeas as $idea) {
                if (isset($idea['effort']) && isset($idea['impact'])) {
                    $avgEffort += $idea['effort'];
                    $avgImpact += $idea['impact'];
                    $scoredIdeas++;
                }
            }

            if ($scoredIdeas > 0) {
                $avgEffort = round($avgEffort / $scoredIdeas, 1);
                $avgImpact = round($avgImpact / $scoredIdeas, 1);
                $ratio = round($avgImpact / max($avgEffort, 1), 2);

                echo "   Ideas Metrics:\n";
                echo "     Average Effort: {$avgEffort}/10\n";
                echo "     Average Impact: {$avgImpact}/10\n";
                echo "     Impact/Effort Ratio: {$ratio}\n";
            }
        }

        echo "\n";
    }

    /**
     * Run the example with error handling and cleanup
     */
    public function run(array $args): void
    {
        $command = $args[1] ?? 'demo';
        $param1 = $args[2] ?? null;
        $param2 = $args[3] ?? null;

        Loop::run(function () use ($command, $param1, $param2) {
            try {
                yield $this->fullcx->connect();

                switch ($command) {
                    case 'connect':
                        yield $this->testConnection();
                        break;

                    case 'ai-plan':
                        if (!$param1 || !$param2) {
                            echo "Usage: ai-plan <product-id> <feature-idea>\n";
                            break;
                        }
                        yield $this->aiFeaturePlanning($param1, $param2);
                        break;

                    case 'ai-ideas':
                        if (!$param1) {
                            echo "Usage: ai-ideas <product-id> [market-trends]\n";
                            break;
                        }
                        $trends = $param2 ? explode(',', $param2) : [];
                        yield $this->generateAIIdeas($param1, $trends);
                        break;

                    case 'ai-analysis':
                        if (!$param1) {
                            echo "Usage: ai-analysis <product-id> [competitors]\n";
                            break;
                        }
                        $competitors = $param2 ? explode(',', $param2) : [];
                        yield $this->aiCompetitiveAnalysis($param1, $competitors);
                        break;

                    case 'ai-roadmap':
                        if (!$param1) {
                            echo "Usage: ai-roadmap <product-id> [quarters]\n";
                            break;
                        }
                        $quarters = $param2 ? (int)$param2 : 4;
                        yield $this->generateAIRoadmap($param1, $quarters);
                        break;

                    case 'analyze':
                        if (!$param1) {
                            echo "Usage: analyze <product-id>\n";
                            break;
                        }
                        yield $this->enhancedAnalysis($param1);
                        break;

                    case 'demo':
                    default:
                        yield $this->runDemo();
                        break;
                }
            } catch (\Exception $e) {
                echo "❌ Error: {$e->getMessage()}\n";

                if ($_ENV['DEBUG'] ?? false) {
                    echo "\nStack trace:\n";
                    echo $e->getTraceAsString() . "\n";
                }
            } finally {
                try {
                    yield $this->fullcx->close();
                } catch (\Exception $e) {
                    // Ignore cleanup errors
                }
            }
        });
    }
}

// Handle command line execution
if (basename($_SERVER['argv'][0]) === 'fullcx-client.php') {
    try {
        $client = new AIEnhancedFullCXClient();
        $client->run($_SERVER['argv']);
    } catch (\Exception $e) {
        echo "❌ Failed to initialize: {$e->getMessage()}\n";
        echo "\nPlease ensure:\n";
        echo "1. FULLCX_API_TOKEN environment variable is set\n";
        echo "2. FullCX MCP server is accessible at https://full.cx/mcp\n";
        echo "3. OPENAI_API_KEY environment variable is set (for AI features)\n";
        echo "4. PHP extensions (json, mbstring) are installed\n";
        echo "5. OpenAI PHP client is installed: composer require openai-php/client\n";
        exit(1);
    }
}
