# Changelog

All notable changes to the PHP MCP SDK will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Initial PHP MCP SDK project structure with Composer configuration
- Core type definitions: `ErrorCode`, `McpError`, and `Implementation`
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
