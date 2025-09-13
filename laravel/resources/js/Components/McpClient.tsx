import React, { useState, useEffect, useRef, useCallback } from 'react';

interface McpClientOptions {
  endpoint?: string;
  onConnect?: (client: McpClientInstance) => void;
  onError?: (error: Error) => void;
  onDisconnect?: () => void;
}

interface McpClientInstance {
  connected: boolean;
  callTool: (name: string, args: any) => Promise<any>;
  readResource: (uri: string) => Promise<any>;
  getPrompt: (name: string, args: any) => Promise<any>;
  listTools: () => Promise<any>;
  listResources: () => Promise<any>;
  listPrompts: () => Promise<any>;
  disconnect: () => void;
}

interface UseClientResult extends McpClientInstance {
  connecting: boolean;
  error: Error | null;
}

export function useMcpClient({
  endpoint = '/mcp',
  onConnect,
  onError,
  onDisconnect,
}: McpClientOptions = {}): UseClientResult {
  const [connected, setConnected] = useState(false);
  const [connecting, setConnecting] = useState(false);
  const [error, setError] = useState<Error | null>(null);
  const clientRef = useRef<McpClientInstance | null>(null);
  const requestId = useRef(0);

  const makeRequest = useCallback(async (method: string, params: any = {}) => {
    if (!connected) {
      throw new Error('MCP client not connected');
    }

    const id = ++requestId.current;
    const request = {
      jsonrpc: '2.0',
      id,
      method,
      params,
    };

    try {
      const response = await fetch(endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify(request),
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      const data = await response.json();

      if (data.error) {
        throw new Error(`MCP Error ${data.error.code}: ${data.error.message}`);
      }

      return data.result;
    } catch (err) {
      const error = err instanceof Error ? err : new Error(String(err));
      onError?.(error);
      throw error;
    }
  }, [endpoint, connected, onError]);

  const client: McpClientInstance = {
    connected,
    
    async callTool(name: string, args: any) {
      return makeRequest('tools/call', { name, arguments: args });
    },

    async readResource(uri: string) {
      return makeRequest('resources/read', { uri });
    },

    async getPrompt(name: string, args: any) {
      return makeRequest('prompts/get', { name, arguments: args });
    },

    async listTools() {
      return makeRequest('tools/list');
    },

    async listResources() {
      return makeRequest('resources/list');
    },

    async listPrompts() {
      return makeRequest('prompts/list');
    },

    disconnect() {
      setConnected(false);
      setConnecting(false);
      clientRef.current = null;
      onDisconnect?.();
    },
  };

  useEffect(() => {
    let mounted = true;

    const connect = async () => {
      if (connecting || connected) return;

      setConnecting(true);
      setError(null);

      try {
        // Test connection with a ping or initialize request
        const response = await fetch(`${endpoint}/info`);
        
        if (!response.ok) {
          throw new Error(`Failed to connect: ${response.statusText}`);
        }

        if (mounted) {
          setConnected(true);
          setConnecting(false);
          clientRef.current = client;
          onConnect?.(client);
        }
      } catch (err) {
        const error = err instanceof Error ? err : new Error(String(err));
        if (mounted) {
          setError(error);
          setConnecting(false);
          onError?.(error);
        }
      }
    };

    connect();

    return () => {
      mounted = false;
      client.disconnect();
    };
  }, [endpoint]);

  return {
    ...client,
    connecting,
    error,
  };
}

// React component wrapper
export interface McpClientProps extends McpClientOptions {
  children: (client: UseClientResult) => React.ReactNode;
}

export function McpClient({ children, ...options }: McpClientProps) {
  const client = useMcpClient(options);
  return <>{children(client)}</>;
}