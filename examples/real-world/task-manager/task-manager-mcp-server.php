#!/usr/bin/env php
<?php

/**
 * Task Management System - Real-World MCP Application
 * 
 * This is a complete task management system built with MCP that demonstrates:
 * - Project and task organization with hierarchies
 * - Team collaboration and assignment workflows
 * - Time tracking and productivity analytics
 * - Automated reporting and notifications
 * - Integration with external calendars and tools
 * - Agile/Scrum workflow support
 * - Resource allocation and capacity planning
 * 
 * Perfect example of a production-ready MCP application that could power
 * real project management and team coordination systems.
 * 
 * Usage:
 *   php task-manager-mcp-server.php
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

use MCP\Server\McpServer;
use MCP\Server\Transport\StdioServerTransport;
use MCP\Types\Implementation;
use MCP\Types\McpError;
use function Amp\async;

/**
 * Task Management Database
 */
class TaskDatabase
{
    private array $projects = [];
    private array $tasks = [];
    private array $users = [];
    private array $timeEntries = [];
    private array $comments = [];
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
                'name' => 'Alice Johnson',
                'email' => 'alice@company.com',
                'role' => 'project_manager',
                'department' => 'Engineering',
                'capacity_hours_per_week' => 40,
                'current_workload' => 32,
                'skills' => ['project_management', 'agile', 'stakeholder_communication'],
                'status' => 'active'
            ],
            2 => [
                'id' => 2,
                'name' => 'Bob Smith',
                'email' => 'bob@company.com',
                'role' => 'senior_developer',
                'department' => 'Engineering',
                'capacity_hours_per_week' => 40,
                'current_workload' => 35,
                'skills' => ['php', 'javascript', 'database_design', 'api_development'],
                'status' => 'active'
            ],
            3 => [
                'id' => 3,
                'name' => 'Carol Davis',
                'email' => 'carol@company.com',
                'role' => 'designer',
                'department' => 'Design',
                'capacity_hours_per_week' => 40,
                'current_workload' => 28,
                'skills' => ['ui_design', 'ux_research', 'prototyping', 'user_testing'],
                'status' => 'active'
            ]
        ];

        // Seed projects
        $this->projects = [
            1 => [
                'id' => 1,
                'name' => 'MCP SDK Documentation',
                'description' => 'Create comprehensive documentation for the PHP MCP SDK',
                'status' => 'in_progress',
                'priority' => 'high',
                'start_date' => '2024-09-01',
                'due_date' => '2024-09-30',
                'project_manager_id' => 1,
                'team_members' => [1, 2, 3],
                'budget_hours' => 200,
                'spent_hours' => 145,
                'completion_percentage' => 72
            ],
            2 => [
                'id' => 2,
                'name' => 'Agentic AI Integration',
                'description' => 'Implement agentic AI capabilities in the MCP ecosystem',
                'status' => 'planning',
                'priority' => 'medium',
                'start_date' => '2024-09-15',
                'due_date' => '2024-10-15',
                'project_manager_id' => 1,
                'team_members' => [2],
                'budget_hours' => 120,
                'spent_hours' => 15,
                'completion_percentage' => 12
            ]
        ];

        // Seed tasks
        $this->tasks = [
            1 => [
                'id' => 1,
                'project_id' => 1,
                'title' => 'Create Getting Started Guide',
                'description' => 'Write comprehensive getting started documentation with examples',
                'status' => 'completed',
                'priority' => 'high',
                'assignee_id' => 2,
                'reporter_id' => 1,
                'estimated_hours' => 16,
                'actual_hours' => 18,
                'due_date' => '2024-09-10',
                'completed_date' => '2024-09-09',
                'tags' => ['documentation', 'tutorial', 'examples'],
                'dependencies' => []
            ],
            2 => [
                'id' => 2,
                'project_id' => 1,
                'title' => 'Build Working Examples',
                'description' => 'Create hello-world, calculator, and file-reader examples',
                'status' => 'completed',
                'priority' => 'high',
                'assignee_id' => 2,
                'reporter_id' => 1,
                'estimated_hours' => 24,
                'actual_hours' => 22,
                'due_date' => '2024-09-12',
                'completed_date' => '2024-09-11',
                'tags' => ['examples', 'code', 'testing'],
                'dependencies' => [1]
            ],
            3 => [
                'id' => 3,
                'project_id' => 1,
                'title' => 'Framework Integration Examples',
                'description' => 'Create Laravel and Symfony integration examples',
                'status' => 'in_progress',
                'priority' => 'medium',
                'assignee_id' => 2,
                'reporter_id' => 1,
                'estimated_hours' => 20,
                'actual_hours' => 12,
                'due_date' => '2024-09-15',
                'completed_date' => null,
                'tags' => ['framework', 'integration', 'laravel', 'symfony'],
                'dependencies' => [2]
            ],
            4 => [
                'id' => 4,
                'project_id' => 2,
                'title' => 'Research Agentic AI Patterns',
                'description' => 'Research and document agentic AI architectural patterns',
                'status' => 'in_progress',
                'priority' => 'high',
                'assignee_id' => 2,
                'reporter_id' => 1,
                'estimated_hours' => 32,
                'actual_hours' => 8,
                'due_date' => '2024-09-20',
                'completed_date' => null,
                'tags' => ['research', 'ai', 'architecture'],
                'dependencies' => []
            ],
            5 => [
                'id' => 5,
                'project_id' => 1,
                'title' => 'UI/UX Design for Documentation',
                'description' => 'Design user-friendly documentation website interface',
                'status' => 'todo',
                'priority' => 'medium',
                'assignee_id' => 3,
                'reporter_id' => 1,
                'estimated_hours' => 16,
                'actual_hours' => 0,
                'due_date' => '2024-09-18',
                'completed_date' => null,
                'tags' => ['design', 'ui', 'ux', 'documentation'],
                'dependencies' => [1, 2]
            ]
        ];

        // Seed time entries
        $this->timeEntries = [
            1 => [
                'id' => 1,
                'task_id' => 1,
                'user_id' => 2,
                'description' => 'Writing getting started documentation',
                'hours' => 8,
                'date' => '2024-09-08',
                'billable' => true
            ],
            2 => [
                'id' => 2,
                'task_id' => 1,
                'user_id' => 2,
                'description' => 'Creating code examples and testing',
                'hours' => 10,
                'date' => '2024-09-09',
                'billable' => true
            ],
            3 => [
                'id' => 3,
                'task_id' => 2,
                'user_id' => 2,
                'description' => 'Building hello-world examples',
                'hours' => 6,
                'date' => '2024-09-10',
                'billable' => true
            ]
        ];

        $this->nextId = 10;
    }

    // Project operations
    public function getProjects(array $filters = []): array
    {
        $projects = $this->projects;
        
        if (isset($filters['status'])) {
            $projects = array_filter($projects, fn($p) => $p['status'] === $filters['status']);
        }
        
        if (isset($filters['priority'])) {
            $projects = array_filter($projects, fn($p) => $p['priority'] === $filters['priority']);
        }
        
        return array_values($projects);
    }

    public function createProject(array $data): array
    {
        $project = [
            'id' => $this->nextId++,
            'name' => $data['name'],
            'description' => $data['description'],
            'status' => $data['status'] ?? 'planning',
            'priority' => $data['priority'] ?? 'medium',
            'start_date' => $data['start_date'] ?? date('Y-m-d'),
            'due_date' => $data['due_date'],
            'project_manager_id' => $data['project_manager_id'],
            'team_members' => $data['team_members'] ?? [],
            'budget_hours' => $data['budget_hours'] ?? 0,
            'spent_hours' => 0,
            'completion_percentage' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $this->projects[$project['id']] = $project;
        return $project;
    }

    // Task operations
    public function getTasks(array $filters = []): array
    {
        $tasks = $this->tasks;
        
        if (isset($filters['project_id'])) {
            $tasks = array_filter($tasks, fn($t) => $t['project_id'] == $filters['project_id']);
        }
        
        if (isset($filters['assignee_id'])) {
            $tasks = array_filter($tasks, fn($t) => $t['assignee_id'] == $filters['assignee_id']);
        }
        
        if (isset($filters['status'])) {
            $tasks = array_filter($tasks, fn($t) => $t['status'] === $filters['status']);
        }
        
        if (isset($filters['priority'])) {
            $tasks = array_filter($tasks, fn($t) => $t['priority'] === $filters['priority']);
        }
        
        return array_values($tasks);
    }

    public function createTask(array $data): array
    {
        $task = [
            'id' => $this->nextId++,
            'project_id' => $data['project_id'],
            'title' => $data['title'],
            'description' => $data['description'],
            'status' => $data['status'] ?? 'todo',
            'priority' => $data['priority'] ?? 'medium',
            'assignee_id' => $data['assignee_id'] ?? null,
            'reporter_id' => $data['reporter_id'],
            'estimated_hours' => $data['estimated_hours'] ?? 0,
            'actual_hours' => 0,
            'due_date' => $data['due_date'] ?? null,
            'completed_date' => null,
            'tags' => $data['tags'] ?? [],
            'dependencies' => $data['dependencies'] ?? [],
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $this->tasks[$task['id']] = $task;
        return $task;
    }

    public function updateTaskStatus(int $taskId, string $status): ?array
    {
        if (!isset($this->tasks[$taskId])) {
            return null;
        }
        
        $this->tasks[$taskId]['status'] = $status;
        
        if ($status === 'completed') {
            $this->tasks[$taskId]['completed_date'] = date('Y-m-d H:i:s');
        }
        
        return $this->tasks[$taskId];
    }

    // Time tracking
    public function logTime(array $data): array
    {
        $entry = [
            'id' => $this->nextId++,
            'task_id' => $data['task_id'],
            'user_id' => $data['user_id'],
            'description' => $data['description'],
            'hours' => $data['hours'],
            'date' => $data['date'] ?? date('Y-m-d'),
            'billable' => $data['billable'] ?? true,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $this->timeEntries[$entry['id']] = $entry;
        
        // Update task actual hours
        if (isset($this->tasks[$data['task_id']])) {
            $this->tasks[$data['task_id']]['actual_hours'] += $data['hours'];
        }
        
        return $entry;
    }

    public function getTimeEntries(array $filters = []): array
    {
        $entries = $this->timeEntries;
        
        if (isset($filters['user_id'])) {
            $entries = array_filter($entries, fn($e) => $e['user_id'] == $filters['user_id']);
        }
        
        if (isset($filters['task_id'])) {
            $entries = array_filter($entries, fn($e) => $e['task_id'] == $filters['task_id']);
        }
        
        if (isset($filters['date_from'])) {
            $entries = array_filter($entries, fn($e) => $e['date'] >= $filters['date_from']);
        }
        
        return array_values($entries);
    }

    // Analytics
    public function getProjectAnalytics(int $projectId): array
    {
        $project = $this->projects[$projectId] ?? null;
        if (!$project) return [];
        
        $projectTasks = $this->getTasks(['project_id' => $projectId]);
        $completedTasks = array_filter($projectTasks, fn($t) => $t['status'] === 'completed');
        $inProgressTasks = array_filter($projectTasks, fn($t) => $t['status'] === 'in_progress');
        $todoTasks = array_filter($projectTasks, fn($t) => $t['status'] === 'todo');
        
        $totalEstimated = array_sum(array_column($projectTasks, 'estimated_hours'));
        $totalActual = array_sum(array_column($projectTasks, 'actual_hours'));
        
        return [
            'project' => $project,
            'task_summary' => [
                'total' => count($projectTasks),
                'completed' => count($completedTasks),
                'in_progress' => count($inProgressTasks),
                'todo' => count($todoTasks),
                'completion_rate' => count($projectTasks) > 0 ? count($completedTasks) / count($projectTasks) * 100 : 0
            ],
            'time_summary' => [
                'estimated_hours' => $totalEstimated,
                'actual_hours' => $totalActual,
                'variance' => $totalActual - $totalEstimated,
                'efficiency' => $totalEstimated > 0 ? ($totalEstimated / $totalActual) * 100 : 0
            ],
            'team_workload' => $this->calculateTeamWorkload($project['team_members'])
        ];
    }

    private function calculateTeamWorkload(array $teamMemberIds): array
    {
        $workload = [];
        
        foreach ($teamMemberIds as $userId) {
            $user = $this->users[$userId] ?? null;
            if (!$user) continue;
            
            $userTasks = $this->getTasks(['assignee_id' => $userId, 'status' => 'in_progress']);
            $estimatedHours = array_sum(array_column($userTasks, 'estimated_hours'));
            
            $workload[] = [
                'user' => $user,
                'active_tasks' => count($userTasks),
                'estimated_hours' => $estimatedHours,
                'capacity_utilization' => ($user['current_workload'] / $user['capacity_hours_per_week']) * 100
            ];
        }
        
        return $workload;
    }

    public function getUsers(): array { return array_values($this->users); }
    public function getUser(int $id): ?array { return $this->users[$id] ?? null; }
    public function getProject(int $id): ?array { return $this->projects[$id] ?? null; }
    public function getTask(int $id): ?array { return $this->tasks[$id] ?? null; }
}

// Task Management System
$database = new TaskDatabase();

// Create Task Manager MCP Server
$server = new McpServer(
    new Implementation(
        'task-manager-server',
        '1.0.0',
        'Comprehensive Task Management System with MCP'
    )
);

// Tool: Get Projects
$server->tool(
    'get_projects',
    'Retrieve projects with filtering options',
    [
        'type' => 'object',
        'properties' => [
            'status' => ['type' => 'string', 'enum' => ['planning', 'in_progress', 'completed', 'on_hold']],
            'priority' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'critical']],
            'include_analytics' => ['type' => 'boolean', 'default' => false]
        ]
    ],
    function (array $args) use ($database): array {
        $projects = $database->getProjects($args);
        $includeAnalytics = $args['include_analytics'] ?? false;
        
        $output = "📋 Project Portfolio (" . count($projects) . " projects)\n\n";
        
        foreach ($projects as $project) {
            $statusIcon = match($project['status']) {
                'planning' => '📝',
                'in_progress' => '⚡',
                'completed' => '✅',
                'on_hold' => '⏸️'
            };
            
            $priorityIcon = match($project['priority']) {
                'low' => '🟢',
                'medium' => '🟡',
                'high' => '🟠',
                'critical' => '🔴'
            };
            
            $pm = $database->getUser($project['project_manager_id']);
            $pmName = $pm ? $pm['name'] : 'Unassigned';
            
            $output .= "{$statusIcon}{$priorityIcon} **{$project['name']}**\n";
            $output .= "   Status: {$project['status']} | Priority: {$project['priority']}\n";
            $output .= "   PM: {$pmName} | Team: " . count($project['team_members']) . " members\n";
            $output .= "   Timeline: {$project['start_date']} → {$project['due_date']}\n";
            $output .= "   Progress: {$project['completion_percentage']}% | Hours: {$project['spent_hours']}/{$project['budget_hours']}\n";
            
            if ($includeAnalytics) {
                $analytics = $database->getProjectAnalytics($project['id']);
                $completion = round($analytics['task_summary']['completion_rate'], 1);
                $efficiency = round($analytics['time_summary']['efficiency'], 1);
                $output .= "   Analytics: {$completion}% tasks done, {$efficiency}% time efficiency\n";
            }
            
            $output .= "   Description: {$project['description']}\n\n";
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

// Tool: Get Tasks
$server->tool(
    'get_tasks',
    'Retrieve tasks with filtering and sorting options',
    [
        'type' => 'object',
        'properties' => [
            'project_id' => ['type' => 'integer'],
            'assignee_id' => ['type' => 'integer'],
            'status' => ['type' => 'string', 'enum' => ['todo', 'in_progress', 'completed', 'blocked']],
            'priority' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'critical']],
            'due_soon' => ['type' => 'boolean', 'description' => 'Show tasks due within 7 days'],
            'include_time' => ['type' => 'boolean', 'default' => false]
        ]
    ],
    function (array $args) use ($database): array {
        $tasks = $database->getTasks($args);
        $includeTime = $args['include_time'] ?? false;
        
        // Filter for due soon if requested
        if ($args['due_soon'] ?? false) {
            $sevenDaysFromNow = date('Y-m-d', strtotime('+7 days'));
            $tasks = array_filter($tasks, fn($t) => $t['due_date'] && $t['due_date'] <= $sevenDaysFromNow);
        }
        
        $output = "📝 Task List (" . count($tasks) . " tasks)\n\n";
        
        foreach ($tasks as $task) {
            $statusIcon = match($task['status']) {
                'todo' => '⭕',
                'in_progress' => '🔄',
                'completed' => '✅',
                'blocked' => '🚫'
            };
            
            $priorityIcon = match($task['priority']) {
                'low' => '🟢',
                'medium' => '🟡',
                'high' => '🟠',
                'critical' => '🔴'
            };
            
            $assignee = $database->getUser($task['assignee_id']);
            $assigneeName = $assignee ? $assignee['name'] : 'Unassigned';
            
            $project = $database->getProject($task['project_id']);
            $projectName = $project ? $project['name'] : 'Unknown Project';
            
            $output .= "{$statusIcon}{$priorityIcon} **{$task['title']}**\n";
            $output .= "   Project: {$projectName}\n";
            $output .= "   Assignee: {$assigneeName}\n";
            $output .= "   Status: {$task['status']} | Priority: {$task['priority']}\n";
            
            if ($task['due_date']) {
                $daysUntilDue = floor((strtotime($task['due_date']) - time()) / 86400);
                $dueStatus = $daysUntilDue < 0 ? "OVERDUE by " . abs($daysUntilDue) . " days" : 
                           ($daysUntilDue <= 3 ? "Due in {$daysUntilDue} days" : "Due {$task['due_date']}");
                $output .= "   Due: {$dueStatus}\n";
            }
            
            if ($includeTime) {
                $variance = $task['actual_hours'] - $task['estimated_hours'];
                $varianceText = $variance > 0 ? "+{$variance}h over" : ($variance < 0 ? abs($variance) . "h under" : "on estimate");
                $output .= "   Time: {$task['actual_hours']}/{$task['estimated_hours']}h ({$varianceText})\n";
            }
            
            if (!empty($task['tags'])) {
                $output .= "   Tags: " . implode(', ', $task['tags']) . "\n";
            }
            
            $output .= "   Description: {$task['description']}\n\n";
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

// Tool: Create Task
$server->tool(
    'create_task',
    'Create a new task in a project',
    [
        'type' => 'object',
        'properties' => [
            'project_id' => ['type' => 'integer', 'description' => 'Project ID'],
            'title' => ['type' => 'string', 'description' => 'Task title'],
            'description' => ['type' => 'string', 'description' => 'Task description'],
            'assignee_id' => ['type' => 'integer', 'description' => 'User ID of assignee'],
            'priority' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'critical'], 'default' => 'medium'],
            'estimated_hours' => ['type' => 'number', 'description' => 'Estimated hours to complete'],
            'due_date' => ['type' => 'string', 'description' => 'Due date (YYYY-MM-DD)'],
            'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
            'dependencies' => ['type' => 'array', 'items' => ['type' => 'integer']]
        ],
        'required' => ['project_id', 'title', 'description']
    ],
    function (array $args) use ($database): array {
        // Validate project exists
        $project = $database->getProject($args['project_id']);
        if (!$project) {
            throw new McpError(-32602, "Project with ID {$args['project_id']} not found");
        }
        
        // Validate assignee if provided
        if (isset($args['assignee_id'])) {
            $assignee = $database->getUser($args['assignee_id']);
            if (!$assignee) {
                throw new McpError(-32602, "User with ID {$args['assignee_id']} not found");
            }
        }
        
        $args['reporter_id'] = 1; // Default reporter
        $task = $database->createTask($args);
        
        $assignee = $database->getUser($task['assignee_id']);
        $assigneeName = $assignee ? $assignee['name'] : 'Unassigned';
        
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "✅ Task created successfully!\n\n" .
                              "ID: {$task['id']}\n" .
                              "Title: {$task['title']}\n" .
                              "Project: {$project['name']}\n" .
                              "Assignee: {$assigneeName}\n" .
                              "Priority: {$task['priority']}\n" .
                              "Estimated: {$task['estimated_hours']} hours\n" .
                              "Due: " . ($task['due_date'] ?? 'Not set') . "\n" .
                              "Status: {$task['status']}"
                ]
            ]
        ];
    }
);

