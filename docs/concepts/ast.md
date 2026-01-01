# 🌲 What is an AST?

**AST** stands for **Abstract Syntax Tree** - it's a structured representation of your regex pattern that makes it easier to analyze and understand.

## 🎯 Simple Explanation

Imagine you have a regex pattern like `/^hello\d+$/`. To a computer, this is just a string. But to understand what it means, we need to break it down:

```
String: "/^hello\d+$"

AST Structure:
RegexNode
├── SequenceNode
│   ├── AnchorNode (^")
│   ├── LiteralNode ("hello")
│   ├── QuantifierNode (+)
│   │   └── ShorthandClassNode (\d)
│   └── AnchorNode ($)
```

## 🔍 Why Use an AST?

### Before AST (String-based Analysis)

```php
// Old way: String manipulation
$pattern = '/^hello\d+$/';
if (strpos($pattern, 'hello') !== false) {
    // This is unreliable - what if 'hello' is escaped?
}
```

### After AST (Structured Analysis)

```php
// New way: Precise analysis
$ast = Regex::create()->parse('/^hello\d+$/');
$sequence = $ast->pattern; // Exact structure known
$literal = $sequence->children[1]; // The "hello" literal
```

## 🧩 AST Components

### Root Node
- **RegexNode**: Contains the entire pattern and flags

### Pattern Nodes
- **SequenceNode**: Contains ordered elements (e.g., `hello` then `\d+`)
- **AlternationNode**: Contains choices (e.g., `a|b`)
- **GroupNode**: Contains sub-patterns (e.g., `(hello)`)

### Element Nodes
- **LiteralNode**: Fixed text (e.g., `hello`)
- **ShorthandClassNode**: Character classes (e.g., `\d`, `\w`)
- **CharacterClassNode**: Custom character sets (e.g., `[a-z]`)
- **QuantifierNode**: Repetition (e.g., `+`, `*`, `{2,4}`)
- **AnchorNode**: Position markers (e.g., `^`, `$`)

## 🎨 Visualizing ASTs

Use the CLI to see the AST structure:

```bash
# Show AST diagram
bin/regex diagram '/^hello\d+$/'

# Parse and analyze
bin/regex parse '/^hello\d+$/' --ast
```

## 💡 Real-world Benefits

### 1. Precise Error Reporting
```php
// Exact error location
try {
    $ast = Regex::create()->parse('/[unclosed/');
} catch (ParserException $e) {
    echo "Error at position: " . $e->getPosition();
    echo "Snippet: " . $e->getSnippet();
}
```

### 2. Pattern Transformation
```php
// Safely modify patterns
$ast = Regex::create()->parse('/\d{3}-\d{4}/');
// You can now manipulate the AST nodes precisely
```

### 3. Complex Analysis
```php
// Detect ReDoS vulnerabilities
$analysis = Regex::create()->redos('/(a+)+$/');
// AST allows deep structural analysis
```

## 🔗 Related Concepts

- **[Understanding Visitors](visitors.md)** - How visitors process ASTs
- **[Architecture](../ARCHITECTURE.md)** - How RegexParser builds ASTs
- **[Nodes Reference](../nodes/README.md)** - Complete list of node types

## 📚 Further Reading

- [RegexParser Architecture](../ARCHITECTURE.md) - Technical deep dive
- [AST Traversal Design](../design/AST_TRAVERSAL.md) - How trees are processed
- [Nodes Reference](../nodes/README.md) - All available node types

---

📖 **Previous**: [Concepts Home](README.md) | 🚀 **Next**: [Understanding Visitors](visitors.md)