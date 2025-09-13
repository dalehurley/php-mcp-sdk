# Contributing to PHP MCP SDK

Thank you for your interest in contributing to the PHP MCP SDK! This guide will help you get started with contributing code, documentation, examples, and bug reports.

## 🎯 Ways to Contribute

- 🐛 **Bug Reports** - Help us identify and fix issues
- 💡 **Feature Requests** - Suggest new capabilities
- 📝 **Documentation** - Improve guides, API docs, and examples
- 🔧 **Code Contributions** - Bug fixes, features, and improvements
- 🧪 **Testing** - Add tests and improve coverage
- 💬 **Community Support** - Help others in discussions

## 🚀 Getting Started

### Prerequisites

- **PHP 8.1+** with extensions: `json`, `mbstring`, `openssl`
- **Composer** for dependency management
- **Git** for version control
- **Node.js 18+** (optional, for MCP Inspector testing)

### Development Setup

1. **Fork and Clone**
   ```bash
   # Fork the repository on GitHub, then:
   git clone https://github.com/YOUR-USERNAME/php-mcp-sdk.git
   cd php-mcp-sdk
   ```

2. **Install Dependencies**
   ```bash
   composer install
   ```

3. **Verify Setup**
   ```bash
   composer test
   composer cs-check
   composer phpstan
   ```

4. **Create Feature Branch**
   ```bash
   git checkout -b feature/your-feature-name
   ```

## 📋 Development Workflow

### Code Standards

We follow strict coding standards to ensure consistency:

- **PSR-12** coding style
- **PHPStan Level 8** static analysis
- **Psalm** type checking
- **PHPUnit** for testing

### Before Submitting

Run the full check suite:

```bash
# Code style
composer cs-fix

# Static analysis  
composer phpstan
composer psalm

# Tests
composer test
composer test-coverage

# All checks
composer check
```

### Commit Messages