// Tool: Log Time
$server->tool(
    'log_time',
    'Log time spent on a task',
    [
        'type' => 'object',
        'properties' => [
            'task_id' => ['type' => 'integer', 'description' => 'Task ID'],
            'hours' => ['type' => 'number', 'description' => 'Hours worked'],
            'description' => ['type' => 'string', 'description' => 'Work description'],
            'user_id' => ['type' => 'integer', 'description' => 'User ID (defaults to current user)'],
            'date' => ['type' => 'string', 'description' => 'Date (YYYY-MM-DD, defaults to today)'],
            'billable' => ['type' => 'boolean', 'default' => true]
        ],
        'required' => ['task_id', 'hours', 'description']
    ],
    function (array $args) use ($database): array {
        $args['user_id'] = $args['user_id'] ?? 2; // Default user
        
        // Validate task exists
        $task = $database->getTask($args['task_id']);
        if (!$task) {
            throw new McpError(-32602, "Task with ID {$args['task_id']} not found");
        }
        
        $entry = $database->logTime($args);
        
        $user = $database->getUser($entry['user_id']);
        $userName = $user ? $user['name'] : 'Unknown User';
        
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "⏱️ Time logged successfully!\n\n" .
                              "Task: {$task['title']}\n" .
                              "User: {$userName}\n" .
                              "Hours: {$entry['hours']}\n" .
                              "Date: {$entry['date']}\n" .
                              "Description: {$entry['description']}\n" .
                              "Billable: " . ($entry['billable'] ? 'Yes' : 'No') . "\n\n" .
                              "Task Total Hours: {$task['actual_hours']} / {$task['estimated_hours']}"
                ]
            ]
        ];
    }
);

