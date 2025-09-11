# MCP Protocol Messages

This document provides an overview of all protocol message types implemented in the PHP MCP SDK.

## Base Types

### Pagination

- `PaginatedRequest` - Base class for paginated requests
- `PaginatedResult` - Base class for paginated results

### Progress

- `Progress` - Progress information for long-running operations
- `ProgressToken` - Token to track progress notifications

## Request Types

### Initialization

- `InitializeRequest` - Client → Server: Begin initialization
- `PingRequest` - Either → Either: Check if other party is alive

### Resources

- `ListResourcesRequest` - Client → Server: List available resources
- `ListResourceTemplatesRequest` - Client → Server: List resource templates
- `ReadResourceRequest` - Client → Server: Read a specific resource
- `SubscribeRequest` - Client → Server: Subscribe to resource updates
- `UnsubscribeRequest` - Client → Server: Unsubscribe from resource updates

### Prompts

- `ListPromptsRequest` - Client → Server: List available prompts
- `GetPromptRequest` - Client → Server: Get a specific prompt

### Tools

- `ListToolsRequest` - Client → Server: List available tools
- `CallToolRequest` - Client → Server: Invoke a tool

### Logging

- `SetLevelRequest` - Client → Server: Set logging level

### Sampling

- `CreateMessageRequest` - Server → Client: Request LLM sampling

### Elicitation

- `ElicitRequest` - Server → Client: Request user input

### Completion

- `CompleteRequest` - Client → Server: Request completion options

### Roots

- `ListRootsRequest` - Server → Client: Request list of root URIs

## Result Types

### Initialization

- `InitializeResult` - Server → Client: Initialization response

### Resources

- `ListResourcesResult` - Server → Client: List of resources
- `ListResourceTemplatesResult` - Server → Client: List of resource templates
- `ReadResourceResult` - Server → Client: Resource contents

### Prompts

- `ListPromptsResult` - Server → Client: List of prompts
- `GetPromptResult` - Server → Client: Prompt content

### Tools

- `ListToolsResult` - Server → Client: List of tools
- `CallToolResult` - Server → Client: Tool execution result

### Sampling

- `CreateMessageResult` - Client → Server: Sampled message

### Elicitation

- `ElicitResult` - Client → Server: User input response

### Completion

- `CompleteResult` - Server → Client: Completion options

### Roots

- `ListRootsResult` - Client → Server: List of root URIs

### Generic

- `EmptyResult` - Either → Either: Success with no data

## Notification Types

### Initialization

- `InitializedNotification` - Client → Server: Initialization complete

### Progress

- `ProgressNotification` - Either → Either: Progress update
- `CancelledNotification` - Either → Either: Request cancellation

### Resources

- `ResourceUpdatedNotification` - Server → Client: Resource changed
- `ResourceListChangedNotification` - Server → Client: Resource list changed

### Prompts

- `PromptListChangedNotification` - Server → Client: Prompt list changed

### Tools

- `ToolListChangedNotification` - Server → Client: Tool list changed

### Logging

- `LoggingMessageNotification` - Server → Client: Log message

### Roots

- `RootsListChangedNotification` - Client → Server: Roots list changed

## Supporting Types

### References

- `PromptReference` - Reference to a prompt
- `ResourceTemplateReference` - Reference to a resource/template

### Elicitation Schemas

- `BooleanSchema` - Boolean field schema
- `StringSchema` - String field schema
- `NumberSchema` - Number/integer field schema
- `EnumSchema` - Enum field schema
- `PrimitiveSchemaDefinition` - Base class for schemas

### Extra Information

- `RequestInfo` - HTTP request information
- `MessageExtraInfo` - Additional message metadata

## Message Union Helpers

These helper classes facilitate parsing and type checking of message unions:

- `ClientRequest` - Union of all client request types
- `ServerRequest` - Union of all server request types
- `ClientNotification` - Union of all client notification types
- `ServerNotification` - Union of all server notification types
- `ClientResult` - Union of all client result types
- `ServerResult` - Union of all server result types

## Usage Example

```php
use MCP\Types\Requests\InitializeRequest;
use MCP\Types\Protocol;
use MCP\Types\Implementation;
use MCP\Types\Capabilities\ClientCapabilities;

// Create an initialize request
$clientInfo = new Implementation('my-client', '1.0.0');
$capabilities = ClientCapabilities::fromArray(['sampling' => []]);

$request = InitializeRequest::create(
    Protocol::LATEST_PROTOCOL_VERSION,
    $capabilities,
    $clientInfo
);

// Send as JSON
$json = json_encode($request);

// Parse incoming message
use MCP\Types\Messages\ClientRequest;

$data = json_decode($json, true);
if (ClientRequest::isValidMethod($data['method'])) {
    $parsed = ClientRequest::fromArray($data);
    // Handle specific request type
}
```

## Protocol Flow

1. **Initialization**

   - Client sends `InitializeRequest`
   - Server responds with `InitializeResult`
   - Client sends `InitializedNotification`

2. **Resource Operations**

   - Client sends `ListResourcesRequest`
   - Server responds with `ListResourcesResult`
   - Client can `ReadResourceRequest` specific resources
   - Client can `SubscribeRequest` to changes

3. **Tool Invocation**

   - Client sends `ListToolsRequest`
   - Server responds with `ListToolsResult`
   - Client sends `CallToolRequest` with arguments
   - Server responds with `CallToolResult`

4. **Notifications**
   - Can be sent at any time after initialization
   - Include progress updates, cancellations, and change notifications
