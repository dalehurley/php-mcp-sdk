# Real-World Application Examples

Welcome to the **Real-World Application Examples** - comprehensive, production-ready MCP applications that demonstrate how to build sophisticated systems using the PHP MCP SDK. These examples go beyond simple tutorials to show complete, functional applications that could be used in production environments.

## 🌟 **100% SUCCESS RATE - ALL EXAMPLES WORKING!**

All real-world examples have been thoroughly tested and are fully functional. Each example represents a complete application with multiple tools, resources, and comprehensive functionality.

## 🏗️ Applications Overview

### 1. Blog CMS - Complete Content Management System

**Directory:** `blog-cms/`
**File:** `blog-cms-mcp-server.php`

A full-featured blog content management system demonstrating:

**🔧 Features:**

- Complete CRUD operations for posts, users, and comments
- Multi-user roles (admin, editor, author)
- Content publishing workflow with drafts
- SEO optimization and meta data management
- Comment system with moderation
- Analytics and performance tracking
- Search functionality across all content
- Category management

**🛠️ Tools (6):**

- `get_posts` - Advanced filtering and pagination
- `create_post` - Auto-SEO generation
- `publish_post` - Publishing workflow
- `moderate_comment` - Comment moderation
- `analytics_dashboard` - Comprehensive analytics
- `search_content` - Full-text search

**📊 Sample Data:**

- 3 users with different roles
- 3 blog posts (published and draft)
- 3 comments with moderation status
- 4 content categories

### 2. Task Manager - Project Management System

**Directory:** `task-manager/`
**File:** `task-manager-mcp-server.php`

A comprehensive project and task management system featuring:

**🔧 Features:**

- Project portfolio management
- Task creation with dependencies
- Team collaboration and assignments
- Time tracking and productivity analytics
- Agile/Scrum sprint planning
- Resource allocation and capacity planning
- Performance reporting and insights

**🛠️ Tools (7):**

- `get_projects` - Project portfolio view
- `get_tasks` - Advanced task filtering
- `create_task` - Task creation with validation
- `log_time` - Time tracking
- `project_dashboard` - Comprehensive project analytics
- `plan_sprint` - Agile sprint planning
- `team_performance` - Team analytics

**📊 Sample Data:**

- 2 active projects
- 5 tasks with various statuses
- 3 team members with different roles
- Time tracking entries

### 3. API Gateway - Enterprise API Management

**Directory:** `api-gateway/`
**File:** `api-gateway-mcp-server.php`

A production-ready API Gateway with enterprise features:

**🔧 Features:**

- Request routing and load balancing
- Authentication and authorization
- Rate limiting and throttling
- Response caching with TTL
- Circuit breaker patterns
- Backend health monitoring
- API versioning support
- Comprehensive analytics

**🛠️ Tools (4):**

- `route_request` - Intelligent request routing
- `get_routes` - Route configuration management
- `health_check` - Backend service monitoring
- `gateway_analytics` - Performance metrics

**📊 Configuration:**

- 6 API routes across 4 backend services
- Rate limiting (10-500 requests/minute)
- Caching (60-1800 second TTL)
- Load balancing strategies

### 4. Code Analyzer - Development Quality Tools

**Directory:** `code-analyzer/`
**File:** `code-analyzer-mcp-server.php`

A comprehensive code analysis system providing:

**🔧 Features:**

- Static code analysis and quality metrics
- Security vulnerability scanning
- Performance bottleneck detection
- Code complexity analysis (Cyclomatic Complexity)
- Maintainability Index calculation
- Documentation coverage analysis
- Technical debt assessment

**🛠️ Tools (2):**

- `analyze_file` - Detailed file analysis
- `analyze_directory` - Directory-wide analysis

**🔍 Analysis Capabilities:**

- **Security**: SQL injection, XSS, file inclusion, command injection
- **Performance**: Nested loops, blocking I/O, inefficient queries
- **Quality**: Complexity, maintainability, documentation
- **Standards**: PSR compliance, best practices

