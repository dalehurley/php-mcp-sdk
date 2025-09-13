<?php

declare(strict_types=1);

namespace MCP\Laravel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class McpInstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mcp:install 
        {--inertia : Install Inertia.js components}
        {--vue : Install Vue.js components instead of React}
        {--auth : Install authentication scaffolding}
        {--force : Overwrite existing files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install MCP Laravel package scaffolding';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Installing MCP Laravel package...');

        // Publish configuration
        $this->publishConfiguration();

        // Create directory structure
        $this->createDirectories();

        // Create example files
        $this->createExampleFiles();

        // Install UI components if requested
        if ($this->option('inertia')) {
            $this->installInertiaComponents();
        }

        // Install authentication if requested
        if ($this->option('auth')) {
            $this->installAuthScaffolding();
        }

        $this->info('MCP Laravel package installed successfully!');
        $this->displayNextSteps();

        return self::SUCCESS;
    }

    /**
     * Publish configuration file.
     */
    protected function publishConfiguration(): void
    {
        $this->call('vendor:publish', [
            '--tag' => 'mcp-config',
            '--force' => $this->option('force'),
        ]);

        $this->info('✓ Configuration published');
    }

    /**
     * Create necessary directories.
     */
    protected function createDirectories(): void
    {
        $directories = [
            app_path('Mcp'),
            app_path('Mcp/Tools'),
            app_path('Mcp/Resources'),
            app_path('Mcp/Prompts'),
            storage_path('mcp'),
            storage_path('mcp/sessions'),
            storage_path('mcp/logs'),
        ];

        foreach ($directories as $directory) {
            File::ensureDirectoryExists($directory);
        }

        $this->info('✓ Directory structure created');
    }

    /**
     * Create example files.
     */
    protected function createExampleFiles(): void
    {
        $this->createExampleTool();
        $this->createExampleResource();
        $this->createExamplePrompt();
        $this->createExampleController();

        $this->info('✓ Example files created');
    }

    /**
     * Create example tool.
     */
    protected function createExampleTool(): void
    {
        $content = <<<PHP
<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use MCP\Laravel\Tools\BaseTool;

class CalculatorTool extends BaseTool
{
    public function name(): string
    {
        return 'calculator';
    }

    public function title(): string
    {
        return 'Calculator';
    }

    public function description(): string
    {
        return 'Perform basic mathematical calculations';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'operation' => [
                    'type' => 'string',
                    'enum' => ['add', 'subtract', 'multiply', 'divide'],
                    'description' => 'The mathematical operation to perform',
                ],
                'a' => [
                    'type' => 'number',
                    'description' => 'First number',
                ],
                'b' => [
                    'type' => 'number',
                    'description' => 'Second number',
                ],
            ],
            'required' => ['operation', 'a', 'b'],
        ];
    }

    public function handle(array \$params): array
    {
        \$operation = \$params['operation'];
        \$a = \$params['a'];
        \$b = \$params['b'];

        \$result = match (\$operation) {
            'add' => \$a + \$b,
            'subtract' => \$a - \$b,
            'multiply' => \$a * \$b,
            'divide' => \$b !== 0 ? \$a / \$b : throw new \InvalidArgumentException('Division by zero'),
            default => throw new \InvalidArgumentException("Unknown operation: \$operation"),
        };

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "Result: \$result",
                ],
            ],
        ];
    }
}
PHP;

        File::put(app_path('Mcp/Tools/CalculatorTool.php'), $content);
    }

    /**
     * Create example resource.
     */
    protected function createExampleResource(): void
    {
        $content = <<<PHP
<?php

declare(strict_types=1);

namespace App\Mcp\Resources;

use MCP\Laravel\Resources\BaseResource;

class AppConfigResource extends BaseResource
{
    public function uri(): string
    {
        return 'config://app';
    }

    public function name(): string
    {
        return 'App Configuration';
    }

    public function description(): string
    {
        return 'Laravel application configuration information';
    }

    public function mimeType(): ?string
    {
        return 'application/json';
    }

    public function read(string \$uri): array
    {
        return [
            'contents' => [
                [
                    'uri' => \$uri,
                    'mimeType' => \$this->mimeType(),
                    'text' => json_encode([
                        'app_name' => config('app.name'),
                        'app_env' => config('app.env'),
                        'app_debug' => config('app.debug'),
                        'app_url' => config('app.url'),
                        'laravel_version' => app()->version(),
                        'php_version' => PHP_VERSION,
                        'timezone' => config('app.timezone'),
                    ], JSON_PRETTY_PRINT),
                ],
            ],
        ];
    }
}
PHP;

        File::put(app_path('Mcp/Resources/AppConfigResource.php'), $content);
    }

    /**
     * Create example prompt.
     */
    protected function createExamplePrompt(): void
    {
        $content = <<<PHP
<?php

declare(strict_types=1);

namespace App\Mcp\Prompts;

use MCP\Laravel\Prompts\BasePrompt;

class CodeReviewPrompt extends BasePrompt
{
    public function name(): string
    {
        return 'code-review';
    }

    public function description(): string
    {
        return 'Generate a code review prompt for Laravel code';
    }

    public function arguments(): array
    {
        return [
            [
                'name' => 'code',
                'description' => 'The code to review',
                'required' => true,
            ],
            [
                'name' => 'focus',
                'description' => 'Areas to focus on (security, performance, style, etc.)',
                'required' => false,
            ],
        ];
    }

    public function handle(array \$params): array
    {
        \$code = \$params['code'];
        \$focus = \$params['focus'] ?? 'general code quality';

        \$prompt = "Please review this Laravel code focusing on \$focus:\\n\\n";
        \$prompt .= "```php\\n" . \$code . "\\n```\\n\\n";
        \$prompt .= "Consider:\\n";
        \$prompt .= "- Laravel best practices\\n";
        \$prompt .= "- Security implications\\n";
        \$prompt .= "- Performance considerations\\n";
        \$prompt .= "- Code readability and maintainability\\n";
        \$prompt .= "- Proper use of Laravel features\\n";

        return [
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        'type' => 'text',
                        'text' => \$prompt,
                    ],
                ],
            ],
        ];
    }
}
PHP;

        File::put(app_path('Mcp/Prompts/CodeReviewPrompt.php'), $content);
    }

    /**
     * Create example controller.
     */
    protected function createExampleController(): void
    {
        $content = <<<PHP
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use MCP\Server\McpServer;
use MCP\Client\Client;

class McpExampleController extends Controller
{
    public function __construct(
        private McpServer \$server,
        private Client \$client
    ) {}

    /**
     * Display MCP server information.
     */
    public function serverInfo()
    {
        // Example of accessing server information
        return response()->json([
            'server' => [
                'name' => config('mcp.server.name'),
                'version' => config('mcp.server.version'),
                'capabilities' => config('mcp.server.capabilities'),
            ],
        ]);
    }

    /**
     * List available tools.
     */
    public function listTools()
    {
        // This would typically call \$server->listTools() when that method is available
        return response()->json([
            'tools' => [
                // Mock data for now
                ['name' => 'calculator', 'description' => 'Basic math operations'],
            ],
        ]);
    }

    /**
     * Example of calling a tool.
     */
    public function callTool(Request \$request)
    {
        \$toolName = \$request->input('tool');
        \$params = \$request->input('params', []);

        // This would typically use the server to call the tool
        return response()->json([
            'result' => "Called tool: \$toolName with params",
            'params' => \$params,
        ]);
    }
}
PHP;

        File::put(app_path('Http/Controllers/McpExampleController.php'), $content);
    }

    /**
     * Install Inertia.js components.
     */
    protected function installInertiaComponents(): void
    {
        $this->call('vendor:publish', [
            '--tag' => 'mcp-components',
            '--force' => $this->option('force'),
        ]);

        $this->call('vendor:publish', [
            '--tag' => 'mcp-views',
            '--force' => $this->option('force'),
        ]);

        $this->info('✓ UI components published');

        // Create example Inertia page
        $this->createInertiaExamplePage();
    }

    /**
     * Create example Inertia page.
     */
    protected function createInertiaExamplePage(): void
    {
        $jsContent = <<<JSX
import React, { useState, useEffect } from 'react';
import { Head } from '@inertiajs/react';

export default function McpDashboard() {
    const [serverInfo, setServerInfo] = useState(null);
    const [tools, setTools] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        Promise.all([
            fetch('/mcp/info').then(r => r.json()),
            fetch('/mcp/tools').then(r => r.json()),
        ]).then(([info, toolsData]) => {
            setServerInfo(info.server);
            setTools(toolsData.tools);
            setLoading(false);
        }).catch(console.error);
    }, []);

    if (loading) {
        return <div className="p-4">Loading MCP Dashboard...</div>;
    }

    return (
        <>
            <Head title="MCP Dashboard" />
            <div className="p-6">
                <h1 className="text-2xl font-bold mb-6">MCP Dashboard</h1>
                
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div className="bg-white rounded-lg shadow p-6">
                        <h2 className="text-xl font-semibold mb-4">Server Information</h2>
                        {serverInfo && (
                            <dl className="space-y-2">
                                <div><dt className="font-medium">Name:</dt><dd>{serverInfo.name}</dd></div>
                                <div><dt className="font-medium">Version:</dt><dd>{serverInfo.version}</dd></div>
                            </dl>
                        )}
                    </div>

                    <div className="bg-white rounded-lg shadow p-6">
                        <h2 className="text-xl font-semibold mb-4">Available Tools</h2>
                        <ul className="space-y-2">
                            {tools.map((tool, index) => (
                                <li key={index} className="border-l-4 border-blue-500 pl-4">
                                    <div className="font-medium">{tool.name}</div>
                                    <div className="text-sm text-gray-600">{tool.description}</div>
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>
            </div>
        </>
    );
}
JSX;

        File::ensureDirectoryExists(resource_path('js/Pages/Mcp'));
        File::put(resource_path('js/Pages/Mcp/Dashboard.jsx'), $jsContent);

        $this->info('✓ Example Inertia page created');
    }

    /**
     * Install authentication scaffolding.
     */
    protected function installAuthScaffolding(): void
    {
        // Create migration for OAuth clients
        $this->createOAuthMigration();

        // Create OAuth models
        $this->createOAuthModels();

        $this->info('✓ Authentication scaffolding installed');
    }

    /**
     * Create OAuth migration.
     */
    protected function createOAuthMigration(): void
    {
        $timestamp = date('Y_m_d_His');
        $filename = database_path("migrations/{$timestamp}_create_mcp_oauth_tables.php");

        $content = <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_oauth_clients', function (Blueprint \$table) {
            \$table->id();
            \$table->string('client_id')->unique();
            \$table->string('client_secret')->nullable();
            \$table->string('name');
            \$table->text('redirect_uris');
            \$table->boolean('confidential')->default(false);
            \$table->timestamps();
        });

        Schema::create('mcp_oauth_access_tokens', function (Blueprint \$table) {
            \$table->id();
            \$table->string('token')->unique();
            \$table->unsignedBigInteger('client_id');
            \$table->unsignedBigInteger('user_id')->nullable();
            \$table->text('scopes')->nullable();
            \$table->timestamp('expires_at');
            \$table->timestamps();
            
            \$table->foreign('client_id')->references('id')->on('mcp_oauth_clients')->onDelete('cascade');
        });

        Schema::create('mcp_oauth_refresh_tokens', function (Blueprint \$table) {
            \$table->id();
            \$table->string('token')->unique();
            \$table->unsignedBigInteger('access_token_id');
            \$table->timestamp('expires_at');
            \$table->timestamps();
            
            \$table->foreign('access_token_id')->references('id')->on('mcp_oauth_access_tokens')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_oauth_refresh_tokens');
        Schema::dropIfExists('mcp_oauth_access_tokens');
        Schema::dropIfExists('mcp_oauth_clients');
    }
};
PHP;

        File::put($filename, $content);
        $this->info("✓ OAuth migration created: {$filename}");
    }

    /**
     * Create OAuth models.
     */
    protected function createOAuthModels(): void
    {
        // Create OAuthClient model
        $clientModel = <<<PHP
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class McpOAuthClient extends Model
{
    protected \$table = 'mcp_oauth_clients';

    protected \$fillable = [
        'client_id',
        'client_secret',
        'name',
        'redirect_uris',
        'confidential',
    ];

    protected \$casts = [
        'redirect_uris' => 'array',
        'confidential' => 'boolean',
    ];

    public function accessTokens(): HasMany
    {
        return \$this->hasMany(McpOAuthAccessToken::class, 'client_id');
    }
}
PHP;

        File::put(app_path('Models/McpOAuthClient.php'), $clientModel);

        // Create AccessToken model
        $tokenModel = <<<PHP
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class McpOAuthAccessToken extends Model
{
    protected \$table = 'mcp_oauth_access_tokens';

    protected \$fillable = [
        'token',
        'client_id',
        'user_id',
        'scopes',
        'expires_at',
    ];

    protected \$casts = [
        'scopes' => 'array',
        'expires_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return \$this->belongsTo(McpOAuthClient::class, 'client_id');
    }

    public function refreshToken(): HasOne
    {
        return \$this->hasOne(McpOAuthRefreshToken::class, 'access_token_id');
    }
}
PHP;

        File::put(app_path('Models/McpOAuthAccessToken.php'), $tokenModel);
    }

    /**
     * Display next steps.
     */
    protected function displayNextSteps(): void
    {
        $this->info('');
        $this->info('Next steps:');
        $this->line('1. Review the configuration in config/mcp.php');
        $this->line('2. Create your MCP tools in app/Mcp/Tools/');
        $this->line('3. Create your MCP resources in app/Mcp/Resources/');
        $this->line('4. Start the MCP server: php artisan mcp:server');
        
        if ($this->option('auth')) {
            $this->line('5. Run migrations: php artisan migrate');
        }
        
        if ($this->option('inertia')) {
            $this->line('6. Build frontend assets: npm run dev');
            $this->line('7. Visit /mcp/dashboard for the UI');
        }
        
        $this->info('');
        $this->info('For more information, visit the documentation.');
    }
}