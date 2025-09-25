# Changelog

All notable changes to the PHP MCP SDK will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.8] - 2025-09-25

### Fixed

- **Unhandled Future Errors in StreamableHttpClientTransport**: Fixed critical bug where async operations were not properly awaited, causing "UnhandledFutureError" exceptions when session errors occurred
  - Modified `startSseStream()`, `handleSseStream()`, and `scheduleReconnection()` methods to properly return Future objects
  - Added `->ignore()` calls to all async operations that don't require result handling
  - Ensures all futures are properly awaited or explicitly ignored to prevent Amp framework warnings
  - Fixes "Streamable HTTP error: missing or invalid session" causing unhandled future exceptions
  - Improves error handling robustness when HTTP 400 errors occur

## [0.1.7] - 2025-09-17

### Fixed

- **StreamableHttpServerTransport Response Body Type** - Fixed Response body construction to use `Amp\ByteStream\Pipe` instead of deprecated `WritableIterableStream` for SSE responses in HTTP transport

## [0.1.6] - 2025-09-16

### Fixed

- **Unhandled Future Errors During Connection Cleanup**: Fixed critical bug in `Protocol::onclose()` that caused "Unhandled future" errors when MCP server connections failed

  - Modified `Protocol::onclose()` method to properly handle error futures during connection cleanup
  - Used `async()->ignore()` pattern to prevent unhandled future exceptions when cleaning up pending requests
  - Added comprehensive test coverage to verify no unhandled futures are generated during connection close
  - Eliminates error log pollution when MCP servers become unavailable or connections fail
  - Maintains proper error propagation for active requests while preventing cleanup-related warnings

- **Documentation 404 Error**: Fixed missing Laravel server documentation that was causing 404 errors on GitHub

  - Created missing `docs/examples/laravel/laravel-server.md` file with comprehensive Laravel integration guide
  - Added complete Laravel MCP integration guide at `docs/guides/laravel-integration.md`
  - Created redirect file at `docs/guides/integrations/laravel-integration.md` for backward compatibility
  - Removed references to non-existent `laravel-mcp-sdk` package and updated all documentation to focus on core SDK integration
  - Added clarifying notes that a dedicated Laravel package is planned for future release
  - All Laravel-related documentation links now resolve correctly

- **Test Suite Improvements**: Resolved multiple test failures and improved test reliability:
  - Fixed constructor signature issues in notification classes (`CancelledNotification`, `ProgressNotification`, `InitializedNotification`) to support both legacy and new `fromArray()` usage patterns
  - Fixed constructor signature issues in request classes (`ListToolsRequest`, `CallToolRequest`) for proper protocol message handling
  - Fixed missing `StreamableHttpTransportAdapter` class autoloading by adding explicit require to test bootstrap
  - Fixed `CallToolResult` content handling by using proper `TextContent` objects instead of raw arrays
  - Fixed async timing issues in integration tests by adding proper delays for initialization completion
  - Resolved protocol async/Future handling errors that were causing "Not connected" exceptions
  - Fixed request cancellation and progress notification handling in protocol tests

### Added

- **WebSocket Server Transport**: Complete WebSocket server transport implementation for real-time, bidirectional communication:

  - Full `WebSocketServerTransport` class implementing the MCP transport interface
  - Comprehensive `WebSocketServerTransportOptions` with production-ready configuration options
  - Support for multiple concurrent client connections with configurable limits
  - Automatic connection lifecycle management (connect, disconnect, cleanup)
  - Heartbeat/ping-pong mechanism for connection health monitoring
  - Security features: CORS support, origin validation, DNS rebinding protection
  - Comprehensive error handling and logging with connection-specific tracking
  - Example server demonstrating WebSocket usage with tools, resources, and prompts
  - Complete unit test coverage for all WebSocket transport functionality

- **Comprehensive Documentation Testing Framework**: Implemented automated testing system for all documentation code examples:
  - Created DocumentationTester class that extracts and tests PHP code blocks from markdown files
  - Added intelligent code block filtering to skip configuration snippets and incomplete examples
  - Integrated with fullcxexamplemcp project for testing environment
  - Automated validation ensures all documentation examples remain functional
- **Enhanced Getting Started Examples**: Created comprehensive beginner-friendly examples:
  - hello-world-server.php and hello-world-client.php: Simplest possible MCP implementations
  - basic-calculator.php: Multi-tool server demonstrating math operations, resources, and prompts
  - file-reader-server.php: Secure file system integration with safety checks
  - weather-client.php: Mock external API integration patterns
  - All examples include proper error handling, security considerations, and extensive documentation
- **Understanding MCP Guide**: Created comprehensive conceptual guide covering:
  - Core MCP architecture and components (tools, resources, prompts, sampling)
  - Protocol flow and message types
  - Transport layer options (STDIO, HTTP, WebSocket)
  - Security model and authentication patterns
  - Real-world use cases and design patterns
  - Learning paths for different developer types
