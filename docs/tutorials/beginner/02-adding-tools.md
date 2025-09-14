# Tutorial 2: Adding Tools and Functionality

Now that you've built your first MCP server, let's expand it with multiple tools and learn how to create more sophisticated functionality. You'll transform your simple calculator into a powerful multi-tool server.

**⏱️ Estimated Time:** 20 minutes  
**📋 Prerequisites:** Completed [Tutorial 1](01-your-first-mcp-server.md)  
**🎯 Goal:** Build a multi-tool calculator with advanced features

## 🎯 What You'll Build

Expand your calculator server with:

- ✅ **Multiple math operations** (add, subtract, multiply, divide, power, sqrt)
- ✅ **Input validation** and error handling
- ✅ **Memory functions** for storing calculations
- ✅ **Calculation history** tracking
- ✅ **Help system** for users

## 🔧 Step 1: Add More Math Operations (5 minutes)

Let's add subtract, multiply, and divide tools to your calculator. Add these to your `calculator-server.php`:

```php
// Subtraction tool
$server->tool(
    'subtract',
    'Subtract second number from first number',
    [
        'type' => 'object',
        'properties' => [
            'a' => ['type' => 'number', 'description' => 'First number (minuend)'],
            'b' => ['type' => 'number', 'description' => 'Second number (subtrahend)']
        ],
        'required' => ['a', 'b']
    ],
    function (array $args): array {
        $result = $args['a'] - $args['b'];
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "{$args['a']} - {$args['b']} = {$result}"
                ]
            ]
        ];
    }
);

// Multiplication tool
$server->tool(
    'multiply',
    'Multiply two numbers together',
    [
        'type' => 'object',
        'properties' => [
            'a' => ['type' => 'number', 'description' => 'First number'],
            'b' => ['type' => 'number', 'description' => 'Second number']
        ],
        'required' => ['a', 'b']
    ],
    function (array $args): array {
        $result = $args['a'] * $args['b'];
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "{$args['a']} × {$args['b']} = {$result}"
                ]
            ]
        ];
    }
);

// Division tool with error handling
$server->tool(
    'divide',
    'Divide first number by second number',
    [
        'type' => 'object',
        'properties' => [
            'a' => ['type' => 'number', 'description' => 'Dividend (number to be divided)'],
            'b' => ['type' => 'number', 'description' => 'Divisor (number to divide by)']
        ],
        'required' => ['a', 'b']
    ],
    function (array $args): array {
        // Error handling for division by zero
        if ($args['b'] == 0) {
            throw new McpError(
                code: -32602,
                message: 'Division by zero is not allowed'
            );
        }

        $result = $args['a'] / $args['b'];
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "{$args['a']} ÷ {$args['b']} = {$result}"
                ]
            ]
        ];
    }
);
```

✅ **Checkpoint:** Your calculator now has four basic operations with proper error handling.

## 🔧 Step 2: Add Advanced Math Functions (5 minutes)

Let's add power and square root functions:

```php
// Power function
$server->tool(
    'power',
    'Raise first number to the power of second number',
    [
        'type' => 'object',
        'properties' => [
            'base' => ['type' => 'number', 'description' => 'Base number'],
            'exponent' => ['type' => 'number', 'description' => 'Exponent']
        ],
        'required' => ['base', 'exponent']
    ],
    function (array $args): array {
        $result = pow($args['base'], $args['exponent']);
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "{$args['base']}^{$args['exponent']} = {$result}"
                ]
            ]
        ];
    }
);

// Square root function
$server->tool(
    'sqrt',
    'Calculate square root of a number',
    [
        'type' => 'object',
        'properties' => [
            'number' => [
                'type' => 'number',
                'description' => 'Number to find square root of',
                'minimum' => 0  // Prevent negative numbers
            ]
        ],
        'required' => ['number']
    ],
    function (array $args): array {
        if ($args['number'] < 0) {
            throw new McpError(
                code: -32602,
                message: 'Cannot calculate square root of negative number'
            );
        }

        $result = sqrt($args['number']);
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "√{$args['number']} = {$result}"
                ]
            ]
        ];
    }
);
```

✅ **Checkpoint:** Your calculator now has six mathematical operations with validation.

## 🧠 Step 3: Add Memory Functions (5 minutes)

Let's add calculator memory functionality. First, create a memory variable at the top of your file:

```php
// Add this near the top, after the server creation
$calculatorMemory = 0;
```

Now add memory tools:

```php
// Memory store
$server->tool(
    'memory_store',
    'Store a number in calculator memory',
    [
        'type' => 'object',
        'properties' => [
            'value' => ['type' => 'number', 'description' => 'Number to store in memory']
        ],
        'required' => ['value']
    ],
    function (array $args) use (&$calculatorMemory): array {
        $calculatorMemory = $args['value'];
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "Memory stored: {$calculatorMemory}"
                ]
            ]
        ];
    }
);

// Memory recall
$server->tool(
    'memory_recall',
    'Recall the number stored in calculator memory',
    [
        'type' => 'object',
        'properties' => []
    ],
    function (array $args) use (&$calculatorMemory): array {
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "Memory contains: {$calculatorMemory}"
                ]
            ]
        ];
    }
);

// Memory clear
$server->tool(
    'memory_clear',
    'Clear calculator memory',
    [
        'type' => 'object',
        'properties' => []
    ],
    function (array $args) use (&$calculatorMemory): array {
        $calculatorMemory = 0;
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "Memory cleared"
                ]
            ]
        ];
    }
);
```