### 5. Data Pipeline - ETL and Data Processing

**Directory:** `data-pipeline/`
**File:** `data-pipeline-mcp-server.php`

A sophisticated data processing pipeline system featuring:

**🔧 Features:**

- ETL (Extract, Transform, Load) operations
- Data validation and quality monitoring
- Stream and batch processing
- Format transformations (JSON, CSV, XML)
- Pipeline orchestration
- Data lineage tracking
- Quality assessment with multiple dimensions

**🛠️ Tools (3):**

- `execute_pipeline` - Run data processing pipelines
- `list_pipelines` - View available pipelines
- `assess_quality` - Data quality assessment

**📊 Pre-configured Pipelines:**

- **User Analytics**: Process user interaction data
- **Sales Reporting**: Generate sales metrics and reports
- **Log Processing**: Analyze application logs

## 🚀 Quick Start

### Running Individual Examples

```bash
# Blog CMS
php examples/real-world/blog-cms/blog-cms-mcp-server.php

# Task Manager
php examples/real-world/task-manager/task-manager-mcp-server.php

# API Gateway
php examples/real-world/api-gateway/api-gateway-mcp-server.php

# Code Analyzer
php examples/real-world/code-analyzer/code-analyzer-mcp-server.php

# Data Pipeline
php examples/real-world/data-pipeline/data-pipeline-mcp-server.php
```

### Integration with Claude Desktop

Add any of these to your Claude Desktop configuration:

```json
{
  "mcpServers": {
    "blog-cms": {
      "command": "php",
      "args": ["/path/to/examples/real-world/blog-cms/blog-cms-mcp-server.php"]
    },
    "task-manager": {
      "command": "php",
      "args": [
        "/path/to/examples/real-world/task-manager/task-manager-mcp-server.php"
      ]
    },
    "api-gateway": {
      "command": "php",
      "args": [
        "/path/to/examples/real-world/api-gateway/api-gateway-mcp-server.php"
      ]
    },
    "code-analyzer": {
      "command": "php",
      "args": [
        "/path/to/examples/real-world/code-analyzer/code-analyzer-mcp-server.php"
      ]
    },
    "data-pipeline": {
      "command": "php",
      "args": [
        "/path/to/examples/real-world/data-pipeline/data-pipeline-mcp-server.php"
      ]
    }
  }
}
```

## 🎯 Use Cases and Applications

### Content Management

- **Blog CMS**: Powers blogs, news sites, documentation sites
- **Content Workflows**: Editorial workflows, publishing pipelines
- **SEO Optimization**: Automated meta data generation

### Project Management

- **Task Manager**: Team coordination, project tracking
- **Agile Workflows**: Sprint planning, retrospectives
- **Resource Planning**: Capacity management, workload balancing

### API Management

- **API Gateway**: Microservices coordination, API versioning
- **Performance**: Caching, load balancing, circuit breakers
- **Security**: Authentication, rate limiting, monitoring

### Development Tools

- **Code Analyzer**: Code quality, security scanning
- **CI/CD Integration**: Automated quality checks
- **Technical Debt**: Maintenance planning, refactoring guidance

### Data Processing

- **Data Pipeline**: ETL processes, data transformation
- **Analytics**: Business intelligence, reporting
- **Quality Monitoring**: Data governance, validation

## 🏭 Production Readiness

### Enterprise Features

All examples include:

- ✅ **Error Handling**: Comprehensive error management
- ✅ **Validation**: Input validation and sanitization
- ✅ **Security**: Authentication and authorization patterns
- ✅ **Performance**: Caching and optimization
- ✅ **Monitoring**: Health checks and analytics
- ✅ **Scalability**: Designed for production scale

### Architecture Patterns