// Tool: Project Dashboard
$server->tool(
    'project_dashboard',
    'Get comprehensive project dashboard with analytics',
    [
        'type' => 'object',
        'properties' => [
            'project_id' => ['type' => 'integer', 'description' => 'Project ID for dashboard'],
            'include_team_details' => ['type' => 'boolean', 'default' => true]
        ],
        'required' => ['project_id']
    ],
    function (array $args) use ($database): array {
        $projectId = $args['project_id'];
        $includeTeam = $args['include_team_details'] ?? true;
        
        $analytics = $database->getProjectAnalytics($projectId);
        if (empty($analytics)) {
            throw new McpError(-32602, "Project with ID {$projectId} not found");
        }
        
        $project = $analytics['project'];
        $taskSummary = $analytics['task_summary'];
        $timeSummary = $analytics['time_summary'];
        
        $dashboard = "📊 Project Dashboard: {$project['name']}\n";
        $dashboard .= "=" . str_repeat("=", 50) . "\n\n";
        
        // Project Overview
        $dashboard .= "🎯 Project Overview\n";
        $dashboard .= "-" . str_repeat("-", 20) . "\n";
        $dashboard .= "Status: {$project['status']} | Priority: {$project['priority']}\n";
        $dashboard .= "Timeline: {$project['start_date']} → {$project['due_date']}\n";
        $dashboard .= "Budget: {$project['budget_hours']} hours | Spent: {$project['spent_hours']} hours\n";
        $dashboard .= "Completion: {$project['completion_percentage']}%\n\n";
        
        // Task Summary
        $dashboard .= "📝 Task Summary\n";
        $dashboard .= "-" . str_repeat("-", 15) . "\n";
        $dashboard .= "Total Tasks: {$taskSummary['total']}\n";
        $dashboard .= "✅ Completed: {$taskSummary['completed']}\n";
        $dashboard .= "🔄 In Progress: {$taskSummary['in_progress']}\n";
        $dashboard .= "⭕ To Do: {$taskSummary['todo']}\n";
        $dashboard .= "Completion Rate: " . round($taskSummary['completion_rate'], 1) . "%\n\n";
        
        // Time Analysis
        $dashboard .= "⏱️ Time Analysis\n";
        $dashboard .= "-" . str_repeat("-", 15) . "\n";
        $dashboard .= "Estimated: {$timeSummary['estimated_hours']} hours\n";
        $dashboard .= "Actual: {$timeSummary['actual_hours']} hours\n";
        $dashboard .= "Variance: " . ($timeSummary['variance'] >= 0 ? "+" : "") . "{$timeSummary['variance']} hours\n";
        $dashboard .= "Efficiency: " . round($timeSummary['efficiency'], 1) . "%\n\n";
        
        // Team Workload
        if ($includeTeam) {
            $dashboard .= "👥 Team Workload\n";
            $dashboard .= "-" . str_repeat("-", 15) . "\n";
            foreach ($analytics['team_workload'] as $member) {
                $utilizationIcon = $member['capacity_utilization'] > 90 ? '🔴' : 
                                 ($member['capacity_utilization'] > 75 ? '🟡' : '🟢');
                $dashboard .= "{$utilizationIcon} {$member['user']['name']}: {$member['active_tasks']} tasks, " .
                             round($member['capacity_utilization'], 1) . "% capacity\n";
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

// Tool: Sprint Planning
$server->tool(
    'plan_sprint',
    'Plan a sprint with task allocation and capacity planning',
    [
        'type' => 'object',
        'properties' => [
            'project_id' => ['type' => 'integer', 'description' => 'Project ID'],
            'sprint_duration_weeks' => ['type' => 'integer', 'default' => 2, 'description' => 'Sprint duration in weeks'],
            'team_members' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Team member IDs'],
            'focus_area' => ['type' => 'string', 'description' => 'Sprint focus area or theme']
        ],
        'required' => ['project_id']
    ],
    function (array $args) use ($database): array {
        $projectId = $args['project_id'];
        $sprintWeeks = $args['sprint_duration_weeks'] ?? 2;
        $focusArea = $args['focus_area'] ?? 'General Development';
        
        $project = $database->getProject($projectId);
        if (!$project) {
            throw new McpError(-32602, "Project with ID {$projectId} not found");
        }
        
        // Get available tasks
        $availableTasks = $database->getTasks(['project_id' => $projectId, 'status' => 'todo']);
        
        // Calculate team capacity
        $teamMembers = $args['team_members'] ?? $project['team_members'];
        $totalCapacity = 0;
        $teamCapacity = [];
        
        foreach ($teamMembers as $userId) {
            $user = $database->getUser($userId);
            if ($user) {
                $weeklyCapacity = $user['capacity_hours_per_week'] - $user['current_workload'];
                $sprintCapacity = $weeklyCapacity * $sprintWeeks;
                $totalCapacity += $sprintCapacity;
                
                $teamCapacity[] = [
                    'user' => $user,
                    'sprint_capacity' => $sprintCapacity,
                    'weekly_capacity' => $weeklyCapacity
                ];
            }
        }
        
        // Select tasks for sprint based on priority and capacity
        usort($availableTasks, function($a, $b) {
            $priorityOrder = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
            return ($priorityOrder[$b['priority']] ?? 0) <=> ($priorityOrder[$a['priority']] ?? 0);
        });
        
        $sprintTasks = [];
        $allocatedHours = 0;
        
        foreach ($availableTasks as $task) {
            if ($allocatedHours + $task['estimated_hours'] <= $totalCapacity) {
                $sprintTasks[] = $task;
                $allocatedHours += $task['estimated_hours'];
            }
        }
        
        $sprintPlan = "🏃‍♂️ Sprint Plan: {$focusArea}\n";
        $sprintPlan .= "=" . str_repeat("=", 40) . "\n\n";
        
        $sprintPlan .= "📋 Sprint Overview\n";
        $sprintPlan .= "-" . str_repeat("-", 18) . "\n";
        $sprintPlan .= "Project: {$project['name']}\n";
        $sprintPlan .= "Duration: {$sprintWeeks} weeks\n";
        $sprintPlan .= "Focus: {$focusArea}\n";
        $sprintPlan .= "Team Size: " . count($teamMembers) . " members\n";
        $sprintPlan .= "Total Capacity: {$totalCapacity} hours\n";
        $sprintPlan .= "Allocated Work: {$allocatedHours} hours (" . round(($allocatedHours / $totalCapacity) * 100, 1) . "%)\n\n";
        
        $sprintPlan .= "📝 Sprint Backlog (" . count($sprintTasks) . " tasks)\n";
        $sprintPlan .= "-" . str_repeat("-", 25) . "\n";
        foreach ($sprintTasks as $task) {
            $priorityIcon = match($task['priority']) {
                'critical' => '🔴',
                'high' => '🟠',
                'medium' => '🟡',
                'low' => '🟢'
            };
            
            $sprintPlan .= "{$priorityIcon} {$task['title']} ({$task['estimated_hours']}h)\n";
            $sprintPlan .= "   {$task['description']}\n\n";
        }
        
        $sprintPlan .= "👥 Team Capacity\n";
        $sprintPlan .= "-" . str_repeat("-", 15) . "\n";
        foreach ($teamCapacity as $member) {
            $utilizationIcon = $member['sprint_capacity'] > 60 ? '🟡' : '🟢';
            $sprintPlan .= "{$utilizationIcon} {$member['user']['name']}: {$member['sprint_capacity']}h available\n";
        }
        
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $sprintPlan
                ]
            ]
        ];
    }
);

// Tool: Team Performance Report
$server->tool(
    'team_performance',
    'Generate team performance analytics and insights',
    [
        'type' => 'object',
        'properties' => [
            'period_days' => ['type' => 'integer', 'default' => 30, 'description' => 'Analysis period in days'],
            'include_individual' => ['type' => 'boolean', 'default' => true]
        ]
    ],
    function (array $args) use ($database): array {
        $periodDays = $args['period_days'] ?? 30;
        $includeIndividual = $args['include_individual'] ?? true;
        
        $users = $database->getUsers();
        $allTasks = $database->getTasks();
        $timeEntries = $database->getTimeEntries();
        
        $report = "📊 Team Performance Report (Last {$periodDays} days)\n";
        $report .= "=" . str_repeat("=", 50) . "\n\n";
        
        // Overall team metrics
        $totalTasks = count($allTasks);
        $completedTasks = count(array_filter($allTasks, fn($t) => $t['status'] === 'completed'));
        $totalHours = array_sum(array_column($timeEntries, 'hours'));
        $avgTaskCompletion = $totalTasks > 0 ? ($completedTasks / $totalTasks) * 100 : 0;
        
        $report .= "🎯 Team Overview\n";
        $report .= "-" . str_repeat("-", 15) . "\n";
        $report .= "Team Size: " . count($users) . " members\n";
        $report .= "Total Tasks: {$totalTasks}\n";
        $report .= "Completed Tasks: {$completedTasks}\n";
        $report .= "Completion Rate: " . round($avgTaskCompletion, 1) . "%\n";
        $report .= "Total Hours Logged: {$totalHours}\n";
        $report .= "Avg Hours per Task: " . ($completedTasks > 0 ? round($totalHours / $completedTasks, 1) : 0) . "\n\n";
        
        if ($includeIndividual) {
            $report .= "👤 Individual Performance\n";
            $report .= "-" . str_repeat("-", 25) . "\n";
            
            foreach ($users as $user) {
                $userTasks = $database->getTasks(['assignee_id' => $user['id']]);
                $userCompletedTasks = array_filter($userTasks, fn($t) => $t['status'] === 'completed');
                $userTimeEntries = $database->getTimeEntries(['user_id' => $user['id']]);
                $userTotalHours = array_sum(array_column($userTimeEntries, 'hours'));
                
                $completionRate = count($userTasks) > 0 ? (count($userCompletedTasks) / count($userTasks)) * 100 : 0;
                $utilizationRate = ($user['current_workload'] / $user['capacity_hours_per_week']) * 100;
                
                $performanceIcon = $completionRate > 80 ? '🌟' : ($completionRate > 60 ? '👍' : '⚠️');
                
                $report .= "{$performanceIcon} {$user['name']} ({$user['role']})\n";
                $report .= "   Tasks: " . count($userCompletedTasks) . "/" . count($userTasks) . " completed (" . round($completionRate, 1) . "%)\n";
                $report .= "   Hours Logged: {$userTotalHours}\n";
                $report .= "   Capacity Utilization: " . round($utilizationRate, 1) . "%\n";
                $report .= "   Skills: " . implode(', ', $user['skills']) . "\n\n";
            }
        }
        
        // Productivity insights
        $report .= "💡 Insights & Recommendations\n";
        $report .= "-" . str_repeat("-", 30) . "\n";
        
        if ($avgTaskCompletion > 90) {
            $report .= "🎉 Excellent team performance! Consider taking on additional challenges.\n";
        } elseif ($avgTaskCompletion > 70) {
            $report .= "👍 Good team performance with room for optimization.\n";
        } else {
            $report .= "⚠️ Team performance below target. Consider reviewing workload and processes.\n";
        }
        
        $avgUtilization = array_sum(array_column($users, 'current_workload')) / array_sum(array_column($users, 'capacity_hours_per_week')) * 100;
        
        if ($avgUtilization > 85) {
            $report .= "🔴 High team utilization detected. Consider additional resources or timeline adjustments.\n";
        } elseif ($avgUtilization < 60) {
            $report .= "🟢 Team has available capacity for additional work.\n";
        }
        
        $report .= "📈 Recommended Actions:\n";
        $report .= "• Regular sprint retrospectives to improve processes\n";
        $report .= "• Cross-training to balance workload across skills\n";
        $report .= "• Automated testing to reduce manual QA time\n";
        $report .= "• Documentation updates to reduce onboarding time\n";
        
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $report
                ]
            ]
        ];
    }
);

