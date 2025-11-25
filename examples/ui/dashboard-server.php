#!/usr/bin/env php
<?php

/**
 * Dashboard Widgets MCP Server Example
 *
 * Demonstrates using UITemplate for creating dashboard-style widgets
 * including stats, tables, cards, and forms.
 *
 * Run with: php examples/ui/dashboard-server.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use MCP\Server\McpServer;
use MCP\Server\Transport\StdioServerTransport;
use MCP\Types\Implementation;
use MCP\UI\UIResource;
use MCP\UI\UITemplate;

use function Amp\async;

$server = new McpServer(
    new Implementation('dashboard-server', '1.0.0')
);

/**
 * Stats Dashboard Tool
 *
 * Returns a metrics dashboard with key statistics.
 */
$server->tool(
    'get_stats_dashboard',
    'Get a stats dashboard with key metrics',
    [
        'type' => 'object',
        'properties' => [
            'period' => [
                'type' => 'string',
                'enum' => ['today', 'week', 'month', 'year'],
                'description' => 'Time period for stats'
            ]
        ],
        'required' => []
    ],
    function (array $args): array {
        $period = $args['period'] ?? 'today';

        // Mock data - in production, fetch from your data source
        $multiplier = match ($period) {
            'today' => 1,
            'week' => 7,
            'month' => 30,
            'year' => 365,
        };

        $stats = [
            [
                'label' => 'Revenue',
                'value' => '$' . number_format(rand(1000, 5000) * $multiplier),
                'icon' => '💰',
                'color' => '#27ae60'
            ],
            [
                'label' => 'Users',
                'value' => number_format(rand(100, 500) * $multiplier),
                'icon' => '👥',
                'color' => '#3498db'
            ],
            [
                'label' => 'Orders',
                'value' => number_format(rand(50, 200) * $multiplier),
                'icon' => '📦',
                'color' => '#9b59b6'
            ],
            [
                'label' => 'Conversion',
                'value' => rand(2, 8) . '.' . rand(0, 9) . '%',
                'icon' => '📈',
                'color' => '#e67e22'
            ],
        ];

        $html = UITemplate::stats($stats, [
            'title' => '📊 Dashboard - ' . ucfirst($period),
            'gradient' => UITemplate::GRADIENT_BLUE,
            'columns' => 4
        ]);

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "Dashboard stats for {$period}: " . implode(', ', array_map(
                        fn($s) => "{$s['label']}: {$s['value']}",
                        $stats
                    ))
                ],
                UIResource::html("ui://dashboard/stats/{$period}", $html)
            ]
        ];
    }
);

/**
 * User Table Tool
 *
 * Returns an interactive table of users.
 */
$server->tool(
    'get_users_table',
    'Get a table of users',
    [
        'type' => 'object',
        'properties' => [
            'limit' => [
                'type' => 'integer',
                'description' => 'Number of users to show (max 20)'
            ]
        ],
        'required' => []
    ],
    function (array $args): array {
        $limit = min($args['limit'] ?? 10, 20);

        // Mock user data
        $firstNames = ['Alice', 'Bob', 'Charlie', 'Diana', 'Eve', 'Frank', 'Grace', 'Henry'];
        $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller'];
        $roles = ['Admin', 'User', 'Editor', 'Viewer'];
        $statuses = ['Active', 'Inactive', 'Pending'];

        $rows = [];
        for ($i = 1; $i <= $limit; $i++) {
            $firstName = $firstNames[array_rand($firstNames)];
            $lastName = $lastNames[array_rand($lastNames)];
            $rows[] = [
                $i,
                "{$firstName} {$lastName}",
                strtolower($firstName) . '@example.com',
                $roles[array_rand($roles)],
                $statuses[array_rand($statuses)]
            ];
        }

        $html = UITemplate::table(
            '👥 Users',
            ['ID', 'Name', 'Email', 'Role', 'Status'],
            $rows,
            ['gradient' => UITemplate::GRADIENT_GREEN]
        );

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "Showing {$limit} users"
                ],
                UIResource::html('ui://dashboard/users', $html)
            ]
        ];
    }
);

/**
 * Contact Form Tool
 *
 * Returns an interactive contact form.
 */