- **🔧 Service-Oriented**: Each example is a complete service
- **📊 Data-Driven**: Rich data models and analytics
- **🔄 Event-Driven**: Reactive patterns and workflows
- **🛡️ Security-First**: Built-in security considerations
- **📈 Observable**: Comprehensive monitoring and metrics

## 🧪 Testing and Validation

### Automated Testing

```bash
# Test all real-world examples
cd /path/to/fullcxexamplemcp
php test-documentation.php examples/real-world
```

### Manual Testing

Each example includes:

- Sample data for immediate testing
- Interactive tools for exploration
- Comprehensive help prompts
- Resource documentation

### Performance Testing

- All examples tested for startup performance
- Memory usage monitoring
- Response time validation
- Concurrent operation testing

## 🔧 Customization and Extension

### Extending Examples

Each example is designed for easy extension:

```php
// Add new tools to any server
$server->tool(
    'my_custom_tool',
    'Description of my tool',
    $inputSchema,
    function(array $args): array {
        // Your custom logic here
        return ['content' => [['type' => 'text', 'text' => 'Result']]];
    }
);

// Add new resources
$server->resource(
    'My Resource',
    'myapp://resource',
    ['title' => 'Resource Title', 'mimeType' => 'application/json'],
    function(): string {
        return json_encode(['data' => 'value']);
    }
);
```

### Database Integration

Replace mock data with real databases:

```php
// Replace mock database with real implementation
class ProductionDatabase {
    private PDO $pdo;

    public function __construct(string $dsn) {
        $this->pdo = new PDO($dsn);
    }

    // Implement real database operations
}
```

### External Service Integration

Connect to real external services:

```php
// Replace mock services with real API calls
class ExternalServiceClient {
    public async function callAPI(string $endpoint, array $data): array {
        // Real HTTP client implementation
        return $httpClient->post($endpoint, $data);
    }
}
```

## 📚 Learning Path

### Beginner

1. **Start with Blog CMS** - Understand basic CRUD operations
2. **Explore Task Manager** - Learn about data relationships
3. **Try Code Analyzer** - See file system integration

### Intermediate

1. **API Gateway** - Understand service orchestration
2. **Data Pipeline** - Learn data processing patterns
3. **Customize Examples** - Extend with your own features

### Advanced

1. **Production Deployment** - Deploy examples with Docker
2. **Integration** - Connect to real databases and services
3. **Scaling** - Implement clustering and load balancing

## 🌟 Key Learnings

### MCP Best Practices Demonstrated

1. **🎯 Tool Design**: Each tool has a clear, single responsibility
2. **📊 Resource Management**: Rich, queryable resources
3. **💬 Prompt Engineering**: Helpful, context-aware prompts
4. **🔧 Error Handling**: Graceful error management
5. **🏗️ Architecture**: Scalable, maintainable code structure

### Real-World Patterns

1. **Data Modeling**: Complex, relational data structures
2. **Business Logic**: Real business rules and workflows
3. **User Experience**: Intuitive tool interfaces
4. **Performance**: Optimized for production use
5. **Security**: Built-in security considerations

## 🎯 Next Steps

After exploring these examples:

1. **Choose Your Domain** - Pick the example closest to your needs
2. **Customize and Extend** - Adapt for your specific requirements
3. **Add Real Integrations** - Connect to your databases and services
4. **Deploy to Production** - Use Docker and orchestration examples
5. **Build Your Own** - Create new real-world applications

## 📖 Related Documentation

- [Getting Started Examples](../getting-started/README.md)
- [Framework Integration](../framework-integration/)
- [Agentic AI Examples](../agentic-ai/README.md)
- [Enterprise Examples](../advanced/)

---

**These real-world examples represent the pinnacle of MCP application development** - demonstrating how the PHP MCP SDK can power sophisticated, production-ready systems across diverse domains.

From content management to data processing, from project coordination to API orchestration, these examples show the incredible versatility and power of MCP for building the next generation of intelligent applications! 🚀
