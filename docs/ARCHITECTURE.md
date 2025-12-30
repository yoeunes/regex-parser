# Architecture and Design Guide

> **Understanding how RegexParser works internally.**

---

## 🎯 What is RegexParser?

RegexParser is a PHP 8.2+ library that converts PCRE regex patterns into a structured **Abstract Syntax Tree (AST)**. This enables:

- ✅ Pattern validation and error reporting
- ✅ ReDoS vulnerability detection
- ✅ Human-readable explanations
- ✅ Pattern optimization and transformation
- ✅ CI/CD linting at scale

```
┌─────────────────────────────────────────────────────────────────────┐
│                         Your Pattern                                │
│                         "/^hello/i"                                 │
└─────────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────────┐
│                         Lexer                                       │
│              Tokenizes pattern into TokenStream                     │
│              Tokens: WORD, QUANTIFIER, ANCHOR, etc.                 │
└─────────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────────┐
│                         Parser                                      │
│              Builds typed AST from TokenStream                      │
│              Recursive descent parser                               │
└─────────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────────┐
│                         AST                                         │
│              RegexNode                                              │
│              └── SequenceNode                                       │
│                  ├── AnchorNode (^)                                 │
│                  ├── LiteralNode ("hello")                          │
│                  └── AnchorNode ($)                                 │
└─────────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      Visitors                                       │
│              ┌────────────────┬─────────────────┬───────────────┐   │
│              │ Validator      │ Explainer       │ Optimizer     │   │
│              │ Linter         │ Highlighter     │ Compiler      │   │
│              │ ReDoS Analyzer │ Diagram         │ ...           │   │
│              └────────────────┴─────────────────┴───────────────┘   │
└─────────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────────┐
│                     Output                                          │
│              Validation results, explanations,                      │
│              optimized patterns, lint reports                       │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 🎨 Design Principles

### 1. Precise Error Reporting

RegexParser uses **byte offsets** throughout, making error messages accurate for IDE integration:

```php
$pattern = '/(?<=a+)b/';  // Variable-length lookbehind

$result = $regex->validate($pattern);

echo $result->getErrorMessage();
// "Variable-length lookbehind is not supported in PCRE."

echo $result->getCaretSnippet();
/*
Line 1: (?<=a+)b
            ^
*/
```

### 2. Separation of Concerns

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│   Nodes     │ ──▶ │  Visitors   │ ──▶ │  Results    │
│ (immutable) │     │ (behavior)  │     │ (output)    │
└─────────────┘     └─────────────┘     └─────────────┘
```

- **Nodes** are data holders - they never change
- **Visitors** implement behavior - easy to add new ones
- **Results** are value objects - easy to test

### 3. Performance at Scale

The CLI linter uses a **MapReduce-style** architecture for scanning large codebases:

```
Phase 1: Map (Extract)
┌──────────────┐  ┌──────────────┐  ┌──────────────┐
│   Worker 1   │  │   Worker 2   │  │   Worker N   │
│ Extracts     │  │ Extracts     │  │ Extracts     │
│ patterns     │  │ patterns     │  │ patterns     │
└──────┬───────┘  └──────┬───────┘  └──────┬───────┘
       │                 │                 │
       └─────────────────┼─────────────────┘
                         ▼
              ┌─────────────────────┐
              │   Pattern Queue     │
              └──────────┬──────────┘
                         │
                         ▼
Phase 2: Reduce (Analyze)
       ┌────────────────┼────────────────┐
       ▼                ▼                ▼
┌──────────────┐ ┌──────────────┐ ┌──────────────┐
│   Analyze    │ │   Analyze    │ │   Analyze    │
│   Chunk 1    │ │   Chunk 2    │ │   Chunk N    │
└──────┬───────┘ └──────┬───────┘ └──────┬───────┘
       │                │                │
       └────────────────┼────────────────┘
                         ▼
              ┌───────────────────────┐
              │   Final Report        │
              └───────────────────────┘
```