$server->tool(
    'get_contact_form',
    'Get an interactive contact form',
    [
        'type' => 'object',
        'properties' => [],
        'required' => []
    ],
    function (array $args): array {
        $html = UITemplate::form(
            [
                [
                    'name' => 'name',
                    'label' => 'Full Name',
                    'type' => 'text',
                    'required' => true,
                    'placeholder' => 'John Doe'
                ],
                [
                    'name' => 'email',
                    'label' => 'Email Address',
                    'type' => 'email',
                    'required' => true,
                    'placeholder' => 'john@example.com'
                ],
                [
                    'name' => 'subject',
                    'label' => 'Subject',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        'general' => 'General Inquiry',
                        'support' => 'Technical Support',
                        'billing' => 'Billing Question',
                        'feedback' => 'Feedback'
                    ]
                ],
                [
                    'name' => 'message',
                    'label' => 'Message',
                    'type' => 'textarea',
                    'required' => true,
                    'placeholder' => 'How can we help you?'
                ]
            ],
            [
                'title' => '📬 Contact Us',
                'submitLabel' => 'Send Message',
                'submitTool' => 'submit_contact',
                'gradient' => UITemplate::GRADIENT_PINK
            ]
        );

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => 'Contact form ready for input'
                ],
                UIResource::html('ui://dashboard/contact', $html)
            ]
        ];
    }
);

/**
 * Submit Contact Tool
 *
 * Handles form submission from the contact form.
 */
$server->tool(
    'submit_contact',
    'Submit a contact form',
    [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string'],
            'email' => ['type' => 'string'],
            'subject' => ['type' => 'string'],
            'message' => ['type' => 'string']
        ],
        'required' => ['name', 'email', 'message']
    ],
    function (array $args): array {
        // In production, save to database, send email, etc.
        $name = htmlspecialchars($args['name'] ?? '');
        $email = htmlspecialchars($args['email'] ?? '');
        $subject = htmlspecialchars($args['subject'] ?? 'general');

        $html = UITemplate::card([
            'title' => 'Message Sent!',
            'icon' => '✅',
            'content' => <<<HTML
            <p style="margin-bottom: 15px;">Thank you for contacting us, <strong>{$name}</strong>!</p>
            <p style="margin-bottom: 15px;">We've received your message about <strong>{$subject}</strong> and will respond to <strong>{$email}</strong> within 24 hours.</p>
            HTML,
            'gradient' => UITemplate::GRADIENT_GREEN,
            'actions' => [
                ['label' => '📬 Send Another', 'onclick' => "mcpToolCall('get_contact_form', {})"]
            ]
        ]);

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "Contact form submitted by {$name} ({$email})"
                ],
                UIResource::html('ui://dashboard/contact-success', $html)
            ]
        ];
    }
);

/**
 * Info Card Tool
 *
 * Returns a simple info card with custom content.
 */
$server->tool(
    'get_info_card',
    'Get an info card with custom content',
    [
        'type' => 'object',
        'properties' => [
            'title' => ['type' => 'string', 'description' => 'Card title'],
            'message' => ['type' => 'string', 'description' => 'Card message'],
            'icon' => ['type' => 'string', 'description' => 'Emoji icon'],
            'style' => [
                'type' => 'string',
                'enum' => ['default', 'success', 'warning', 'error', 'info'],
                'description' => 'Card style'
            ]
        ],
        'required' => ['title', 'message']
    ],
    function (array $args): array {
        $title = $args['title'];
        $message = htmlspecialchars($args['message']);
        $icon = $args['icon'] ?? '💡';
        $style = $args['style'] ?? 'default';

        $gradient = match ($style) {
            'success' => UITemplate::GRADIENT_GREEN,
            'warning' => UITemplate::GRADIENT_ORANGE,
            'error' => '#e74c3c, #c0392b',
            'info' => UITemplate::GRADIENT_BLUE,
            default => UITemplate::DEFAULT_GRADIENT,
        };

        $html = UITemplate::card([
            'title' => $title,
            'icon' => $icon,
            'content' => "<p>{$message}</p>",
            'gradient' => $gradient,
            'actions' => [
                ['label' => '👍 Got it', 'onclick' => "mcpNotify('User acknowledged: {$title}')"]
            ]
        ]);

        return [
            'content' => [
                ['type' => 'text', 'text' => "{$title}: {$args['message']}"],
                UIResource::html('ui://dashboard/card/' . time(), $html)
            ]
        ];
    }
);

// Start the server
async(function () use ($server) {
    fwrite(STDERR, "📊 Dashboard Server starting...\n");
    $transport = new StdioServerTransport();
    $server->connect($transport)->await();
})->await();