✅ **Checkpoint:** Your calculator now has memory functions for storing values.

## 📊 Step 4: Add Calculation History (3 minutes)

Let's track calculation history. Add this near the top:

```php
// Add this with the memory variable
$calculationHistory = [];
```

Now modify your existing tools to record history. Here's an example for the add tool:

```php
// Update your add tool to include history tracking
$server->tool(
    'add',
    'Add two numbers together',
    [
        'type' => 'object',
        'properties' => [
            'a' => ['type' => 'number', 'description' => 'First number'],
            'b' => ['type' => 'number', 'description' => 'Second number']
        ],
        'required' => ['a', 'b']
    ],
    function (array $args) use (&$calculationHistory): array {
        $result = $args['a'] + $args['b'];

        // Record in history
        $calculationHistory[] = [
            'operation' => 'add',
            'inputs' => $args,
            'result' => $result,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "{$args['a']} + {$args['b']} = {$result}"
                ]
            ]
        ];
    }
);

// Add a tool to view history
$server->tool(
    'get_history',
    'Get calculation history',
    [
        'type' => 'object',
        'properties' => [
            'limit' => [
                'type' => 'integer',
                'description' => 'Maximum number of history entries to return',
                'default' => 10,
                'minimum' => 1,
                'maximum' => 100
            ]
        ]
    ],
    function (array $args) use (&$calculationHistory): array {
        $limit = $args['limit'] ?? 10;
        $recentHistory = array_slice($calculationHistory, -$limit);

        if (empty($recentHistory)) {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'No calculations in history yet.'
                    ]
                ]
            ];
        }

        $historyText = "📊 Calculation History (last {$limit}):\n\n";
        foreach ($recentHistory as $entry) {
            $historyText .= "• {$entry['timestamp']}: {$entry['operation']} → {$entry['result']}\n";
        }

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $historyText
                ]
            ]
        ];
    }
);
```

✅ **Checkpoint:** Your calculator now tracks and displays calculation history.

## 🧪 Step 5: Test Your Enhanced Calculator (2 minutes)

### Test All Functions

```bash
# Run your enhanced calculator
php calculator-server.php
```

### Test with Claude Desktop

Update your Claude Desktop configuration:

```json
{
  "mcpServers": {
    "enhanced-calculator": {
      "command": "php",
      "args": ["/path/to/your/calculator-server.php"]
    }
  }
}
```

### Try These Commands with Claude

1. **Basic Math:** "Calculate 15 + 27"
2. **Advanced Math:** "What's 2 to the power of 8?"
3. **Error Handling:** "Divide 10 by 0" (should handle gracefully)
4. **Memory Functions:** "Store 42 in memory, then recall it"
5. **History:** "Show me my calculation history"

## 🎓 Key Concepts Learned

### 1. Tool Composition

You learned how to build servers with multiple related tools that work together.

### 2. State Management

You implemented memory and history features that maintain state across tool calls.

### 3. Error Handling

You added proper error handling for edge cases like division by zero.

### 4. Input Validation

You used JSON Schema features like `minimum` to validate inputs.

### 5. User Experience

You created tools that provide helpful, formatted output for users.

## 💡 Challenge Exercises

Before moving to the next tutorial, try these challenges:

### Challenge 1: Add More Functions

Add these mathematical functions:

- `factorial` - Calculate factorial of a number
- `percentage` - Calculate percentage (e.g., 20% of 150)
- `average` - Calculate average of an array of numbers

### Challenge 2: Enhanced Memory

Improve the memory system:

- Support multiple memory slots (M1, M2, M3)
- Add memory arithmetic (M+ to add to memory)
- Implement memory exchange functions

### Challenge 3: Advanced History

Enhance the history system:

- Add search functionality to find specific calculations
- Implement history export (as JSON or CSV)
- Add statistics (most used operation, average result, etc.)

## 🔮 What's Next?

You now have a sophisticated calculator with multiple tools. In the next tutorial, you'll learn about **resources** - how to provide AI models with access to data and information.

**Ready for more?** → [Tutorial 3: Working with Resources](03-working-with-resources.md)

## 📚 Related Resources

- [Tools Guide](../../guides/server-development/tools-guide.md) - Deep dive into tool development
- [Calculator Example](../../../examples/getting-started/basic-calculator.php) - Complete calculator implementation
- [Error Handling Guide](../../guides/client-development/error-handling.md) - Advanced error patterns

---

🎉 **Excellent work!** You've learned how to build multi-tool MCP servers with state management and error handling. These patterns will serve you well as you build more complex applications!