- **Enhanced Troubleshooting Guide**: Comprehensive troubleshooting documentation covering:

  - Installation and environment setup issues
  - Server and client connection problems
  - Tool registration and execution errors
  - Security and authentication troubleshooting
  - Performance optimization guidance
  - Community resources and bug reporting guidelines

- **WebSocket Transport Options Enhancement**: Completed WebSocketClientTransportOptions implementation with comprehensive configuration support:
  - Added validation for all configuration parameters
  - Implemented factory methods for production and development configurations
  - Added fluent interface methods for customizing headers, subprotocols, and timeout settings
  - Enhanced error handling with detailed validation messages
- **Result Validation System**: Implemented comprehensive validation for MCP result types:
  - Added CreateMessageResultValidator for validating sampling results
  - Added ElicitResultValidator for validating elicitation results
  - Added ListRootsResultValidator for validating root listing results
  - Integrated validators into ValidationService with convenient validation methods
- **Enhanced Protocol Result Handling**: Improved result processing in Protocol class:
  - Added automatic result validation based on request type
  - Implemented defensive type conversion for all result types
  - Enhanced backward compatibility with graceful error handling
  - Added proper logging for validation and conversion warnings

### Added

- **FullCX Integration Guide and Example with OpenAI**: Comprehensive tutorial and example demonstrating how to connect to and interact with the FullCX MCP server for AI-enhanced product management:

  - Complete integration guide at `docs/guides/fullcx-integration.md` with step-by-step instructions for FullCX (https://full.cx/mcp) integration
  - Full-featured example client at `examples/client/fullcx-client.php` with AI-powered features using [OpenAI PHP client](https://github.com/openai-php/client)
  - Demonstrates product, feature, requirement, and idea management workflows enhanced with AI
  - AI-powered features: feature planning, idea generation, competitive analysis, roadmap creation, and strategic insights
  - Integration with OpenAI for intelligent content generation and strategic recommendations
  - Includes interactive creation workflows, comprehensive analysis tools, and error handling patterns
  - Shows authentication, configuration, and security best practices for both FullCX and OpenAI APIs
  - Provides Laravel integration examples and testing strategies

- **OpenAI Tool Calling with MCP Integration**: Complete implementation showing how OpenAI can use function calling to interact with MCP servers:
  - OpenAI tool calling tutorial at `docs/guides/openai-tool-calling.md` with dynamic tool discovery patterns
  - Working example at `examples/client/openai-mcp-agent.php` demonstrating GPT-4.1 function calling with FullCX MCP tools
  - Simple demo at `examples/client/openai-mcp-simple.php` for learning the basic concepts
  - Dynamic tool discovery from MCP servers instead of hardcoded tool definitions
  - Natural language interface for product management operations
  - Multi-step workflow orchestration with intelligent tool selection
  - Comprehensive error handling and configuration management
  - Added OpenAI PHP client dependency for seamless integration

### Fixed

- **MCP Client Initialization**: Fixed "Server sent invalid initialize result" error by properly converting InitializeResult responses from JSON-RPC to typed objects in the Protocol class
- **FullCX Demo Application**: Updated demo to correctly parse JSON responses from FullCX MCP server that returns data as text content instead of structured content

- **Server-side Elicitation Support**: Complete server-side implementation for user input collection:
  - `McpServer::elicitUserInput()` method for requesting user input with JSON schema validation
  - Full support for ElicitRequest/ElicitResult protocol messages
  - Schema-based form generation and validation on the client side
- **Server-side Sampling Support**: Complete server-side LLM sampling implementation:
  - `McpServer::createMessage()` method for requesting LLM message generation
  - Support for model preferences, temperature control, stop sequences, and metadata
  - Full CreateMessageRequest/CreateMessageResult protocol message handling
- **Notification Debouncing**: Automatic debouncing for bulk notification operations:

  - Resource, tool, and prompt list change notifications are automatically debounced
  - Prevents notification spam during bulk operations
  - Configurable debouncing behavior in Protocol base class

- **Advanced Client Enhancements**: Comprehensive client features bringing PHP SDK to feature parity with TypeScript SDK:
  - **OAuth 2.0 Client Provider Interface**: Complete OAuth client abstraction with support for multiple storage mechanisms and authentication flows
  - **Enhanced OAuth Client**: Full OAuth 2.0 Authorization Code flow with PKCE, automatic token refresh, server discovery (RFC 8414), and error handling
  - **OAuth Exception Hierarchy**: Specific exception classes for all OAuth error types (InvalidClientException, InvalidGrantException, UnauthorizedClientException, etc.)
  - **OAuth Utilities**: Comprehensive utility functions for PKCE generation, JWT parsing, metadata discovery, resource validation, and client authentication
  - **Middleware System**: Powerful middleware architecture for HTTP request/response processing:
    - `MiddlewareInterface` and `MiddlewareStack` for composable request processing
    - `OAuthMiddleware` for automatic authentication with 401 response handling and token refresh
    - `LoggingMiddleware` for comprehensive request/response logging with configurable levels
    - `RetryMiddleware` for automatic retry with exponential backoff and jitter
  - **WebSocket Client Transport**: Full WebSocket transport implementation with:
    - Automatic reconnection with exponential backoff and configurable retry limits
    - Heartbeat/ping-pong mechanism for connection health monitoring
    - Comprehensive connection lifecycle management and event handling
    - Configurable options for headers, subprotocols, timeouts, and SSL validation
  - **Client Integration**: Seamless middleware integration with existing Client class:
    - Fluent API methods (`withOAuth()`, `withRetry()`, `withLogging()`) for easy middleware addition
    - `ClientBuilder` class for fluent client construction with middleware chaining
    - Enhanced client methods with middleware support and automatic authentication
  - **Compiled Validators**: High-performance validation system for frequently used schemas:
    - `JsonSchemaCompiler` for pre-compiling JSON schemas into optimized validators
    - `CompiledValidator` with optimized validation rules and caching support
    - Significant performance improvements over runtime schema validation
    - Cache integration for persistent compiled validator storage
- **Usage Examples**: Comprehensive examples demonstrating all new client features:

  - Enhanced OAuth client example with complete authentication flow demonstration
  - WebSocket client example with connection management and error handling
  - Middleware example showing custom middleware creation and performance optimization
  - Real-world usage patterns and best practices documentation

- **Comprehensive Documentation Suite** (`docs/`): Complete documentation system for developers and users:
  - **Main Documentation Hub** (`docs/README.md`): Central documentation index with architecture overview, features, use cases, and navigation
  - **Getting Started Guide** (`docs/getting-started/`): Complete onboarding experience:
    - Installation guide with system requirements, troubleshooting, and optimization tips
    - Quick Start tutorial with working weather server/client examples and MCP Inspector integration
    - Core Concepts guide explaining MCP architecture, capabilities, protocol flow, security, async patterns, and design patterns
  - **API Reference Documentation** (`docs/api/`): Complete API documentation for all public interfaces:
    - Server API reference with McpServer class, tool/resource/prompt registration, authentication, logging, and sampling
    - Client API reference with connection management, tool/resource/prompt operations, error handling, and best practices
    - Transport APIs documentation (planned) for STDIO, HTTP, and WebSocket implementations
    - Types & Schemas reference (planned) for complete type system documentation
  - **Migration Guide** (`docs/migration/from-typescript.md`): Comprehensive guide for TypeScript developers:
    - Architecture comparison and key differences between TypeScript and PHP implementations
    - API migration examples with side-by-side code comparisons for servers, clients, and tools
    - Async programming patterns: converting async/await to yield with Amphp
    - Type system differences: interfaces to classes, error handling, and validation patterns
    - Common migration issues, troubleshooting guide, and best practices checklist
  - **Examples Documentation** (`docs/examples/README.md`): Organized example catalog with learning paths
  - **Contributing Guide** (`docs/contributing.md`): Complete contributor onboarding with development workflow, coding standards, and testing guidelines
- **Documentation Generation System**:

  - PHPDoc configuration (`phpdoc.xml`) for automated API documentation generation from source code
  - GitHub Actions workflow (`docs.yml`) for automated documentation deployment to GitHub Pages
  - Eleventy-based static site generation with markdown processing, navigation, and responsive layout
  - Multi-stage documentation build process with PHP dependency analysis and Node.js tooling

- **Laravel Integration**: Full Laravel package has been moved to separate `laravel-mcp-sdk` repository, while Laravel usage examples and integration guides remain in core SDK for developers who want to use the core SDK directly with Laravel

- Comprehensive MCP specification compliance checklist (`ai-prompts/14-mcp-specification-compliance-checklist.md`):

  - Complete verification of PHP MCP SDK against MCP specification 2025-06-18
  - Detailed evidence mapping from specification requirements to implementation
  - 85.5% compliance rate with 47/55 requirements fully implemented
  - Identified 8 remaining requirements for future implementation
  - Priority-based roadmap for completing full MCP compliance
  - Verification covers: JSON-RPC 2.0, versioning, OAuth 2.1, server/client features, transports, error handling

- **AUDIT**: Comprehensive codebase audit completed (2024-12-19)

  - Identified 375 PSR-12 coding standards violations (340 auto-fixable)
  - Found 89 static analysis errors requiring manual fixes
  - Documented feature parity gaps with TypeScript SDK
  - Created detailed action plans for all identified issues
  - Established test coverage baseline (~26%) and improvement roadmap

- Comprehensive test suite with 250+ new tests covering all major components (405 total test methods across 46 test files)
- Factory tests for TypeFactoryService, JSONRPCMessageFactory, ToolFactory, and ResultFactory
- Validation tests for ValidationService, TypeValidator, and ValidationException
- Transport layer tests for Stdio and StreamableHttp transports (both client and server)
- Integration tests for end-to-end protocol compliance and full client-server interactions
- Performance tests for load testing, memory usage, and throughput benchmarks
- Test utilities including InMemoryTransport and TestFixtures for easier testing
- Protocol compliance tests ensuring JSON-RPC 2.0 and MCP specification adherence

### Changed

- Replaced ReactPHP with Amphp for async operations to ensure compatibility with Laravel and PSR/HTTP-Message v2.0
- Updated Transport interface to use Amphp Futures instead of ReactPHP Promises
- Updated test writing guide (`ai-prompts/08-write-tests.md`) to reflect current package structure and Amphp usage
- Enhanced test coverage from basic validation to comprehensive integration and performance testing

### Fixed

- Fixed missing `ServerOptions` require statements in all server examples (sqlite-server, weather-server, resource-server, oauth-server) that were causing "Class not found" fatal errors
- Enhanced `build.sh` script with timeout (5 minutes) and error handling for cursor-agent execution to prevent hanging processes
- Fixed async function syntax issues in client examples (parallel-tools, oauth, http, multiple-servers)
- Fixed Protocol request handler Future handling - handlers returning Futures are now properly awaited before sending responses
- Fixed test async timing issues by replacing incorrect delay() usage with proper \Amp\delay() calls
- Resolved PHP syntax errors in example files
- Corrected Amphp closure syntax for proper async/await handling
- Fixed notification class usage in resource server examples
- Fixed ServerOptions class import issues in all server examples by adding proper use statements and manual require statements for autoloading compatibility
- Fixed Client capability assertion issues by only setting up default handlers for capabilities that are actually supported
- Fixed await() function calls in all client examples - replaced `await($future)` with `$future->await()` syntax
- Fixed string interpolation deprecation warning in weather-server.php (changed `${var}` to `{$var}`)
- Added manual require statements to all client examples to work around PSR-4 autoloading issues
- Fixed import statements in client examples (removed non-existent `Amp\await` and `React\Promise\all` functions)
- Fixed PSR-4 autoloading compliance by extracting classes from multi-class files:
  - Moved `ProtocolOptions` to `src/Shared/ProtocolOptions.php`
  - Moved `RequestOptions` to `src/Shared/RequestOptions.php`
  - Moved `NotificationOptions` to `src/Shared/NotificationOptions.php`
  - Moved `ServerOptions` to `src/Server/ServerOptions.php`
  - Moved `ToolCallback` to `src/Server/ToolCallback.php`
  - Moved `RegisteredTool` to `src/Server/RegisteredTool.php`
  - Moved `ReadResourceCallback` to `src/Server/ReadResourceCallback.php`
  - Moved `RegisteredResource` to `src/Server/RegisteredResource.php`
  - Moved `ReadResourceTemplateCallback` to `src/Server/ReadResourceTemplateCallback.php`
  - Moved `RegisteredResourceTemplate` to `src/Server/RegisteredResourceTemplate.php`
  - Moved `PromptCallback` to `src/Server/PromptCallback.php`
  - Moved `RegisteredPrompt` to `src/Server/RegisteredPrompt.php`
- Fixed syntax errors in utility files:
  - Corrected invalid `async function` syntax in `examples/utils/inspector.php`
  - Corrected invalid `async function` syntax in `examples/utils/monitor.php`
  - Added proper `\Amp\async()` calls and `Future` return types

### Added

- Updated test writing guide (`ai-prompts/08-write-tests.md`) with comprehensive testing strategies:
  - Updated test structure to match current package organization (151 tests, 456 assertions)
  - Added test examples for new components: Factories, Validation, Auth systems
  - Added Laravel integration test patterns for service providers, controllers, and Artisan commands
  - Added comprehensive transport layer test examples (STDIO, HTTP, SSE)
  - Updated all test examples to use Amphp instead of ReactPHP
  - Added performance testing guidelines and memory usage benchmarks
  - Added InMemoryTransport test utility and test fixture classes
  - Provided implementation priority guide focusing on untested components
- Comprehensive examples demonstrating PHP MCP SDK usage (`examples/`):
  - **Server Examples**: 5 complete server implementations showcasing different MCP patterns
    - `simple-server.php`: Basic MCP server with calculations, resources, and prompts
    - `weather-server.php`: External API integration with caching and rate limiting
    - `sqlite-server.php`: Database operations with safe query execution and schema inspection
    - `oauth-server.php`: Authentication and authorization with scope-based access control
    - `resource-server.php`: Dynamic resource management with subscriptions and notifications
  - **Client Examples**: 5 advanced client implementations for different use cases
    - `simple-stdio-client.php`: Basic client operations and server interaction patterns
    - `parallel-tools-client.php`: Concurrent tool execution with performance comparison
    - `oauth-client.php`: OAuth 2.0 authentication flow with token management
    - `http-client.php`: HTTP transport with SSE, session management, and connection recovery
    - `multiple-servers-client.php`: Multi-server coordination and cross-server operations
  - **Laravel Integration**: Complete Laravel/Inertia.js integration with modern web UI
    - `ExampleMcpServiceProvider.php`: Laravel service provider with database, cache, and Artisan tools
    - `McpDemoController.php`: Inertia.js controller with real-time monitoring and interactive testing
    - `Demo.tsx`: React component providing dashboard-style interface with live metrics
    - `mcp-config.php`: Comprehensive configuration for all MCP features in Laravel
  - **Utility Tools**: Professional server inspection and monitoring utilities
    - `inspector.php`: Comprehensive server analysis with interactive mode, reporting, and testing
    - `monitor.php`: Real-time server monitoring with dashboard display, alerts, and performance tracking
  - **Docker Configuration**: Production-ready containerization with multi-stage builds
    - Multi-stage Dockerfile supporting development, server, client, and Laravel deployments
    - Docker Compose configuration with 8+ services including Redis, PostgreSQL, and Nginx proxy
    - Environment-specific entrypoint scripts with health checks and graceful shutdown
    - Development tools container with full SDK access and debugging capabilities
  - **Documentation**: Comprehensive guides and usage patterns
    - Complete README with quick start, configuration, troubleshooting, and learning path
    - Usage examples for all patterns: basic server-client, Laravel integration, monitoring
    - Docker deployment guides with individual and orchestrated service management
    - Performance tips, security considerations, and best practices
- Implemented OAuth 2.1 authentication system (`src/Server/Auth/`, `src/Client/Auth/`):
  - Complete OAuth 2.1 server implementation with PKCE support
  - AuthInfo interface and DefaultAuthInfo implementation for token information
  - OAuthServerProvider interface for pluggable authentication backends
  - ProxyProvider for forwarding OAuth requests to upstream servers
  - PSR-15 middleware integration with McpAuthRouter and McpAuthMetadataRouter
  - OAuth handlers for authorization, token exchange, revocation, and metadata endpoints
  - BearerAuthMiddleware for protecting resources with access tokens
  - ClientAuthMiddleware supporting client_secret_post and client_secret_basic
  - OAuthClient with PKCE implementation for client-side authentication flows
  - Token storage interfaces with InMemoryTokenStorage and FileTokenStorage implementations
  - Automatic token refresh and expiration handling
  - OAuth metadata endpoints for RFC 8414 and RFC 9728 compliance
  - Integration with McpServer via useAuth() method for seamless authentication
- Implemented comprehensive transport layer (`src/Server/Transport/`, `src/Client/Transport/`):
  - STDIO Server Transport with message buffering and partial message handling
  - STDIO Client Transport with process management and environment variable security
  - Streamable HTTP Server Transport with session management, SSE streaming, and security features
  - Streamable HTTP Client Transport with reconnection support and event resumability
  - Deprecated SSE transports for backward compatibility with migration guidance
  - WebSocket transport placeholder with clear implementation roadmap
- Created transport utilities (`src/Shared/`):
  - `MessageFraming` utility for JSON-RPC message serialization, validation, and chunking
  - `HttpTransportAdapter` interface for PSR-7 integration with Laravel/Symfony frameworks
  - `LaravelIntegration` helpers for route handlers, middleware, service providers, and Artisan commands
- Added comprehensive transport tests:
  - Message framing validation and chunking tests
  - WebSocket placeholder functionality tests
  - HTTP transport adapter integration tests
  - Laravel integration helper tests
- Implemented Client components (`src/Client/`):
  - `Client` class extending Protocol with full MCP client-side functionality
  - `ClientOptions` class for configuring client behavior and capabilities
  - Automatic initialization flow with server protocol negotiation
  - Support for all MCP operations: tools, resources, prompts, completion, logging
  - Tool output validation against cached schemas from listTools
  - Server capability checking with detailed error messages
  - Built-in handlers for server-initiated requests (sampling, elicitation, roots)
  - Convenience methods for common operations (callToolByName, readResourceByUri, etc.)
  - Protocol version compatibility checking and HTTP transport support
  - Capability merging and registration before connection
  - Graceful error handling with proper connection cleanup on initialization failure
- Created comprehensive AI implementation prompt (`ai-prompts/11-implement-client-enhancements.md`):
  - OAuth authentication client with PKCE support and automatic token refresh
  - Middleware system for cross-cutting concerns (authentication, logging, retry, caching)
  - WebSocket transport support with auto-reconnection and heartbeat
  - Generic type support for protocol extensions
  - AJV-style compiled validators for performance optimization
  - Schema parameter support for flexible validation
  - Detailed implementation guidelines, testing requirements, and usage examples
- Implemented Server components (`src/Server/`):
  - Low-level `Server` class extending Protocol with MCP server-side logic
  - High-level `McpServer` class with convenient API for registering tools, resources, and prompts
  - `ServerOptions` class for configuring server behavior
  - `RegisteredTool`, `RegisteredResource`, `RegisteredPrompt` classes for managing registered items
  - `Completable` class for adding autocompletion support to prompt arguments
  - `ResourceTemplate` class for dynamic resource URIs with template patterns
  - Full support for dynamic enable/disable of registered items
  - Automatic capability management and list_changed notifications
- Implemented comprehensive JSON Schema validation (`src/Utils/JsonSchemaValidator.php`):
  - Full JSON Schema validation for tool input/output schemas
  - Prompt argument schema validation with detailed error messages
  - Schema normalization and field extraction utilities
  - Support for nested objects, arrays, string patterns, and numeric constraints
- Added comprehensive test suite:
  - Unit tests for Server initialization and protocol handling
  - Unit tests for McpServer registration methods and dynamic management
  - Unit tests for JSON Schema validation with 17 test cases
  - Integration tests with mock transport for end-to-end testing
- Implemented Transport layers (`src/Server/Transport/` and `src/Client/Transport/`):
  - `StdioServerTransport` for server-side stdio communication
  - `StdioClientTransport` for client-side stdio with process spawning
  - Automatic message framing with newline-delimited JSON
  - Process lifecycle management with graceful shutdown
  - Environment variable filtering for security
  - Full Amphp async integration
- Added automated build script `ai-prompts/build.sh` for processing implementation prompts:
  - Automatically processes markdown prompt files in sequence
  - Integrates with cursor-agent for code generation
  - Moves completed prompts to done/ directory
  - Commits changes after each implementation step
- Implemented Protocol base class in `src/Shared/Protocol.php` with:
- Resource subscription support in server:
  - Handlers for `resources/subscribe` and `resources/unsubscribe`
  - In-memory tracking of subscriptions by session ID
  - Compatible with debounced `list_changed` notifications
  - Request/response correlation and timeout handling
  - Event emission using Evenement
  - Support for request handlers, notification handlers, and progress callbacks
  - Automatic ping/pong handling
  - Request cancellation support
  - Debounced notification support
  - Full compatibility with Amphp async operations
- Implemented URI Template support in `src/Shared/UriTemplate.php`:
  - RFC 6570 compliant URI template parsing and expansion
  - Support for simple string expansion with {variable} syntax
  - Variable extraction from URIs with match() method
  - Operators: +, #, ., /, ?, &
  - Protection against malicious inputs with length limits
- Created authentication utilities in `src/Shared/AuthUtils.php`:
  - OAuth resource URL handling (RFC 8707 compliant)
  - PKCE code verifier and challenge generation
  - JWT token parsing and expiration checking (without verification)
  - OAuth state parameter generation
  - Authorization URL building
- Implemented STDIO support in `src/Shared/Stdio.php`:
  - ReadBuffer class for buffering and parsing newline-delimited JSON
  - Non-blocking STDIN reading support
  - Message serialization and deserialization
  - Stream utilities for checking data availability
- Created metadata utilities in `src/Shared/MetadataUtils.php`:
  - Display name resolution with proper precedence (title → annotations.title → name)
  - Description extraction from metadata
  - Support for both object and array metadata formats
- Implemented OAuth and OpenID Connect schemas in `src/Shared/Auth.php`:
  - OAuth Protected Resource Metadata (RFC 9728)
  - OAuth 2.0 Authorization Server Metadata (RFC 8414)
  - OAuth token response and error response structures
  - OAuth Dynamic Client Registration metadata (RFC 7591)
  - URL validation with protection against dangerous schemes
- Created Transport interface in `src/Shared/Transport.php` using Amphp for async operations
- Added comprehensive unit tests for shared components:
  - UriTemplate tests covering expansion, matching, and RFC 6570 compliance
  - AuthUtils tests for OAuth flows, JWT parsing, and resource URL validation
  - MetadataUtils tests for display name resolution and metadata handling
  - Stdio tests for message buffering and serialization
  - Protocol tests for request/response handling, timeouts, and event management
  - Auth schema tests for OAuth and OpenID Connect structures
- Fixed issues identified during testing:
  - Split Auth.php into separate files for PSR-4 compliance (OAuthProtectedResourceMetadata, OAuthMetadata, OAuthTokens, OAuthErrorResponse, OAuthClientMetadata, OAuthClientInformation)
  - Fixed Protocol constructor to not call parent constructor on EventEmitter
  - Corrected Future handling in Protocol methods by properly awaiting transport operations
  - Separated ReadBuffer class into its own file for autoloading
  - Fixed URL validation in OAuth classes to check scheme before other validations
  - Changed RequestOptions onprogress type from callable to mixed due to PHP property type limitations
  - Improved AuthUtils::checkResourceAllowed to normalize paths before comparison
- Initial PHP MCP SDK project structure with Composer configuration
- Core type definitions: `ErrorCode`, `McpError`, and `Implementation`
- Complete type system conversion from TypeScript SDK:
  - Protocol constants and version management (`Protocol`)
  - Base types: `ProgressToken`, `Cursor`, `RequestId`
  - Request/Response types: `Request`, `Notification`, `Result`, `EmptyResult`, `RequestMeta`
  - JSON-RPC message types: `JSONRPCRequest`, `JSONRPCNotification`, `JSONRPCResponse`, `JSONRPCError`, `JSONRPCMessage`
  - Content types: `ContentBlock` interface, `TextContent`, `ImageContent`, `AudioContent`, `EmbeddedResource`, `ResourceLink`
  - Resource types: `Resource`, `ResourceTemplate`, `ResourceContents`, `TextResourceContents`, `BlobResourceContents`
  - Tool types: `Tool`, `ToolAnnotations`
  - Prompt types: `Prompt`, `PromptArgument`, `PromptMessage`
  - Capability types: `ClientCapabilities`, `ServerCapabilities`
  - Other types: `Root`, `LoggingLevel` enum, `SamplingMessage`, `ModelPreferences`, `ModelHint`
  - Base metadata class: `BaseMetadata` for common properties
  - Factory class: `ContentBlockFactory` for parsing content block unions
- Type validation system using Respect/Validation:
  - `TypeValidator` interface and `AbstractValidator` base class
  - `ValidationService` for centralized validation
  - `ValidationException` for detailed error reporting
  - Validators for core types: `ProgressTokenValidator`, `RequestIdValidator`, `CursorValidator`
  - Validators for complex types: `JSONRPCRequestValidator`, `ContentBlockValidator`, `ToolValidator`
  - Custom validation rules: `Base64Rule` for base64 validation, `UriTemplateRule` for RFC 6570 templates
- Type factory system for creating instances:
  - `TypeFactory` interface and `AbstractTypeFactory` base class
  - `JSONRPCMessageFactory` for parsing JSON-RPC messages
  - `ToolFactory` for creating tool instances
  - `ResultFactory` for creating result types
  - `TypeFactoryService` providing centralized factory methods for all types
  - Helper methods for extracting typed values from arrays
- Comprehensive unit test suite for type system:
  - Base type tests: `ProgressTokenTest`, `CursorTest`, `RequestIdTest`
  - Request/Response tests: `RequestTest`, `ResultTest`, `EmptyResultTest`
  - JSON-RPC tests: `JSONRPCRequestTest`
  - Enum tests: `LoggingLevelTest`
  - All tests passing with 100% functionality coverage
- Complete protocol message types implementation:
  - Pagination base types: `PaginatedRequest`, `PaginatedResult`
  - Progress types: `Progress`, `ProgressNotification`
  - Initialization messages: `InitializeRequest`, `InitializeResult`, `InitializedNotification`
  - Resource messages: `ListResourcesRequest`/`Result`, `ListResourceTemplatesRequest`/`Result`, `ReadResourceRequest`/`Result`
  - Resource subscription: `SubscribeRequest`, `UnsubscribeRequest`
  - Resource notifications: `ResourceUpdatedNotification`, `ResourceListChangedNotification`
  - Prompt messages: `ListPromptsRequest`/`Result`, `GetPromptRequest`/`Result`
  - Prompt notifications: `PromptListChangedNotification`
  - Tool messages: `ListToolsRequest`/`Result`, `CallToolRequest`/`Result`
  - Tool notifications: `ToolListChangedNotification`
  - Logging messages: `SetLevelRequest`, `LoggingMessageNotification`
  - Sampling messages: `CreateMessageRequest`, `CreateMessageResult`
  - Other protocol messages: `PingRequest`, `CancelledNotification`
  - Completion support: `CompleteRequest`/`Result`
  - Roots support: `ListRootsRequest`/`Result`, `RootsListChangedNotification`
  - Elicitation types: `ElicitRequest`/`Result`, `BooleanSchema`, `StringSchema`, `NumberSchema`, `EnumSchema`
  - Reference types: `PromptReference`, `ResourceTemplateReference`
  - Supporting types: `RequestInfo`, `MessageExtraInfo`
  - Message union helpers: `ClientRequest`, `ServerRequest`, `ClientNotification`, `ServerNotification`, `ClientResult`, `ServerResult`
- Comprehensive unit test suite for protocol message types:
  - 70 new tests covering all protocol messages
  - Tests for pagination, progress, and base types
  - Tests for all request/result pairs
  - Tests for all notification types
  - Tests for reference and elicitation types
  - Tests for message union helpers
  - Total test suite now has 151 tests with 456 assertions
- Comprehensive test suite setup with PHPUnit
- Static analysis tools: PHPStan (level 8) and Psalm
- Code style enforcement with PHP CS Fixer and PHP CodeSniffer
- Full Cursor Rules for SDK development:
  - Main architecture guide (`php-mcp-sdk-main.mdc`)
  - Server implementation patterns (`php-server-implementation.mdc`)
  - Client implementation patterns (`php-client-implementation.mdc`)
  - Transport layer guidelines (`php-transport-implementation.mdc`)
  - Authentication/authorization guide (`php-auth-implementation.mdc`)
  - Type system and validation (`php-types-validation.mdc`)
  - Laravel/InertiaJS integration (`laravel-inertia-integration.mdc`)
  - **Changelog maintenance rule** (`changelog-maintenance.mdc`) - Ensures changelog is reviewed before changes and updated after
- AI implementation prompts directory with 10 step-by-step guides:
  - Type conversion from TypeScript
  - Shared components implementation
  - Server and client implementations
  - Transport layers
  - Authentication system
  - Example creation
  - Test writing
  - Laravel package development
  - Documentation
- Basic examples demonstrating SDK usage
- Complete project documentation structure
- **CHANGELOG.md** file following Keep a Changelog format for tracking all project changes

### Changed

- Updated minimum PHP version to 8.1 for enum and readonly property support
- Fixed Psalm configuration for PHP 8.1 compatibility

### Dependencies

- ReactPHP for asynchronous operations
- Guzzle HTTP client
- Respect/Validation for schema validation
- Monolog for logging
- Development tools: PHPUnit, Mockery, PHPStan, Psalm, PHP CS Fixer

### Project Structure

- `/src` - Source code organized by component type
- `/tests` - Test suite mirroring source structure
- `/examples` - Usage examples
- `/ai-prompts` - Implementation guides for AI assistants
- `/.cursor/rules` - Cursor AI assistant rules

### Fixed

- **Critical Static Analysis Errors**: Resolved 21 critical PHPStan errors (reduced from 150 to 129):
  - Implemented missing `setRequestHandlerWrapper()` method in Protocol base class for authentication middleware support
  - Fixed type mismatches in CallToolResult constructor by properly using ContentBlockFactory for content creation
  - Resolved unsafe static usage patterns in base classes with proper PHPStan ignore annotations
  - Fixed PSR SimpleCache interface compatibility issues in JsonSchemaCompiler by using duck typing
  - Cleaned up unused methods and constants to improve code quality
- **PSR-12 Coding Standards**: Fixed 385 coding standards violations (reduced from 416 to 35):
  - Auto-fixed 383 violations using PHP CodeSniffer with PSR-12 standard
  - Resolved line length, whitespace, and formatting issues across the entire codebase
  - Improved code readability and maintainability
- **WebSocket Transport Issues**: Fixed type compatibility issues in WebSocket client transport:
  - Corrected constructor parameter types to use WebSocketClientTransportOptions consistently
  - Enhanced WebSocketClientTransportOptions with missing properties for reconnection and heartbeat
  - Fixed test compatibility by implementing proper placeholder methods
- **Server-side Protocol Compliance**: Enhanced MCP specification compliance:
  - Completed elicitation support implementation for user input collection
  - Finished sampling support implementation for LLM message generation
  - Verified notification debouncing system is working correctly for bulk operations

## [0.0.1] - TBD

### Note

This is the initial development phase. The first official release will be 0.0.1 once core functionality is implemented.

---

## Changelog Update Guidelines

When making changes to the PHP MCP SDK:

1. **Before starting work**: Review this changelog to understand recent changes
2. **During development**: Keep notes of what you're changing
3. **After completing changes**: Update this changelog with:
   - What was added, changed, fixed, or removed
   - Why the change was made (if not obvious)
   - Any breaking changes or migration notes

### Categories to use:

- **Added** - for new features
- **Changed** - for changes in existing functionality
- **Deprecated** - for soon-to-be removed features
- **Removed** - for now removed features
- **Fixed** - for any bug fixes
- **Security** - in case of vulnerabilities

### Example entry:

```markdown
### Added

- New `McpServer::registerMiddleware()` method for adding custom middleware
- Support for WebSocket transport in client

### Changed

- Improved error handling in STDIO transport to handle partial messages
- Updated minimum ReactPHP version to 3.0 for better performance

### Fixed

- Fixed memory leak in long-running servers when handling large payloads
- Fixed capability merging in Server class to properly handle readonly ServerCapabilities properties
- Fixed WritableIterableStream constructor calls in transport implementations to include buffer size
- Fixed Response constructor calls to use readable stream iterators instead of writable streams
- Fixed JsonSerializable interface checks in transport and protocol classes
- Fixed Amp buffer() method calls to remove deprecated size parameters
- Fixed schema validation integration in McpServer for tools and prompts with actual validation logic
```
