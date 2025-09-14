# Framework Integration Examples

These examples demonstrate how to integrate the PHP MCP SDK with popular PHP frameworks. Each example shows complete integration patterns that you can use as templates for your own applications.

## 🏗️ Available Integrations

### Laravel Integration

**File:** `laravel-mcp-integration.php`

Complete Laravel integration example showing:

- Service Container patterns
- Database integration with Query Builder
- Validation system integration
- Event system usage
- Mock ORM and service layer

**Features:**

- 3 tools: `get_users`, `create_user`, `get_posts`
- 2 resources: application info, database schema
- 1 prompt: Laravel development help

**Usage:**

```bash
php laravel-mcp-integration.php
```

### Symfony Integration

**File:** `symfony-mcp-integration.php`

Complete Symfony integration example showing:

- Dependency Injection Container
- Console Command integration
- Event Dispatcher system
- Validator component usage

**Features:**

- 4 tools: `console_command`, `list_commands`, `validate_data`, `dispatch_event`
- 2 resources: application info, container services
- 1 prompt: Symfony development help

**Usage:**

```bash
php symfony-mcp-integration.php
```

## 🚀 Quick Start

1. **Choose your framework** and run the corresponding example
2. **Explore the patterns** used for integration
3. **Adapt to your needs** by modifying the examples
4. **Build your application** using the demonstrated patterns

## 📚 Related Documentation

- [Laravel Integration Guide](../../docs/guides/integrations/laravel-integration.md)
- [Symfony Integration Guide](../../docs/guides/integrations/symfony-integration.md)
- [Getting Started Examples](../getting-started/README.md)
