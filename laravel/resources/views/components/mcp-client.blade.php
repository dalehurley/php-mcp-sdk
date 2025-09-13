@props(['endpoint' => '/mcp', 'autoConnect' => true])

<div 
    x-data="mcpClient(@js(['endpoint' => $endpoint, 'autoConnect' => $autoConnect]))"
    x-init="init()"
    {{ $attributes->merge(['class' => '']) }}
>
    <!-- Connection Status -->
    <div x-show="!connected && !connecting" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-md">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-red-800">Connection Failed</h3>
                <div class="mt-2 text-sm text-red-700">
                    <p x-text="error || 'Failed to connect to MCP server'"></p>
                </div>
                <div class="mt-3">
                    <button 
                        @click="connect()"
                        class="bg-red-100 px-2 py-1 rounded-md text-sm font-medium text-red-800 hover:bg-red-200"
                    >
                        Retry Connection
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Connecting State -->
    <div x-show="connecting" class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-md">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="animate-spin h-5 w-5 text-blue-400" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-blue-800">Connecting to MCP Server</h3>
                <p class="text-sm text-blue-700">Please wait...</p>
            </div>
        </div>
    </div>

    <!-- Connected State -->
    <div x-show="connected" class="mb-4 p-3 bg-green-50 border border-green-200 rounded-md">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.236 4.53L7.53 10.25a.75.75 0 00-1.06 1.5l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-green-800">Connected to MCP Server</h3>
                <p class="text-sm text-green-700">Ready to use MCP functionality</p>
            </div>
        </div>
    </div>

    <!-- Content Slot -->
    <div x-show="connected">
        {{ $slot }}
    </div>
</div>

<script>
function mcpClient(config) {
    return {
        connected: false,
        connecting: false,
        error: null,
        endpoint: config.endpoint,
        requestId: 0,

        async init() {
            if (config.autoConnect) {
                await this.connect();
            }
        },

        async connect() {
            if (this.connecting || this.connected) return;
            
            this.connecting = true;
            this.error = null;

            try {
                const response = await fetch(`${this.endpoint}/info`);
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                this.connected = true;
            } catch (err) {
                this.error = err.message;
                this.connected = false;
            } finally {
                this.connecting = false;
            }
        },

        disconnect() {
            this.connected = false;
            this.connecting = false;
        },

        async makeRequest(method, params = {}) {
            if (!this.connected) {
                throw new Error('MCP client not connected');
            }

            const id = ++this.requestId;
            const request = {
                jsonrpc: '2.0',
                id,
                method,
                params
            };

            const response = await fetch(this.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(request)
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();

            if (data.error) {
                throw new Error(`MCP Error ${data.error.code}: ${data.error.message}`);
            }

            return data.result;
        },

        async callTool(name, args) {
            return this.makeRequest('tools/call', { name, arguments: args });
        },

        async readResource(uri) {
            return this.makeRequest('resources/read', { uri });
        },

        async getPrompt(name, args) {
            return this.makeRequest('prompts/get', { name, arguments: args });
        },

        async listTools() {
            return this.makeRequest('tools/list');
        },

        async listResources() {
            return this.makeRequest('resources/list');
        },

        async listPrompts() {
            return this.makeRequest('prompts/list');
        }
    }
}
</script>