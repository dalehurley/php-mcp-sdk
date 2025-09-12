import React, { useState, useEffect } from "react";
import { Head, usePage } from "@inertiajs/react";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Alert, AlertDescription } from "@/components/ui/alert";
import {
  Activity,
  Server,
  Users,
  Database,
  Cloud,
  Zap,
  CheckCircle,
  AlertCircle,
  Clock,
  BarChart3,
} from "lucide-react";

interface ServerInfo {
  name: string;
  version: string;
  status: string;
}

interface AvailableServer {
  id: string;
  name: string;
  description: string;
  type: string;
  status: string;
  config: {
    command: string;
    args: string[];
  };
}

interface RecentActivity {
  type: string;
  description: string;
  timestamp: string;
  status: string;
}

interface PageProps {
  serverInfo: ServerInfo;
  availableServers: AvailableServer[];
  recentActivity: RecentActivity[];
}

export default function McpDemo() {
  const { serverInfo, availableServers, recentActivity } =
    usePage<PageProps>().props;
  const [connectionStatus, setConnectionStatus] = useState<
    "disconnected" | "connecting" | "connected"
  >("disconnected");
  const [selectedServer, setSelectedServer] = useState<string | null>(null);
  const [metrics, setMetrics] = useState({
    requestsPerMinute: 0,
    averageResponseTime: 0,
    activeConnections: 0,
    errorRate: 0,
  });

  useEffect(() => {
    // Simulate real-time metrics updates
    const interval = setInterval(() => {
      setMetrics((prev) => ({
        requestsPerMinute: Math.floor(Math.random() * 50) + 10,
        averageResponseTime: Math.floor(Math.random() * 200) + 50,
        activeConnections: Math.floor(Math.random() * 5) + 1,
        errorRate: Math.random() * 0.05,
      }));
    }, 3000);

    return () => clearInterval(interval);
  }, []);

  const handleConnect = async (serverId: string) => {
    setConnectionStatus("connecting");
    setSelectedServer(serverId);

    try {
      const server = availableServers.find((s) => s.id === serverId);
      if (!server) throw new Error("Server not found");

      const response = await fetch("/mcp/connect", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-TOKEN":
            document
              .querySelector('meta[name="csrf-token"]')
              ?.getAttribute("content") || "",
        },
        body: JSON.stringify({
          server_type: server.type,
          server_config: server.config,
          client_name: "Laravel Inertia MCP Client",
        }),
      });

      const result = await response.json();

      if (result.success) {
        setConnectionStatus("connected");
      } else {
        throw new Error(result.message);
      }
    } catch (error) {
      setConnectionStatus("disconnected");
      console.error("Connection failed:", error);
    }
  };

  const handleDisconnect = async () => {
    try {
      await fetch("/mcp/disconnect", {
        method: "POST",
        headers: {
          "X-CSRF-TOKEN":
            document
              .querySelector('meta[name="csrf-token"]')
              ?.getAttribute("content") || "",
        },
      });
      setConnectionStatus("disconnected");
      setSelectedServer(null);
    } catch (error) {
      console.error("Disconnect failed:", error);
    }
  };

  const getStatusIcon = (status: string) => {
    switch (status) {
      case "connected":
      case "available":
      case "success":
        return <CheckCircle className="h-4 w-4 text-green-500" />;
      case "connecting":
        return <Clock className="h-4 w-4 text-yellow-500" />;
      case "error":
      case "failed":
        return <AlertCircle className="h-4 w-4 text-red-500" />;
      default:
        return <AlertCircle className="h-4 w-4 text-gray-500" />;
    }
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case "connected":
      case "available":
      case "running":
      case "success":
        return "bg-green-100 text-green-800";
      case "connecting":
        return "bg-yellow-100 text-yellow-800";
      case "error":
      case "failed":
        return "bg-red-100 text-red-800";
      default:
        return "bg-gray-100 text-gray-800";
    }
  };

  return (
    <>
      <Head title="MCP Demo - Laravel Integration" />

      <div className="container mx-auto py-8 px-4">
        <div className="mb-8">
          <h1 className="text-4xl font-bold text-gray-900 mb-2">
            MCP Laravel Integration Demo
          </h1>
          <p className="text-lg text-gray-600">
            Model Context Protocol integration with Laravel and Inertia.js
          </p>
        </div>

        {/* Server Status Card */}
        <Card className="mb-8">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Server className="h-5 w-5" />
              Server Status
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
              <div className="flex items-center gap-2">
                {getStatusIcon(serverInfo.status)}
                <div>
                  <p className="text-sm font-medium">{serverInfo.name}</p>
                  <p className="text-xs text-gray-500">v{serverInfo.version}</p>
                </div>
              </div>
              <div className="flex items-center gap-2">
                <Badge className={getStatusColor(serverInfo.status)}>
                  {serverInfo.status}
                </Badge>
              </div>
              <div className="flex items-center gap-2">
                <Activity className="h-4 w-4 text-blue-500" />
                <span className="text-sm">
                  {metrics.activeConnections} active connections
                </span>
              </div>
              <div className="flex items-center gap-2">
                <BarChart3 className="h-4 w-4 text-purple-500" />
                <span className="text-sm">
                  {metrics.requestsPerMinute} req/min
                </span>
              </div>
            </div>
          </CardContent>
        </Card>

        <Tabs defaultValue="servers" className="space-y-6">
          <TabsList className="grid w-full grid-cols-4">
            <TabsTrigger value="servers">Available Servers</TabsTrigger>
            <TabsTrigger value="metrics">Metrics</TabsTrigger>
            <TabsTrigger value="activity">Recent Activity</TabsTrigger>
            <TabsTrigger value="client">Client Interface</TabsTrigger>
          </TabsList>

          {/* Available Servers Tab */}
          <TabsContent value="servers" className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {availableServers.map((server) => (
                <Card
                  key={server.id}
                  className="hover:shadow-lg transition-shadow"
                >
                  <CardHeader>
                    <CardTitle className="flex items-center justify-between">
                      <span className="flex items-center gap-2">
                        {server.type === "stdio" && (
                          <Database className="h-4 w-4" />
                        )}
                        {server.type === "http" && (
                          <Cloud className="h-4 w-4" />
                        )}
                        {server.type === "websocket" && (
                          <Zap className="h-4 w-4" />
                        )}
                        {server.name}
                      </span>
                      <Badge className={getStatusColor(server.status)}>
                        {server.status}
                      </Badge>
                    </CardTitle>
                    <CardDescription>{server.description}</CardDescription>
                  </CardHeader>
                  <CardContent>
                    <div className="space-y-2">
                      <p className="text-sm text-gray-600">
                        <strong>Type:</strong> {server.type}
                      </p>
                      <div className="flex gap-2">
                        {connectionStatus === "connected" &&
                        selectedServer === server.id ? (
                          <Button
                            variant="destructive"
                            size="sm"
                            onClick={handleDisconnect}
                          >
                            Disconnect
                          </Button>
                        ) : (
                          <Button
                            variant="default"
                            size="sm"
                            disabled={connectionStatus === "connecting"}
                            onClick={() => handleConnect(server.id)}
                          >
                            {connectionStatus === "connecting" &&
                            selectedServer === server.id
                              ? "Connecting..."
                              : "Connect"}
                          </Button>
                        )}
                        <Button variant="outline" size="sm">
                          View Details
                        </Button>
                      </div>
                    </div>
                  </CardContent>
                </Card>
              ))}
            </div>

            {connectionStatus === "connected" && (
              <Alert className="mt-4">
                <CheckCircle className="h-4 w-4" />
                <AlertDescription>
                  Successfully connected to{" "}
                  {availableServers.find((s) => s.id === selectedServer)?.name}.
                  You can now use the client interface to interact with the
                  server.
                </AlertDescription>
              </Alert>
            )}
          </TabsContent>

          {/* Metrics Tab */}
          <TabsContent value="metrics" className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
              <Card>
                <CardHeader className="pb-2">
                  <CardTitle className="text-sm font-medium">
                    Requests/Minute
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="text-2xl font-bold">
                    {metrics.requestsPerMinute}
                  </div>
                  <p className="text-xs text-gray-500">+12% from last hour</p>
                </CardContent>
              </Card>

              <Card>
                <CardHeader className="pb-2">
                  <CardTitle className="text-sm font-medium">
                    Avg Response Time
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="text-2xl font-bold">
                    {metrics.averageResponseTime}ms
                  </div>
                  <p className="text-xs text-gray-500">-5% from last hour</p>
                </CardContent>
              </Card>

              <Card>
                <CardHeader className="pb-2">
                  <CardTitle className="text-sm font-medium">
                    Active Connections
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="text-2xl font-bold">
                    {metrics.activeConnections}
                  </div>
                  <p className="text-xs text-gray-500">Real-time</p>
                </CardContent>
              </Card>

              <Card>
                <CardHeader className="pb-2">
                  <CardTitle className="text-sm font-medium">
                    Error Rate
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="text-2xl font-bold">
                    {(metrics.errorRate * 100).toFixed(2)}%
                  </div>
                  <p className="text-xs text-gray-500">Last 24 hours</p>
                </CardContent>
              </Card>
            </div>

            <Card>
              <CardHeader>
                <CardTitle>Performance Overview</CardTitle>
                <CardDescription>
                  Real-time metrics and performance indicators
                </CardDescription>
              </CardHeader>
              <CardContent>
                <div className="space-y-4">
                  <div>
                    <div className="flex justify-between text-sm mb-1">
                      <span>CPU Usage</span>
                      <span>45%</span>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-2">
                      <div
                        className="bg-blue-600 h-2 rounded-full"
                        style={{ width: "45%" }}
                      ></div>
                    </div>
                  </div>

                  <div>
                    <div className="flex justify-between text-sm mb-1">
                      <span>Memory Usage</span>
                      <span>67%</span>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-2">
                      <div
                        className="bg-green-600 h-2 rounded-full"
                        style={{ width: "67%" }}
                      ></div>
                    </div>
                  </div>

                  <div>
                    <div className="flex justify-between text-sm mb-1">
                      <span>Network I/O</span>
                      <span>23%</span>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-2">
                      <div
                        className="bg-purple-600 h-2 rounded-full"
                        style={{ width: "23%" }}
                      ></div>
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>
          </TabsContent>

          {/* Recent Activity Tab */}
          <TabsContent value="activity" className="space-y-4">
            <Card>
              <CardHeader>
                <CardTitle>Recent Activity</CardTitle>
                <CardDescription>
                  Latest MCP server operations and events
                </CardDescription>
              </CardHeader>
              <CardContent>
                <div className="space-y-4">
                  {recentActivity.map((activity, index) => (
                    <div
                      key={index}
                      className="flex items-center gap-4 p-3 border rounded-lg"
                    >
                      {getStatusIcon(activity.status)}
                      <div className="flex-1">
                        <p className="text-sm font-medium">
                          {activity.description}
                        </p>
                        <p className="text-xs text-gray-500">
                          {new Date(activity.timestamp).toLocaleString()}
                        </p>
                      </div>
                      <Badge className={getStatusColor(activity.status)}>
                        {activity.status}
                      </Badge>
                    </div>
                  ))}
                </div>
              </CardContent>
            </Card>
          </TabsContent>

          {/* Client Interface Tab */}
          <TabsContent value="client" className="space-y-4">
            <Card>
              <CardHeader>
                <CardTitle>MCP Client Interface</CardTitle>
                <CardDescription>
                  Interactive interface for testing MCP operations
                </CardDescription>
              </CardHeader>
              <CardContent>
                {connectionStatus === "connected" ? (
                  <div className="space-y-4">
                    <Alert>
                      <CheckCircle className="h-4 w-4" />
                      <AlertDescription>
                        Connected to{" "}
                        {
                          availableServers.find((s) => s.id === selectedServer)
                            ?.name
                        }
                        . Use the buttons below to test different MCP
                        operations.
                      </AlertDescription>
                    </Alert>

                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                      <Button className="h-20 flex-col gap-2">
                        <Database className="h-6 w-6" />
                        List Tools
                      </Button>
                      <Button className="h-20 flex-col gap-2" variant="outline">
                        <Users className="h-6 w-6" />
                        List Resources
                      </Button>
                      <Button className="h-20 flex-col gap-2" variant="outline">
                        <Zap className="h-6 w-6" />
                        Call Tool
                      </Button>
                    </div>
                  </div>
                ) : (
                  <Alert>
                    <AlertCircle className="h-4 w-4" />
                    <AlertDescription>
                      Connect to an MCP server first to use the client
                      interface.
                    </AlertDescription>
                  </Alert>
                )}
              </CardContent>
            </Card>
          </TabsContent>
        </Tabs>
      </div>
    </>
  );
}