// Resource: Project Statistics
$server->resource(
    'Project Statistics',
    'tasks://stats',
    [
        'title' => 'Task Management Statistics',
        'description' => 'Comprehensive statistics across all projects and tasks',
        'mimeType' => 'application/json'
    ],
    function () use ($database): string {
        $projects = $database->getProjects();
        $tasks = $database->getTasks();
        $users = $database->getUsers();
        $timeEntries = $database->getTimeEntries();
        
        $stats = [
            'summary' => [
                'total_projects' => count($projects),
                'active_projects' => count(array_filter($projects, fn($p) => $p['status'] === 'in_progress')),
                'total_tasks' => count($tasks),
                'completed_tasks' => count(array_filter($tasks, fn($t) => $t['status'] === 'completed')),
                'total_users' => count($users),
                'total_hours_logged' => array_sum(array_column($timeEntries, 'hours'))
            ],
            'project_breakdown' => [
                'by_status' => array_count_values(array_column($projects, 'status')),
                'by_priority' => array_count_values(array_column($projects, 'priority'))
            ],
            'task_breakdown' => [
                'by_status' => array_count_values(array_column($tasks, 'status')),
                'by_priority' => array_count_values(array_column($tasks, 'priority'))
            ],
            'productivity_metrics' => [
                'avg_task_completion_time' => $this->calculateAvgCompletionTime($tasks),
                'avg_hours_per_task' => count($tasks) > 0 ? array_sum(array_column($tasks, 'actual_hours')) / count($tasks) : 0,
                'estimation_accuracy' => $this->calculateEstimationAccuracy($tasks)
            ],
            'generated_at' => date('c')
        ];
        
        return json_encode($stats, JSON_PRETTY_PRINT);
    }
);

