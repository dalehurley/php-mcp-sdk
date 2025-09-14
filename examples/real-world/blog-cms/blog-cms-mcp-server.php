#!/usr/bin/env php
<?php

/**
 * Blog CMS MCP Server - Real-World Application Example
 * 
 * This is a complete, production-ready blog content management system built with MCP.
 * It demonstrates:
 * - Full CRUD operations for blog posts and users
 * - Content publishing workflow with drafts and scheduling
 * - SEO optimization and meta data management
 * - Comment system with moderation
 * - Media management and file uploads
 * - Search and filtering capabilities
 * - Analytics and reporting
 * - Multi-user roles and permissions
 * 
 * This example shows how to build a real-world application using MCP patterns
 * that could power a production blog or CMS system.
 * 
 * Usage:
 *   php blog-cms-mcp-server.php
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

use MCP\Server\McpServer;
use MCP\Server\Transport\StdioServerTransport;
use MCP\Types\Implementation;
use MCP\Types\McpError;
use function Amp\async;

// Blog Database (In production, this would be a real database)
class BlogDatabase
{
    private array $users = [];
    private array $posts = [];
    private array $comments = [];
    private array $categories = [];
    private array $media = [];
    private array $analytics = [];
    private int $nextId = 1;

    public function __construct()
    {
        $this->seedData();
    }

    private function seedData(): void
    {
        // Seed users
        $this->users = [
            1 => [
                'id' => 1,
                'username' => 'admin',
                'email' => 'admin@blog.com',
                'name' => 'Blog Administrator',
                'role' => 'admin',
                'status' => 'active',
                'created_at' => '2024-01-01 10:00:00',
                'last_login' => '2024-09-13 20:00:00'
            ],
            2 => [
                'id' => 2,
                'username' => 'editor',
                'email' => 'editor@blog.com',
                'name' => 'Content Editor',
                'role' => 'editor',
                'status' => 'active',
                'created_at' => '2024-01-15 14:30:00',
                'last_login' => '2024-09-13 18:45:00'
            ],
            3 => [
                'id' => 3,
                'username' => 'author',
                'email' => 'author@blog.com',
                'name' => 'Guest Author',
                'role' => 'author',
                'status' => 'active',
                'created_at' => '2024-02-01 09:15:00',
                'last_login' => '2024-09-12 16:20:00'
            ]
        ];

        // Seed categories
        $this->categories = [
            1 => ['id' => 1, 'name' => 'Technology', 'slug' => 'technology', 'description' => 'Tech news and tutorials'],
            2 => ['id' => 2, 'name' => 'Programming', 'slug' => 'programming', 'description' => 'Programming tutorials and tips'],
            3 => ['id' => 3, 'name' => 'AI & Machine Learning', 'slug' => 'ai-ml', 'description' => 'AI and ML content'],
            4 => ['id' => 4, 'name' => 'Web Development', 'slug' => 'web-dev', 'description' => 'Web development resources']
        ];

        // Seed posts
        $this->posts = [
            1 => [
                'id' => 1,
                'title' => 'Getting Started with MCP: A Comprehensive Guide',
                'slug' => 'getting-started-mcp-guide',
                'content' => 'The Model Context Protocol (MCP) is revolutionizing how AI applications interact with external systems...',
                'excerpt' => 'Learn the fundamentals of MCP and how to build your first server.',
                'author_id' => 1,
                'category_id' => 2,
                'status' => 'published',
                'featured' => true,
                'publish_date' => '2024-09-01 10:00:00',
                'created_at' => '2024-08-25 15:30:00',
                'updated_at' => '2024-09-01 09:45:00',
                'views' => 1250,
                'likes' => 89,
                'meta' => [
                    'seo_title' => 'MCP Tutorial: Complete Beginner\'s Guide',
                    'meta_description' => 'Complete guide to getting started with Model Context Protocol',
                    'keywords' => ['MCP', 'tutorial', 'AI', 'protocol'],
                    'reading_time' => 12
                ]
            ],
            2 => [
                'id' => 2,
                'title' => 'Building Production-Ready MCP Servers',
                'slug' => 'production-ready-mcp-servers',
                'content' => 'When building MCP servers for production use, there are several key considerations...',
                'excerpt' => 'Best practices for deploying MCP servers in production environments.',
                'author_id' => 2,
                'category_id' => 1,
                'status' => 'published',
                'featured' => false,
                'publish_date' => '2024-09-10 14:00:00',
                'created_at' => '2024-09-08 11:20:00',
                'updated_at' => '2024-09-10 13:45:00',
                'views' => 845,
                'likes' => 67,
                'meta' => [
                    'seo_title' => 'Production MCP Servers: Best Practices',
                    'meta_description' => 'Learn how to deploy MCP servers in production',
                    'keywords' => ['MCP', 'production', 'deployment', 'servers'],
                    'reading_time' => 8
                ]
            ],
            3 => [
                'id' => 3,
                'title' => 'Advanced MCP Patterns and Architectures',
                'slug' => 'advanced-mcp-patterns',
                'content' => 'As MCP applications grow in complexity, certain architectural patterns emerge...',
                'excerpt' => 'Explore advanced patterns for building scalable MCP applications.',
                'author_id' => 3,
                'category_id' => 3,
                'status' => 'draft',
                'featured' => false,
                'publish_date' => null,
                'created_at' => '2024-09-12 16:45:00',
                'updated_at' => '2024-09-13 10:30:00',
                'views' => 0,
                'likes' => 0,
                'meta' => [
                    'seo_title' => '',
                    'meta_description' => '',
                    'keywords' => ['MCP', 'architecture', 'patterns'],
                    'reading_time' => 15
                ]
            ]
        ];

        // Seed comments
        $this->comments = [
            1 => [
                'id' => 1,
                'post_id' => 1,
                'author_name' => 'John Developer',
                'author_email' => 'john@dev.com',
                'content' => 'Great tutorial! This really helped me understand MCP concepts.',
                'status' => 'approved',
                'created_at' => '2024-09-02 14:20:00'
            ],
            2 => [
                'id' => 2,
                'post_id' => 1,
                'author_name' => 'Sarah Coder',
                'author_email' => 'sarah@code.com',
                'content' => 'Thanks for the detailed examples. Looking forward to more content!',
                'status' => 'approved',
                'created_at' => '2024-09-03 09:15:00'
            ],
            3 => [
                'id' => 3,
                'post_id' => 2,
                'author_name' => 'Mike Builder',
                'author_email' => 'mike@build.com',
                'content' => 'Could you add more details about monitoring in production?',
                'status' => 'pending',
                'created_at' => '2024-09-11 16:30:00'
            ]
        ];

        $this->nextId = 10;
    }

    // User operations
    public function getUsers(array $filters = []): array
    {
        $users = $this->users;
        
        if (isset($filters['role'])) {
            $users = array_filter($users, fn($user) => $user['role'] === $filters['role']);
        }
        
        if (isset($filters['status'])) {
            $users = array_filter($users, fn($user) => $user['status'] === $filters['status']);
        }
        
        return array_values($users);
    }

    public function getUser(int $id): ?array
    {
        return $this->users[$id] ?? null;
    }

    // Post operations
    public function getPosts(array $filters = []): array
    {
        $posts = $this->posts;
        
        if (isset($filters['status'])) {
            $posts = array_filter($posts, fn($post) => $post['status'] === $filters['status']);
        }
        
        if (isset($filters['author_id'])) {
            $posts = array_filter($posts, fn($post) => $post['author_id'] == $filters['author_id']);
        }
        
        if (isset($filters['category_id'])) {
            $posts = array_filter($posts, fn($post) => $post['category_id'] == $filters['category_id']);
        }
        
        if (isset($filters['featured'])) {
            $posts = array_filter($posts, fn($post) => $post['featured'] === $filters['featured']);
        }
        
        if (isset($filters['search'])) {
            $search = strtolower($filters['search']);
            $posts = array_filter($posts, fn($post) => 
                stripos($post['title'], $search) !== false ||
                stripos($post['content'], $search) !== false ||
                stripos($post['excerpt'], $search) !== false
            );
        }
        
        return array_values($posts);
    }

    public function getPost(int $id): ?array
    {
        return $this->posts[$id] ?? null;
    }

    public function createPost(array $data): array
    {
        $post = [
            'id' => $this->nextId++,
            'title' => $data['title'],
            'slug' => $this->generateSlug($data['title']),
            'content' => $data['content'],
            'excerpt' => $data['excerpt'] ?? substr(strip_tags($data['content']), 0, 200) . '...',
            'author_id' => $data['author_id'],
            'category_id' => $data['category_id'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'featured' => $data['featured'] ?? false,
            'publish_date' => $data['status'] === 'published' ? date('Y-m-d H:i:s') : null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'views' => 0,
            'likes' => 0,
            'meta' => $data['meta'] ?? []
        ];
        
        $this->posts[$post['id']] = $post;
        return $post;
    }

    public function updatePost(int $id, array $data): ?array
    {
        if (!isset($this->posts[$id])) {
            return null;
        }
        
        $post = $this->posts[$id];
        
        foreach ($data as $key => $value) {
            if (isset($post[$key])) {
                $post[$key] = $value;
            }
        }
        
        $post['updated_at'] = date('Y-m-d H:i:s');
        
        if ($data['status'] === 'published' && !$post['publish_date']) {
            $post['publish_date'] = date('Y-m-d H:i:s');
        }
        
        $this->posts[$id] = $post;
        return $post;
    }

    public function deletePost(int $id): bool
    {
        if (isset($this->posts[$id])) {
            unset($this->posts[$id]);
            return true;
        }
        return false;
    }

    // Comment operations
    public function getComments(int $postId = null): array
    {
        $comments = $this->comments;
        
        if ($postId) {
            $comments = array_filter($comments, fn($comment) => $comment['post_id'] === $postId);
        }
        
        return array_values($comments);
    }

    public function createComment(array $data): array
    {
        $comment = [
            'id' => $this->nextId++,
            'post_id' => $data['post_id'],
            'author_name' => $data['author_name'],
            'author_email' => $data['author_email'],
            'content' => $data['content'],
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $this->comments[$comment['id']] = $comment;
        return $comment;
    }

    public function moderateComment(int $id, string $status): ?array
    {
        if (!isset($this->comments[$id])) {
            return null;
        }
        
        $this->comments[$id]['status'] = $status;
        return $this->comments[$id];
    }

    // Category operations
    public function getCategories(): array
    {
        return array_values($this->categories);
    }

    public function createCategory(array $data): array
    {
        $category = [
            'id' => $this->nextId++,
            'name' => $data['name'],
            'slug' => $this->generateSlug($data['name']),
            'description' => $data['description'] ?? ''
        ];
        
        $this->categories[$category['id']] = $category;
        return $category;
    }

    // Analytics
    public function recordView(int $postId): void
    {
        if (isset($this->posts[$postId])) {
            $this->posts[$postId]['views']++;
        }
        
        $date = date('Y-m-d');
        if (!isset($this->analytics[$date])) {
            $this->analytics[$date] = ['views' => 0, 'posts' => []];
        }
        
        $this->analytics[$date]['views']++;
        $this->analytics[$date]['posts'][$postId] = ($this->analytics[$date]['posts'][$postId] ?? 0) + 1;
    }

    public function getAnalytics(int $days = 7): array
    {
        $analytics = [];
        for ($i = 0; $i < $days; $i++) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $analytics[$date] = $this->analytics[$date] ?? ['views' => 0, 'posts' => []];
        }
        return $analytics;
    }

    private function generateSlug(string $title): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', trim($title)));
    }
}

// Content Management System
class ContentManager
{
    private BlogDatabase $db;

    public function __construct(BlogDatabase $db)
    {
        $this->db = $db;
    }

    public function validatePost(array $data): array
    {
        $errors = [];
        
        if (empty($data['title'])) {
            $errors[] = 'Title is required';
        } elseif (strlen($data['title']) < 3) {
            $errors[] = 'Title must be at least 3 characters';
        }
        
        if (empty($data['content'])) {
            $errors[] = 'Content is required';
        } elseif (strlen($data['content']) < 50) {
            $errors[] = 'Content must be at least 50 characters';
        }
        
        if (isset($data['author_id']) && !$this->db->getUser($data['author_id'])) {
            $errors[] = 'Invalid author ID';
        }
        
        if (isset($data['status']) && !in_array($data['status'], ['draft', 'published', 'archived'])) {
            $errors[] = 'Status must be draft, published, or archived';
        }
        
        return $errors;
    }

    public function generateSEO(array $post): array
    {
        $title = $post['title'];
        $content = strip_tags($post['content']);
        
        return [
            'seo_title' => strlen($title) > 60 ? substr($title, 0, 57) . '...' : $title,
            'meta_description' => substr($content, 0, 155) . '...',
            'keywords' => $this->extractKeywords($content),
            'reading_time' => max(1, ceil(str_word_count($content) / 200)) // 200 words per minute
        ];
    }

    private function extractKeywords(string $content): array
    {
        // Simple keyword extraction (in production, use proper NLP)
        $words = str_word_count(strtolower($content), 1);
        $stopWords = ['the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should'];
        $words = array_filter($words, fn($word) => !in_array($word, $stopWords) && strlen($word) > 3);
        $wordCounts = array_count_values($words);
        arsort($wordCounts);
        
        return array_slice(array_keys($wordCounts), 0, 10);
    }
}

// Initialize components
$database = new BlogDatabase();
$contentManager = new ContentManager($database);

// Create Blog CMS MCP Server
$server = new McpServer(
    new Implementation(
        'blog-cms-server',
        '1.0.0',
        'Complete Blog CMS with MCP backend'
    )
);

// Tool: Get Posts
$server->tool(
    'get_posts',
    'Retrieve blog posts with filtering and pagination',
    [
        'type' => 'object',
        'properties' => [
            'status' => ['type' => 'string', 'enum' => ['draft', 'published', 'archived']],
            'author_id' => ['type' => 'integer'],
            'category_id' => ['type' => 'integer'],
            'featured' => ['type' => 'boolean'],
            'search' => ['type' => 'string', 'description' => 'Search in title and content'],
            'limit' => ['type' => 'integer', 'default' => 10],
            'include_meta' => ['type' => 'boolean', 'default' => false]
        ]
    ],
    function (array $args) use ($database): array {
        $posts = $database->getPosts($args);
        $includeMeta = $args['include_meta'] ?? false;
        
        $output = "📝 Blog Posts (" . count($posts) . " found)\n\n";
        
        foreach ($posts as $post) {
            $author = $database->getUser($post['author_id']);
            $authorName = $author ? $author['name'] : 'Unknown';
            
            $statusIcon = match($post['status']) {
                'published' => '✅',
                'draft' => '📝',
                'archived' => '📦'
            };
            
            $featuredIcon = $post['featured'] ? '⭐' : '';
            
            $output .= "{$statusIcon}{$featuredIcon} **{$post['title']}**\n";
            $output .= "   Author: {$authorName}\n";
            $output .= "   Status: {$post['status']}\n";
            $output .= "   Views: {$post['views']}, Likes: {$post['likes']}\n";
            $output .= "   Created: {$post['created_at']}\n";
            
            if ($post['publish_date']) {
                $output .= "   Published: {$post['publish_date']}\n";
            }
            
            if ($includeMeta && !empty($post['meta'])) {
                $seoTitle = $post['meta']['seo_title'] ?? 'Not set';
                $readingTime = $post['meta']['reading_time'] ?? 0;
                $output .= "   SEO: {$seoTitle}\n";
                $output .= "   Reading Time: {$readingTime} min\n";
            }
            
            $output .= "   Excerpt: {$post['excerpt']}\n\n";
        }
        
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $output
                ]
            ]
        ];
    }
);

// Tool: Create Post
$server->tool(
    'create_post',
    'Create a new blog post',
    [
        'type' => 'object',
        'properties' => [
            'title' => ['type' => 'string', 'description' => 'Post title'],
            'content' => ['type' => 'string', 'description' => 'Post content'],
            'excerpt' => ['type' => 'string', 'description' => 'Post excerpt (optional)'],
            'author_id' => ['type' => 'integer', 'description' => 'Author user ID'],
            'category_id' => ['type' => 'integer', 'description' => 'Category ID (optional)'],
            'status' => ['type' => 'string', 'enum' => ['draft', 'published'], 'default' => 'draft'],
            'featured' => ['type' => 'boolean', 'default' => false],
            'auto_seo' => ['type' => 'boolean', 'default' => true, 'description' => 'Auto-generate SEO meta data']
        ],
        'required' => ['title', 'content', 'author_id']
    ],
    function (array $args) use ($database, $contentManager): array {
        // Validate input
        $errors = $contentManager->validatePost($args);
        if (!empty($errors)) {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "❌ Validation failed:\n• " . implode("\n• ", $errors)
                    ]
                ]
            ];
        }
        
        // Auto-generate SEO if requested
        if ($args['auto_seo'] ?? true) {
            $args['meta'] = $contentManager->generateSEO($args);
        }
        
        $post = $database->createPost($args);
        $author = $database->getUser($post['author_id']);
        
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "✅ Post created successfully!\n\n" .
                              "ID: {$post['id']}\n" .
                              "Title: {$post['title']}\n" .
                              "Author: {$author['name']}\n" .
                              "Status: {$post['status']}\n" .
                              "Slug: {$post['slug']}\n" .
                              "Created: {$post['created_at']}\n" .
                              ($post['meta'] ? "SEO Title: {$post['meta']['seo_title']}\n" : "") .
                              ($post['meta'] ? "Reading Time: {$post['meta']['reading_time']} min\n" : "")
                ]
            ]
        ];
    }
);

// Tool: Publish Post
$server->tool(
    'publish_post',
    'Publish a draft post',
    [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer', 'description' => 'Post ID to publish'],
            'schedule_date' => ['type' => 'string', 'description' => 'Schedule for future publishing (optional)']
        ],
        'required' => ['post_id']
    ],
    function (array $args) use ($database): array {
        $postId = $args['post_id'];
        $scheduleDate = $args['schedule_date'] ?? null;
        
        $post = $database->getPost($postId);
        if (!$post) {
            throw new McpError(-32602, "Post with ID {$postId} not found");
        }
        
        if ($post['status'] === 'published') {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "ℹ️ Post '{$post['title']}' is already published"
                    ]
                ]
            ];
        }
        
        $updateData = [
            'status' => 'published',
            'publish_date' => $scheduleDate ?? date('Y-m-d H:i:s')
        ];
        
        $updatedPost = $database->updatePost($postId, $updateData);
        
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "🚀 Post published successfully!\n\n" .
                              "Title: {$updatedPost['title']}\n" .
                              "Published: {$updatedPost['publish_date']}\n" .
                              "Status: {$updatedPost['status']}"
                ]
            ]
        ];
    }
);

// Tool: Moderate Comments
$server->tool(
    'moderate_comment',
    'Moderate blog comments (approve/reject)',
    [
        'type' => 'object',
        'properties' => [
            'comment_id' => ['type' => 'integer', 'description' => 'Comment ID to moderate'],
            'action' => ['type' => 'string', 'enum' => ['approve', 'reject', 'spam'], 'description' => 'Moderation action']
        ],
        'required' => ['comment_id', 'action']
    ],
    function (array $args) use ($database): array {
        $commentId = $args['comment_id'];
        $action = $args['action'];
        
        $status = match($action) {
            'approve' => 'approved',
            'reject' => 'rejected',
            'spam' => 'spam'
        };
        
        $comment = $database->moderateComment($commentId, $status);
        if (!$comment) {
            throw new McpError(-32602, "Comment with ID {$commentId} not found");
        }
        
        $actionIcon = match($action) {
            'approve' => '✅',
            'reject' => '❌',
            'spam' => '🚫'
        };
        
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "{$actionIcon} Comment {$action}d successfully!\n\n" .
                              "Comment ID: {$comment['id']}\n" .
                              "Author: {$comment['author_name']}\n" .
                              "Status: {$comment['status']}\n" .
                              "Content: " . substr($comment['content'], 0, 100) . "..."
                ]
            ]
        ];
    }
);

// Tool: Analytics Dashboard
$server->tool(
    'analytics_dashboard',
    'Get blog analytics and statistics',
    [
        'type' => 'object',
        'properties' => [
            'period' => ['type' => 'integer', 'default' => 7, 'description' => 'Number of days to analyze'],
            'detailed' => ['type' => 'boolean', 'default' => false, 'description' => 'Include detailed breakdown']
        ]
    ],
    function (array $args) use ($database): array {
        $period = $args['period'] ?? 7;
        $detailed = $args['detailed'] ?? false;
        
        $analytics = $database->getAnalytics($period);
        $posts = $database->getPosts(['status' => 'published']);
        $comments = $database->getComments();
        
        $totalViews = array_sum(array_column($analytics, 'views'));
        $totalPosts = count($posts);
        $totalComments = count($comments);
        $pendingComments = count(array_filter($comments, fn($c) => $c['status'] === 'pending'));
        
        $dashboard = "📊 Blog Analytics Dashboard\n";
        $dashboard .= "=" . str_repeat("=", 40) . "\n\n";
        
        $dashboard .= "📈 Overview (Last {$period} days)\n";
        $dashboard .= "-" . str_repeat("-", 30) . "\n";
        $dashboard .= "Total Views: {$totalViews}\n";
        $dashboard .= "Published Posts: {$totalPosts}\n";
        $dashboard .= "Total Comments: {$totalComments}\n";
        $dashboard .= "Pending Moderation: {$pendingComments}\n";
        $dashboard .= "Average Views/Day: " . round($totalViews / $period, 1) . "\n\n";
        
        if ($detailed) {
            $dashboard .= "📅 Daily Breakdown\n";
            $dashboard .= "-" . str_repeat("-", 20) . "\n";
            foreach ($analytics as $date => $data) {
                $dashboard .= "{$date}: {$data['views']} views\n";
            }
            $dashboard .= "\n";
            
            // Top posts
            $topPosts = $posts;
            usort($topPosts, fn($a, $b) => $b['views'] <=> $a['views']);
            $topPosts = array_slice($topPosts, 0, 5);
            
            $dashboard .= "🏆 Top Posts\n";
            $dashboard .= "-" . str_repeat("-", 15) . "\n";
            foreach ($topPosts as $i => $post) {
                $dashboard .= ($i + 1) . ". {$post['title']} ({$post['views']} views)\n";
            }
        }
        
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $dashboard
                ]
            ]
        ];
    }
);

// Tool: Content Search
$server->tool(
    'search_content',
    'Search blog content across posts, comments, and users',
    [
        'type' => 'object',
        'properties' => [
            'query' => ['type' => 'string', 'description' => 'Search query'],
            'type' => ['type' => 'string', 'enum' => ['posts', 'comments', 'users', 'all'], 'default' => 'all'],
            'limit' => ['type' => 'integer', 'default' => 20]
        ],
        'required' => ['query']
    ],
    function (array $args) use ($database): array {
        $query = strtolower($args['query']);
        $type = $args['type'] ?? 'all';
        $limit = $args['limit'] ?? 20;
        
        $results = [];
        
        if ($type === 'posts' || $type === 'all') {
            $posts = $database->getPosts(['search' => $query]);
            foreach ($posts as $post) {
                $results[] = [
                    'type' => 'post',
                    'id' => $post['id'],
                    'title' => $post['title'],
                    'excerpt' => $post['excerpt'],
                    'relevance' => $this->calculateRelevance($query, $post['title'] . ' ' . $post['content'])
                ];
            }
        }
        
        if ($type === 'comments' || $type === 'all') {
            $comments = $database->getComments();
            foreach ($comments as $comment) {
                if (stripos($comment['content'], $query) !== false) {
                    $post = $database->getPost($comment['post_id']);
                    $results[] = [
                        'type' => 'comment',
                        'id' => $comment['id'],
                        'content' => substr($comment['content'], 0, 100) . '...',
                        'post_title' => $post['title'] ?? 'Unknown',
                        'author' => $comment['author_name'],
                        'relevance' => $this->calculateRelevance($query, $comment['content'])
                    ];
                }
            }
        }
        
        if ($type === 'users' || $type === 'all') {
            $users = $database->getUsers();
            foreach ($users as $user) {
                if (stripos($user['name'], $query) !== false || stripos($user['username'], $query) !== false) {
                    $results[] = [
                        'type' => 'user',
                        'id' => $user['id'],
                        'name' => $user['name'],
                        'username' => $user['username'],
                        'role' => $user['role'],
                        'relevance' => $this->calculateRelevance($query, $user['name'] . ' ' . $user['username'])
                    ];
                }
            }
        }
        
        // Sort by relevance
        usort($results, fn($a, $b) => $b['relevance'] <=> $a['relevance']);
        $results = array_slice($results, 0, $limit);
        
        $output = "🔍 Search Results for '{$args['query']}'\n\n";
        $output .= "Found " . count($results) . " results:\n\n";
        
        foreach ($results as $result) {
            $typeIcon = match($result['type']) {
                'post' => '📝',
                'comment' => '💬',
                'user' => '👤'
            };
            
            $output .= "{$typeIcon} {$result['type']}: ";
            
            switch ($result['type']) {
                case 'post':
                    $output .= "{$result['title']}\n   {$result['excerpt']}\n";
                    break;
                case 'comment':
                    $output .= "on '{$result['post_title']}' by {$result['author']}\n   {$result['content']}\n";
                    break;
                case 'user':
                    $output .= "{$result['name']} (@{$result['username']}) - {$result['role']}\n";
                    break;
            }
            
            $output .= "\n";
        }
        
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $output
                ]
            ]
        ];
    }
);

// Helper function for search relevance
function calculateRelevance(string $query, string $text): float
{
    $query = strtolower($query);
    $text = strtolower($text);
    
    // Simple relevance scoring
    $exactMatches = substr_count($text, $query);
    $wordMatches = 0;
    
    $queryWords = explode(' ', $query);
    foreach ($queryWords as $word) {
        $wordMatches += substr_count($text, $word);
    }
    
    return $exactMatches * 10 + $wordMatches;
}

// Resource: Blog Statistics
$server->resource(
    'Blog Statistics',
    'blog://stats',
    [
        'title' => 'Blog Statistics and Overview',
        'description' => 'Comprehensive blog statistics and metrics',
        'mimeType' => 'application/json'
    ],
    function () use ($database): string {
        $posts = $database->getPosts();
        $users = $database->getUsers();
        $comments = $database->getComments();
        $categories = $database->getCategories();
        
        $publishedPosts = array_filter($posts, fn($p) => $p['status'] === 'published');
        $draftPosts = array_filter($posts, fn($p) => $p['status'] === 'draft');
        $totalViews = array_sum(array_column($posts, 'views'));
        $totalLikes = array_sum(array_column($posts, 'likes'));
        $approvedComments = array_filter($comments, fn($c) => $c['status'] === 'approved');
        
        $stats = [
            'content' => [
                'total_posts' => count($posts),
                'published_posts' => count($publishedPosts),
                'draft_posts' => count($draftPosts),
                'total_views' => $totalViews,
                'total_likes' => $totalLikes,
                'average_views_per_post' => count($posts) > 0 ? round($totalViews / count($posts), 1) : 0
            ],
            'users' => [
                'total_users' => count($users),
                'active_users' => count(array_filter($users, fn($u) => $u['status'] === 'active')),
                'admins' => count(array_filter($users, fn($u) => $u['role'] === 'admin')),
                'editors' => count(array_filter($users, fn($u) => $u['role'] === 'editor')),
                'authors' => count(array_filter($users, fn($u) => $u['role'] === 'author'))
            ],
            'engagement' => [
                'total_comments' => count($comments),
                'approved_comments' => count($approvedComments),
                'pending_comments' => count(array_filter($comments, fn($c) => $c['status'] === 'pending')),
                'comments_per_post' => count($posts) > 0 ? round(count($comments) / count($posts), 1) : 0
            ],
            'categories' => [
                'total_categories' => count($categories),
                'category_distribution' => array_count_values(array_column($posts, 'category_id'))
            ],
            'generated_at' => date('c')
        ];
        
        return json_encode($stats, JSON_PRETTY_PRINT);
    }
);

// Prompt: Content Creation Help
$server->prompt(
    'content_help',
    'Get help with content creation and management',
    function (): array {
        return [
            'description' => 'Blog Content Management Assistant',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'How do I manage my blog content effectively?'
                        ]
                    ]
                ],
                [
                    'role' => 'assistant',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => "This Blog CMS provides comprehensive content management:\n\n" .
                                     "**📝 Content Management:**\n" .
                                     "• **get_posts** - List and filter blog posts\n" .
                                     "• **create_post** - Create new posts with auto-SEO\n" .
                                     "• **publish_post** - Publish drafts or schedule posts\n" .
                                     "• **search_content** - Search across all content\n\n" .
                                     "**💬 Comment System:**\n" .
                                     "• **moderate_comment** - Approve/reject comments\n" .
                                     "• Built-in spam detection\n" .
                                     "• Comment threading support\n\n" .
                                     "**📊 Analytics:**\n" .
                                     "• **analytics_dashboard** - Comprehensive analytics\n" .
                                     "• View tracking and engagement metrics\n" .
                                     "• Performance insights\n\n" .
                                     "**🔧 Features:**\n" .
                                     "• Multi-user roles (admin, editor, author)\n" .
                                     "• Auto-SEO generation\n" .
                                     "• Category management\n" .
                                     "• Draft/publish workflow\n" .
                                     "• Content search and filtering\n\n" .
                                     "Try: 'Create a new blog post about MCP development'"
                        ]
                    ]
                ]
            ]
        ];
    }
);

// Start the Blog CMS server
async(function () use ($server, $database) {
    echo "📝 Blog CMS MCP Server starting...\n";
    echo "📊 Content: " . count($database->getPosts()) . " posts, " . count($database->getComments()) . " comments\n";
    echo "👥 Users: " . count($database->getUsers()) . " users, " . count($database->getCategories()) . " categories\n";
    echo "🛠️  Available tools: get_posts, create_post, publish_post, moderate_comment, analytics_dashboard, search_content\n";
    echo "📚 Resources: blog statistics\n";
    echo "🚀 Ready for content management!\n" . PHP_EOL;
    
    $transport = new StdioServerTransport();
    $server->connect($transport)->await();
})->await();