Use [Conventional Commits](https://conventionalcommits.org/) format:

```
type(scope): description

[optional body]

[optional footer]
```

**Types:**
- `feat` - New features
- `fix` - Bug fixes  
- `docs` - Documentation changes
- `style` - Code style changes
- `refactor` - Code refactoring
- `test` - Adding/fixing tests
- `chore` - Maintenance tasks

**Examples:**
```bash
git commit -m "feat(server): add batch tool registration method"
git commit -m "fix(client): handle connection timeout errors"
git commit -m "docs(api): update server API examples"
```

## 🐛 Bug Reports

When reporting bugs, include:

### Required Information
- **PHP Version**: `php -v`
- **SDK Version**: Check `composer.json`
- **Operating System**: OS and version
- **Error Message**: Complete error output
- **Minimal Reproduction**: Smallest code that reproduces the issue

### Bug Report Template
```markdown
**Bug Description**
Brief description of the issue.

**Environment**
- PHP Version: 8.1.x
- SDK Version: 1.0.x
- OS: Ubuntu 22.04

**Steps to Reproduce**
1. Create server with...
2. Call tool with...  
3. Error occurs...

**Expected Behavior**
What should have happened.

**Actual Behavior** 
What actually happened.

**Error Output**
```
Complete error message and stack trace
```

**Minimal Code Example**
```php
// Minimal code that reproduces the issue
```
```

## 💡 Feature Requests

For new features:

1. **Check Existing Issues** - Avoid duplicates
2. **Discuss First** - Open a discussion for major features  
3. **Follow Template** - Use the feature request template
4. **Be Specific** - Provide clear use cases and requirements

### Feature Request Template
```markdown
**Feature Summary**
Brief description of the requested feature.

**Motivation**
Why is this feature needed? What problem does it solve?

**Detailed Description**
Comprehensive explanation of the feature.

**Usage Examples**
```php
// How the feature would be used
```

**Additional Context**
Any other relevant information.
```

## 🔧 Code Contributions

### Architecture Guidelines

Follow the established patterns:

1. **Namespace Structure**
   ```
   MCP\
   ├── Server\     # Server implementations
   ├── Client\     # Client implementations  
   ├── Shared\     # Shared components
   ├── Types\      # Type definitions
   └── Utils\      # Utility classes
   ```

2. **Async Programming**
   - Use Amphp for async operations
   - Always return `Future<T>` for async methods
   - Handle cancellation properly

3. **Error Handling**  
   - Use `McpError` for protocol errors
   - Include proper error codes
   - Provide helpful error messages

4. **Type Safety**
   - Use strict types: `declare(strict_types=1);`
   - Add parameter and return type hints
   - Use enums for constants

### Code Style Guidelines

#### Class Structure
```php
<?php

declare(strict_types=1);

namespace MCP\Server;

use MCP\Types\Implementation;
use MCP\Shared\Transport;

/**
 * Brief class description.
 * 
 * Longer description if needed.
 */
class ExampleClass
{
    // Constants first
    private const DEFAULT_TIMEOUT = 30;
    
    // Properties
    private readonly Implementation $implementation;
    
    /**
     * Constructor with clear documentation.
     */
    public function __construct(
        Implementation $implementation,
        private readonly ?int $timeout = null
    ) {
        $this->implementation = $implementation;
    }
    
    // Public methods
    // Protected methods  
    // Private methods
}
```

#### Method Documentation
```php
/**
 * Register a new tool with the server.
 * 
 * @param string $name Tool identifier
 * @param array<string, mixed> $config Tool configuration
 * @param callable(array<string, mixed>): array<string, mixed> $handler Tool handler
 * @return RegisteredTool Tool registration instance
 * @throws McpError If tool name already exists
 */
public function registerTool(string $name, array $config, callable $handler): RegisteredTool
{
    // Implementation...
}
```

### Testing Guidelines

#### Test Structure
```php
<?php

declare(strict_types=1);

namespace MCP\Tests\Server;

use MCP\Server\McpServer;
use MCP\Types\Implementation;
use PHPUnit\Framework\TestCase;

class McpServerTest extends TestCase
{
    private McpServer $server;
    
    protected function setUp(): void
    {
        $this->server = new McpServer(
            new Implementation('test-server', '1.0.0')
        );
    }
    
    public function testToolRegistration(): void
    {
        $tool = $this->server->registerTool(
            'test-tool',
            ['description' => 'Test tool'],
            fn($params) => ['content' => [['type' => 'text', 'text' => 'test']]]
        );
        
        $this->assertEquals('test-tool', $tool->getName());
    }
}
```

#### Test Categories
- **Unit Tests** - Test individual classes/methods
- **Integration Tests** - Test component interactions  
- **Functional Tests** - Test complete workflows
- **Performance Tests** - Test performance characteristics

### Documentation Guidelines

#### PHPDoc Standards
```php
/**
 * Brief method description (required).
 * 
 * Longer description explaining the method's purpose,
 * behavior, and any important details (optional).
 * 
 * @param Type $param Parameter description
 * @param Type|null $optional Optional parameter description
 * @return Type Return value description  
 * @throws ExceptionType When this exception is thrown
 * 
 * @example
 * ```php
 * $result = $object->method($param, $optional);
 * ```
 */
```

#### README Updates
When adding features, update relevant README sections:

- Installation requirements
- Usage examples  
- API changes
- Breaking changes (in CHANGELOG.md)

## 🧪 Testing

### Running Tests

```bash
# All tests
composer test

# Specific test file
vendor/bin/phpunit tests/Server/McpServerTest.php

# With coverage
composer test-coverage

# Performance tests
composer test-performance
```

### Writing Tests

#### Test Naming
- Test class: `{ClassUnderTest}Test`
- Test method: `test{MethodUnderTest}{Scenario}`
- Example: `testRegisterToolWithValidParameters`

#### Test Structure (AAA Pattern)
```php
public function testFeatureBehavior(): void
{
    // Arrange - Set up test data and dependencies
    $server = new McpServer(new Implementation('test', '1.0.0'));
    $config = ['description' => 'Test tool'];
    
    // Act - Execute the code under test
    $tool = $server->registerTool('test-tool', $config, fn() => []);
    
    // Assert - Verify the expected outcome
    $this->assertEquals('test-tool', $tool->getName());
    $this->assertTrue($tool->isEnabled());
}
```

### Mocking Guidelines

Use Mockery for complex mocking:

```php
public function testClientConnection(): void
{
    $transport = Mockery::mock(Transport::class);
    $transport->shouldReceive('connect')->once()->andReturn(true);
    
    $client = new Client(new Implementation('test', '1.0.0'));
    $result = $client->connect($transport);
    
    $this->assertTrue($result);
}
```

## 📝 Documentation Contributions

### Types of Documentation

1. **API Documentation** - PHPDoc comments in code
2. **User Guides** - How-to guides in `docs/guides/`
3. **Examples** - Working code samples in `docs/examples/`
4. **API Reference** - Generated and manual API docs

### Documentation Standards

- Use clear, concise language
- Include code examples for complex topics
- Test all code examples
- Update table of contents when adding sections
- Follow Markdown best practices

### Building Documentation

```bash
# Install PHPDoc
curl -L https://github.com/phpDocumentor/phpDocumentor/releases/latest/download/phpDocumentor.phar -o phpdoc
chmod +x phpdoc

# Generate API docs
./phpdoc --config=phpdoc.xml

# Preview locally (if using static site generator)
cd docs && python -m http.server 8000
```

## 🚢 Release Process

### Versioning

We follow [Semantic Versioning](https://semver.org/):

- `MAJOR.MINOR.PATCH`
- **MAJOR** - Incompatible API changes
- **MINOR** - New functionality, backwards compatible  
- **PATCH** - Bug fixes, backwards compatible

### Changelog

Update `CHANGELOG.md` for all changes:

```markdown
## [Unreleased]

### Added
- New feature description

### Changed  
- Modified functionality description

### Fixed
- Bug fix description
```

### Release Checklist

- [ ] All tests passing
- [ ] Documentation updated
- [ ] CHANGELOG.md updated  
- [ ] Version bumped in composer.json
- [ ] Git tag created
- [ ] GitHub release created

## 💬 Community Guidelines

### Code of Conduct

- Be respectful and inclusive
- Focus on constructive feedback
- Help others learn and grow
- Maintain a welcoming environment

### Communication Channels

- **GitHub Issues** - Bug reports and feature requests
- **GitHub Discussions** - Questions and general discussion
- **Pull Requests** - Code review and collaboration

### Getting Help

If you need help:

1. Check existing documentation
2. Search closed issues and discussions
3. Ask in GitHub Discussions
4. Provide minimal reproduction examples

## 🙏 Recognition

Contributors are recognized in:

- **README.md** - Contributors section
- **CHANGELOG.md** - Change attribution  
- **GitHub Contributors** - Automatic recognition
- **Release Notes** - Major contribution mentions

## 📚 Resources

### Development Tools
- [PHPStan](https://phpstan.org/) - Static analysis
- [Psalm](https://psalm.dev/) - Type checker
- [PHP CS Fixer](https://cs.symfony.com/) - Code style
- [PHPUnit](https://phpunit.de/) - Testing

### Learning Resources
- [MCP Specification](https://spec.modelcontextprotocol.io/)
- [Amphp Documentation](https://amphp.org/)
- [PSR Standards](https://www.php-fig.org/psr/)
- [PHP 8.1+ Features](https://www.php.net/releases/8.1/)

Thank you for contributing to the PHP MCP SDK! Your contributions help make the project better for everyone. 🎉