# Changelog

All notable changes to the PHP MCP SDK will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- Replaced ReactPHP with Amphp for async operations to ensure compatibility with Laravel and PSR/HTTP-Message v2.0
- Updated Transport interface to use Amphp Futures instead of ReactPHP Promises

### Added

- Created Transport interface in `src/Shared/Transport.php` using Amphp for async operations
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
```