**Benefits:**
- Memory stays low (workers have isolated heaps)
- Parallel execution (uses `pcntl_fork` when available)
- Failure isolation (one worker crash doesn't corrupt results)

---

## 🔍 Component Details

### 1. Lexer (`src/Lexer.php`)

The lexer tokenizes the pattern string:

```php
$lexer = new Lexer(\PHP_VERSION_ID);
$tokens = $lexer->tokenize($pattern, $flags);
```

**Output:** `TokenStream` with tokens and byte offsets.

**Token Types:**
- `T_WORD` - Word characters
- `T_QUANTIFIER` - `+`, `*`, `?`, `{m,n}`
- `T_ANCHOR` - `^`, `$`
- `T_GROUP_OPEN` - `(`
- `T_GROUP_CLOSE` - `)`
- `T_CHAR_CLASS_OPEN` - `[`
- `T_LITERAL` - Escaped characters
- And many more...

### 2. Parser (`src/Parser.php`)

The parser builds the AST using recursive descent:

```php
$parser = new Parser(1024, \PHP_VERSION_ID);
$ast = $parser->parse($tokens, $flags, '/', $patternLength);
```

**Output:** `RegexNode` (root of AST)

**Parser Structure:**
```
Parser
├── parse() - Entry point
├── parseSequence() - Parse consecutive items
├── parseGroup() - Parse parentheses
├── parseQuantifier() - Parse repetition
├── parseCharacterClass() - Parse [...]
├── parseAssertion() - Parse lookarounds
└── parseLiteral() - Parse escaped chars
```

### 3. AST Nodes (`src/Node/`)

All nodes implement `NodeInterface` and extend `AbstractNode`:

```php
interface NodeInterface
{
    public function accept(NodeVisitorInterface $visitor): mixed;
}

abstract readonly class AbstractNode implements NodeInterface
{
    public function __construct(
        public int $startPosition,
        public int $endPosition
    ) {}
}
```

**Common Node Types:**

| Node              | Purpose         | Example            |
|-------------------|-----------------|--------------------|
| `RegexNode`       | Root of AST     | `/pattern/flags`   |
| `SequenceNode`    | Ordered list    | `abc`              |
| `AlternationNode` | Alternatives    | `a\|b\|c`          |
| `GroupNode`       | Grouping        | `(...)`, `(?=...)` |
| `QuantifierNode`  | Repetition      | `a+`, `a{2,4}`     |
| `LiteralNode`     | Literal text    | `hello`            |
| `CharClassNode`   | Character class | `[a-z]`            |
| `AnchorNode`      | Position anchor | `^`, `$`           |

### 4. Visitors (`src/NodeVisitor/`)

Visitors traverse the AST and perform operations:

```php
interface NodeVisitorInterface
{
    public function visitRegex(RegexNode $node): mixed;
    public function visitSequence(SequenceNode $node): mixed;
    // ... visit methods for each node type
}
```

**Built-in Visitors:**

| Visitor                     | Purpose                             |
|-----------------------------|-------------------------------------|
| `CompilerNodeVisitor`       | Convert AST back to string          |
| `ExplainNodeVisitor`        | Generate human-readable explanation |
| `ValidatorNodeVisitor`      | Validate pattern structure          |
| `LinterNodeVisitor`         | Find code quality issues            |
| `ReDoSAnalyzer`             | Detect catastrophic backtracking    |
| `OptimizerNodeVisitor`      | Optimize pattern                    |
| `ConsoleHighlighterVisitor` | Colorize for console                |
| `HtmlHighlighterVisitor`    | Colorize for HTML                   |

---

## 🔄 AST Traversal: The Visitor Pattern

The visitor pattern enables **extensible analysis** without modifying nodes:

```php
// Create AST
$ast = $regex->parse('/hello|world/');

// Apply visitor
$explanation = $ast->accept(new ExplainNodeVisitor());
echo $explanation;
/*
Output:
Literal 'hello' or literal 'world'
*/
```

### How It Works

```
$ast->accept($visitor)
        │
        ▼
RegexNode::accept($visitor)
        │
        ▼
$visitor->visitRegex($this)
        │
        ▼
$this->pattern->accept($visitor)  // Delegate to child
        │
        ▼
SequenceNode::accept($visitor)
        │
        ▼
$visitor->visitSequence($this)
        │
        ▼
foreach ($this->children as $child) {
    $child->accept($visitor)  // Visit each child
}
```

---

## ⚡ Performance Optimizations

### 1. Precompiled Patterns

The lexer caches compiled regex patterns:

```php
// First call - compiles pattern
$lexer->tokenize($pattern, $flags);

// Subsequent calls - uses cached pattern
$lexer->tokenize($pattern, $flags);
```

### 2. AST Caching

Parsed patterns can be cached:

```php
$regex = Regex::create(['cache' => '/path/to/cache']);

// First parse - builds AST
$ast1 = $regex->parse('/pattern/');

// Second parse - loads from cache
$ast2 = $regex->parse('/pattern/');
```

### 3. Parallel Linting

```php
// Uses pcntl_fork() when available
// Each worker analyzes a chunk of patterns
// Results aggregated in parent process
```

---

## 📊 Data Flow Examples

### Validating a Pattern

```
Input: "/(?<=a+)b/"
       │
       ▼
Lexer: Tokenize
       Tokens: [GROUP_OPEN, LOOKBEHIND, LITERAL, GROUP_CLOSE, LITERAL]
       │
       ▼
Parser: Build AST
       RegexNode
       └── AssertionNode (lookbehind)
       │
       ▼
ValidatorVisitor: Check structure
       Finds: Variable-length lookbehind
       │
       ▼
Output: ValidationResult
        - isValid: false
        - errorMessage: "Variable-length lookbehind..."
        - position: 0
```

### Explaining a Pattern

```
Input: "/\d{4}-\d{2}-\d{2}/"
       │
       ▼
Lexer + Parser → AST
       │
       ▼
ExplainNodeVisitor: Traverse and explain
       "Four digits, hyphen, two digits, hyphen, two digits"
       │
       ▼
Output: String explanation
```

### ReDoS Analysis

```
Input: "/(a+)+$/"
       │
       ▼
Lexer + Parser → AST
       │
       ▼
ReDoSAnalyzer: Check for patterns
       - Nested quantifier found: (a+)+
       - Score: 10 (CRITICAL)
       │
       ▼
Output: ReDoSAnalysis
        - severity: CRITICAL
        - score: 10
        - recommendations: ["Use atomic groups", "Simplify pattern"]
```

---

## 🎓 Learning Path

### For Users
1. **[Tutorial](../tutorial/README.md)** - Learn regex basics
2. **[Quick Start](../QUICK_START.md)** - Get productive quickly
3. **CLI Guide** - Use the command-line tool

### For Integrators
1. **This guide** - Understand the architecture
2. **[API Reference](../reference/api.md)** - Complete API docs
3. **[Architecture](./ARCHITECTURE.md)** - Design decisions

### For Contributors
1. **[Extending Guide](./EXTENDING_GUIDE.md)** - Add new features
2. **AST Traversal** - Visitor pattern deep dive
3. **Source Code** - Read `src/Parser.php`, `src/NodeVisitor/`

---

## 📚 Related Documentation

- **[AST Nodes](../nodes/README.md)** - Complete node reference
- **[Visitors](../visitors/README.md)** - Visitor implementation guide
- **[AST Traversal](./AST_TRAVERSAL.md)** - Traversal design details
- **[Extending Guide](./EXTENDING_GUIDE.md)** - Adding new features

---

## 🆘 Common Questions

### "Why not use a generated parser?"

Hand-written parsers provide:
- **Better error messages** with exact positions
- **Easier debugging** - code is readable PHP
- **No dependencies** - no generated artifacts

### "Why the visitor pattern?"

Separates **data** (AST nodes) from **behavior** (analysis). Adding a new analysis doesn't require changing any node classes.

### "Is it fast enough for large codebases?"

Yes! The MapReduce-style architecture with parallel workers scales to thousands of files. Memory stays low because workers have isolated heaps.

---

**Next:** [AST Traversal Design](design/AST_TRAVERSAL.md)

---

Previous: [Cookbook](COOKBOOK.md) | Next: [Maintainers Guide](MAINTAINERS_GUIDE.md)
