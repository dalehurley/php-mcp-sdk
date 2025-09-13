<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MCP Dashboard</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>
<body class="bg-gray-100">
    <div x-data="mcpDashboard(@json($serverInfo), @json($config))" class="min-h-screen">
        <!-- Header -->
        <div class="bg-white shadow">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <h1 class="text-xl font-semibold text-gray-900">MCP Dashboard</h1>
                        <span class="ml-4 text-sm text-gray-500" x-text="serverInfo.name + ' v' + serverInfo.version"></span>
                    </div>
                    <div class="flex items-center">
                        <span 
                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                            :class="connected ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                            x-text="connected ? 'Connected' : 'Disconnected'"
                        ></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <!-- Error Display -->
            <div x-show="error" class="mb-4 bg-red-50 border border-red-200 rounded-md p-4">
                <div class="text-sm text-red-700" x-text="error"></div>
            </div>

            <!-- Loading State -->
            <div x-show="loading" class="text-center py-8">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                <p class="mt-2 text-sm text-gray-500">Loading dashboard...</p>
            </div>

            <!-- Stats Grid -->
            <div x-show="!loading && stats" class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-8">
                <!-- Server Status -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div 
                                    class="w-8 h-8 rounded-full flex items-center justify-center"
                                    :class="stats?.server?.status === 'connected' ? 'bg-green-100' : 'bg-red-100'"
                                >
                                    <div 
                                        class="w-3 h-3 rounded-full"
                                        :class="stats?.server?.status === 'connected' ? 'bg-green-600' : 'bg-red-600'"
                                    ></div>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dt class="text-sm font-medium text-gray-500 truncate">Server Status</dt>
                                <dd class="text-lg font-medium text-gray-900 capitalize" x-text="stats?.server?.status || 'Unknown'"></dd>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-5 py-3">
                        <div class="text-sm text-gray-500" x-text="'Uptime: ' + formatUptime(stats?.server?.uptime || 0)"></div>
                    </div>
                </div>

                <!-- Tools -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dt class="text-sm font-medium text-gray-500 truncate">Tools</dt>
                                <dd class="text-lg font-medium text-gray-900" x-text="stats?.tools?.count || 0"></dd>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-5 py-3">
                        <div class="text-sm text-gray-500" x-text="(stats?.tools?.calls_today || 0) + ' calls today'"></div>
                    </div>
                </div>

                <!-- Resources -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2H4zm2 6a2 2 0 114 0 2 2 0 01-4 0zm8-2a2 2 0 11-4 0 2 2 0 014 0z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dt class="text-sm font-medium text-gray-500 truncate">Resources</dt>
                                <dd class="text-lg font-medium text-gray-900" x-text="stats?.resources?.count || 0"></dd>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-5 py-3">
                        <div class="text-sm text-gray-500" x-text="(stats?.resources?.reads_today || 0) + ' reads today'"></div>
                    </div>
                </div>

                <!-- Memory -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dt class="text-sm font-medium text-gray-500 truncate">Memory</dt>
                                <dd class="text-lg font-medium text-gray-900" x-text="formatMemory(stats?.server?.memory_usage || 0)"></dd>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-5 py-3">
                        <div class="text-sm text-gray-500" x-text="'Peak: ' + formatMemory(stats?.server?.memory_peak || 0)"></div>
                    </div>
                </div>
            </div>

            <!-- Configuration Panel -->
            <div x-show="!loading" class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Configuration</h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div class="flex items-center">
                            <div 
                                class="flex-shrink-0 w-2 h-2 rounded-full"
                                :class="config.auth_enabled ? 'bg-green-400' : 'bg-gray-300'"
                            ></div>
                            <span class="ml-3 text-sm text-gray-900">
                                Authentication <span x-text="config.auth_enabled ? 'Enabled' : 'Disabled'"></span>
                            </span>
                        </div>
                        <div class="flex items-center">
                            <div 
                                class="flex-shrink-0 w-2 h-2 rounded-full"
                                :class="config.cache_enabled ? 'bg-green-400' : 'bg-gray-300'"
                            ></div>
                            <span class="ml-3 text-sm text-gray-900">
                                Caching <span x-text="config.cache_enabled ? 'Enabled' : 'Disabled'"></span>
                            </span>
                        </div>
                        <div class="flex items-center">
                            <div 
                                class="flex-shrink-0 w-2 h-2 rounded-full"
                                :class="config.queue_enabled ? 'bg-green-400' : 'bg-gray-300'"
                            ></div>
                            <span class="ml-3 text-sm text-gray-900">
                                Queues <span x-text="config.queue_enabled ? 'Enabled' : 'Disabled'"></span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function mcpDashboard(serverInfo, config) {
            return {
                serverInfo,
                config,
                connected: false,
                loading: true,
                error: null,
                stats: null,

                async init() {
                    await this.checkConnection();
                    await this.fetchStats();
                    
                    // Update stats every 5 seconds
                    setInterval(() => this.fetchStats(), 5000);
                },

                async checkConnection() {
                    try {
                        const response = await fetch('/mcp/info');
                        this.connected = response.ok;
                    } catch (err) {
                        this.connected = false;
                    }
                },

                async fetchStats() {
                    try {
                        const response = await fetch('/mcp/dashboard/api/stats');
                        if (!response.ok) throw new Error('Failed to fetch stats');
                        
                        this.stats = await response.json();
                        this.error = null;
                    } catch (err) {
                        this.error = err.message;
                    } finally {
                        this.loading = false;
                    }
                },

                formatMemory(bytes) {
                    if (!bytes) return 'N/A';
                    const mb = bytes / 1024 / 1024;
                    return mb.toFixed(1) + ' MB';
                },

                formatUptime(seconds) {
                    if (!seconds) return 'N/A';
                    const hours = Math.floor(seconds / 3600);
                    const minutes = Math.floor((seconds % 3600) / 60);
                    return `${hours}h ${minutes}m`;
                }
            }
        }
    </script>
</body>
</html>