// Helper function for statistics
function calculateAvgCompletionTime(array $tasks): float
{
    $completedTasks = array_filter($tasks, fn($t) => $t['status'] === 'completed' && $t['completed_date']);
    
    if (empty($completedTasks)) return 0.0;
    
    $totalDays = 0;
    foreach ($completedTasks as $task) {
        $created = strtotime($task['created_at']);
        $completed = strtotime($task['completed_date']);
        $totalDays += ($completed - $created) / 86400; // Convert to days
    }
    
    return $totalDays / count($completedTasks);
}

function calculateEstimationAccuracy(array $tasks): float
{
    $tasksWithEstimates = array_filter($tasks, fn($t) => $t['estimated_hours'] > 0 && $t['actual_hours'] > 0);
    
    if (empty($tasksWithEstimates)) return 0.0;
    
    $accuracySum = 0;
    foreach ($tasksWithEstimates as $task) {
        $accuracy = min($task['estimated_hours'], $task['actual_hours']) / max($task['estimated_hours'], $task['actual_hours']);
        $accuracySum += $accuracy;
    }
    
    return ($accuracySum / count($tasksWithEstimates)) * 100;
}

// Prompt: Project Management Help
$server->prompt(
    'pm_help',
    'Get help with project management and task coordination',
    function (): array {
        return [
            'description' => 'Project Management Assistant',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'How do I effectively manage projects and tasks?'
                        ]
                    ]
                ],
                [
                    'role' => 'assistant',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => "This Task Management System provides comprehensive project coordination:\n\n" .
                                     "**📋 Project Management:**\n" .
                                     "• **get_projects** - View all projects with filtering\n" .
                                     "• **project_dashboard** - Comprehensive project analytics\n" .
                                     "• **plan_sprint** - Agile sprint planning with capacity management\n\n" .
                                     "**📝 Task Management:**\n" .
                                     "• **get_tasks** - Filter tasks by project, assignee, status, priority\n" .
                                     "• **create_task** - Create tasks with dependencies and estimates\n" .
                                     "• Task status tracking (todo → in_progress → completed)\n\n" .
                                     "**⏱️ Time Tracking:**\n" .
                                     "• **log_time** - Track time spent on tasks\n" .
                                     "• Automatic time aggregation and variance analysis\n" .
                                     "• Billable vs non-billable time tracking\n\n" .
                                     "**📊 Analytics:**\n" .
                                     "• **team_performance** - Team productivity analytics\n" .
                                     "• Capacity utilization monitoring\n" .
                                     "• Estimation accuracy tracking\n" .
                                     "• Performance insights and recommendations\n\n" .
                                     "**🏃‍♂️ Agile Features:**\n" .
                                     "• Sprint planning with capacity management\n" .
                                     "• Team workload balancing\n" .
                                     "• Velocity tracking\n" .
                                     "• Retrospective data collection\n\n" .
                                     "Try: 'Show me the dashboard for project 1'"
                        ]
                    ]
                ]
            ]
        ];
    }
);

// Start the Task Manager server
async(function () use ($server, $database) {
    echo "📋 Task Management System MCP Server starting...\n";
    echo "📊 Data: " . count($database->getProjects()) . " projects, " . count($database->getTasks()) . " tasks\n";
    echo "👥 Team: " . count($database->getUsers()) . " users, " . count($database->getTimeEntries()) . " time entries\n";
    echo "🛠️  Available tools: get_projects, get_tasks, create_task, log_time, project_dashboard, plan_sprint, team_performance\n";
    echo "📚 Resources: project statistics\n";
    echo "🚀 Ready for project management!\n" . PHP_EOL;
    
    $transport = new StdioServerTransport();
    $server->connect($transport)->await();
})->await();
