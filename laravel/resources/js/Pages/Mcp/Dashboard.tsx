import React, { useState, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import { useMcpClient } from '../../Components/McpClient';

interface ServerInfo {
  name: string;
  version: string;
  capabilities: any;
}

interface Config {
  auth_enabled: boolean;
  cache_enabled: boolean;
  queue_enabled: boolean;
}

interface Props {
  serverInfo: ServerInfo;
  config: Config;
}

interface Stats {
  server: {
    status: string;
    uptime: number;
    memory_usage: number;
    memory_peak: number;
  };
  tools: {
    count: number;
    calls_today: number;
  };
  resources: {
    count: number;
    reads_today: number;
  };
  prompts: {
    count: number;
    generations_today: number;
  };
  cache?: {
    hit_rate: number;
    size: number;
  };
}

export default function Dashboard({ serverInfo, config }: Props) {
  const [stats, setStats] = useState<Stats | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  
  const client = useMcpClient({
    onError: (err) => setError(err.message),
  });

  useEffect(() => {
    const fetchStats = async () => {
      try {
        const response = await fetch('/mcp/dashboard/api/stats');
        if (!response.ok) throw new Error('Failed to fetch stats');
        const data = await response.json();
        setStats(data);
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Failed to load stats');
      } finally {
        setLoading(false);
      }
    };

    fetchStats();
    const interval = setInterval(fetchStats, 5000); // Update every 5 seconds

    return () => clearInterval(interval);
  }, []);

  const formatMemory = (bytes: number) => {
    const mb = bytes / 1024 / 1024;
    return `${mb.toFixed(1)} MB`;
  };

  const formatUptime = (seconds: number) => {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    return `${hours}h ${minutes}m`;
  };

  return (
    <>
      <Head title="MCP Dashboard" />
      
      <div className="min-h-screen bg-gray-100">
        <div className="py-6">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            {/* Header */}
            <div className="md:flex md:items-center md:justify-between">
              <div className="flex-1 min-w-0">
                <h1 className="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                  MCP Dashboard
                </h1>
                <p className="mt-1 text-sm text-gray-500">
                  {serverInfo.name} v{serverInfo.version}
                </p>
              </div>
              <div className="mt-4 flex md:mt-0 md:ml-4">
                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                  client.connected ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                }`}>
                  {client.connected ? 'Connected' : 'Disconnected'}
                </span>
              </div>
            </div>

            {/* Error Display */}
            {error && (
              <div className="mt-4 bg-red-50 border border-red-200 rounded-md p-4">
                <div className="text-sm text-red-700">{error}</div>
              </div>
            )}

            {/* Stats Grid */}
            {loading ? (
              <div className="mt-6 text-center">
                <div className="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                <p className="mt-2 text-sm text-gray-500">Loading dashboard...</p>
              </div>
            ) : (
              <div className="mt-6">
                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                  {/* Server Status */}
                  <div className="bg-white overflow-hidden shadow rounded-lg">
                    <div className="p-5">
                      <div className="flex items-center">
                        <div className="flex-shrink-0">
                          <div className={`w-8 h-8 rounded-full flex items-center justify-center ${
                            stats?.server.status === 'connected' ? 'bg-green-100' : 'bg-red-100'
                          }`}>
                            <div className={`w-3 h-3 rounded-full ${
                              stats?.server.status === 'connected' ? 'bg-green-600' : 'bg-red-600'
                            }`}></div>
                          </div>
                        </div>
                        <div className="ml-5 w-0 flex-1">
                          <dl>
                            <dt className="text-sm font-medium text-gray-500 truncate">Server Status</dt>
                            <dd className="text-lg font-medium text-gray-900 capitalize">
                              {stats?.server.status || 'Unknown'}
                            </dd>
                          </dl>
                        </div>
                      </div>
                    </div>
                    <div className="bg-gray-50 px-5 py-3">
                      <div className="text-sm text-gray-500">
                        Uptime: {stats?.server.uptime ? formatUptime(stats.server.uptime) : 'N/A'}
                      </div>
                    </div>
                  </div>

                  {/* Tools */}
                  <div className="bg-white overflow-hidden shadow rounded-lg">
                    <div className="p-5">
                      <div className="flex items-center">
                        <div className="flex-shrink-0">
                          <div className="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                            <svg className="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                              <path fillRule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clipRule="evenodd" />
                            </svg>
                          </div>
                        </div>
                        <div className="ml-5 w-0 flex-1">
                          <dl>
                            <dt className="text-sm font-medium text-gray-500 truncate">Tools</dt>
                            <dd className="text-lg font-medium text-gray-900">
                              {stats?.tools.count || 0}
                            </dd>
                          </dl>
                        </div>
                      </div>
                    </div>
                    <div className="bg-gray-50 px-5 py-3">
                      <div className="text-sm text-gray-500">
                        {stats?.tools.calls_today || 0} calls today
                      </div>
                    </div>
                  </div>

                  {/* Resources */}
                  <div className="bg-white overflow-hidden shadow rounded-lg">
                    <div className="p-5">
                      <div className="flex items-center">
                        <div className="flex-shrink-0">
                          <div className="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                            <svg className="w-5 h-5 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                              <path fillRule="evenodd" d="M4 4a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2H4zm2 6a2 2 0 114 0 2 2 0 01-4 0zm8-2a2 2 0 11-4 0 2 2 0 014 0z" clipRule="evenodd" />
                            </svg>
                          </div>
                        </div>
                        <div className="ml-5 w-0 flex-1">
                          <dl>
                            <dt className="text-sm font-medium text-gray-500 truncate">Resources</dt>
                            <dd className="text-lg font-medium text-gray-900">
                              {stats?.resources.count || 0}
                            </dd>
                          </dl>
                        </div>
                      </div>
                    </div>
                    <div className="bg-gray-50 px-5 py-3">
                      <div className="text-sm text-gray-500">
                        {stats?.resources.reads_today || 0} reads today
                      </div>
                    </div>
                  </div>

                  {/* Memory Usage */}
                  <div className="bg-white overflow-hidden shadow rounded-lg">
                    <div className="p-5">
                      <div className="flex items-center">
                        <div className="flex-shrink-0">
                          <div className="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                            <svg className="w-5 h-5 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                              <path fillRule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z" clipRule="evenodd" />
                            </svg>
                          </div>
                        </div>
                        <div className="ml-5 w-0 flex-1">
                          <dl>
                            <dt className="text-sm font-medium text-gray-500 truncate">Memory Usage</dt>
                            <dd className="text-lg font-medium text-gray-900">
                              {stats?.server.memory_usage ? formatMemory(stats.server.memory_usage) : 'N/A'}
                            </dd>
                          </dl>
                        </div>
                      </div>
                    </div>
                    <div className="bg-gray-50 px-5 py-3">
                      <div className="text-sm text-gray-500">
                        Peak: {stats?.server.memory_peak ? formatMemory(stats.server.memory_peak) : 'N/A'}
                      </div>
                    </div>
                  </div>
                </div>

                {/* Configuration */}
                <div className="mt-8">
                  <div className="bg-white shadow rounded-lg">
                    <div className="px-4 py-5 sm:p-6">
                      <h3 className="text-lg leading-6 font-medium text-gray-900 mb-4">
                        Configuration
                      </h3>
                      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div className="flex items-center">
                          <div className={`flex-shrink-0 w-2 h-2 rounded-full ${
                            config.auth_enabled ? 'bg-green-400' : 'bg-gray-300'
                          }`}></div>
                          <span className="ml-3 text-sm text-gray-900">
                            Authentication {config.auth_enabled ? 'Enabled' : 'Disabled'}
                          </span>
                        </div>
                        <div className="flex items-center">
                          <div className={`flex-shrink-0 w-2 h-2 rounded-full ${
                            config.cache_enabled ? 'bg-green-400' : 'bg-gray-300'
                          }`}></div>
                          <span className="ml-3 text-sm text-gray-900">
                            Caching {config.cache_enabled ? 'Enabled' : 'Disabled'}
                          </span>
                        </div>
                        <div className="flex items-center">
                          <div className={`flex-shrink-0 w-2 h-2 rounded-full ${
                            config.queue_enabled ? 'bg-green-400' : 'bg-gray-300'
                          }`}></div>
                          <span className="ml-3 text-sm text-gray-900">
                            Queues {config.queue_enabled ? 'Enabled' : 'Disabled'}
                          </span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                {/* Cache Stats */}
                {config.cache_enabled && stats?.cache && (
                  <div className="mt-8">
                    <div className="bg-white shadow rounded-lg">
                      <div className="px-4 py-5 sm:p-6">
                        <h3 className="text-lg leading-6 font-medium text-gray-900 mb-4">
                          Cache Performance
                        </h3>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                          <div>
                            <div className="text-sm font-medium text-gray-500">Hit Rate</div>
                            <div className="mt-1 text-2xl font-bold text-gray-900">
                              {(stats.cache.hit_rate * 100).toFixed(1)}%
                            </div>
                          </div>
                          <div>
                            <div className="text-sm font-medium text-gray-500">Cache Size</div>
                            <div className="mt-1 text-2xl font-bold text-gray-900">
                              {formatMemory(stats.cache.size)}
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                )}
              </div>
            )}
          </div>
        </div>
      </div>
    </>
  );
}