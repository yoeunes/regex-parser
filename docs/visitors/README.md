# AST Visitor Reference

Visitors are the algorithms that process the AST. They implement all the interesting behaviors — validation, optimization, explanation, visualization, and more. This reference documents every built-in visitor and shows how to build custom ones.

## How Visitors Work

```
┌─────────────────────────────────────────────────────────────┐
│                    VISITOR PATTERN                          │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  $ast = $regex->parse('/foo|bar/');                         │
│                                                             │
│  $ast->accept(new ExplainNodeVisitor());                    │
│           │                                                 │
│           ▼                                                 │
│  ┌─────────────────────────────────────────────────────┐    │
│  │ RegexNode::accept($visitor)                         │    │
│  │   └─► $visitor->visitRegexNode($this)               │    │
│  │       └─► $this->pattern->accept($visitor)          │    │
│  │           └─► SequenceNode::accept($visitor)        │    │
│  │               └─► AlternationNode::accept(...)      │    │
│  │                   └─► ...                           │    │
│  └─────────────────────────────────────────────────────┘    │
│                                                             │
│  Result: Visitor processes each node type appropriately     │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Base Classes and Interfaces

### NodeVisitorInterface

**Purpose:** The contract that all visitors must implement. Defines a `visitX()` method for each node type.

**Usage:** Implement this interface when building a custom visitor from scratch.

```php
use RegexParser\NodeVisitor\NodeVisitorInterface;

class MyVisitor implements NodeVisitorInterface
{
    public function visitRegexNode(Node\RegexNode $node): mixed { /* ... */ }
    public function visitSequenceNode(Node\SequenceNode $node): mixed { /* ... */ }
    public function visitAlternationNode(Node\AlternationNode $node): mixed { /* ... */ }
    // ... one method for each node type
}
```

---

### AbstractNodeVisitor

**Purpose:** Convenience base class that provides default implementations for all visitor methods. Override only the methods you need.

**Usage:** Extend this class when you only need to handle a few node types.

```php
use RegexParser\NodeVisitor\AbstractNodeVisitor;

class LiteralCollector extends AbstractNodeVisitor
{
    private array $literals = [];

    public function visitLiteralNode(Node\LiteralNode $node): void
    {
        $this->literals[] = $node->value;
    }

    public function getLiterals(): array
    {
        return $this->literals;
    }
}

// Usage
$ast = Regex::create()->parse('/hello world/');
$visitor = new LiteralCollector();
$ast->accept($visitor);

print_r($visitor->getLiterals());
// Array ( [0] => hello [1] => world )
```

---

## Compilation and Transformation Visitors

### CompilerNodeVisitor

**Purpose:** Converts the AST back into a PCRE string. Useful for round-tripping or pattern normalization.

```php
use RegexParser\Regex;
use RegexParser\NodeVisitor\CompilerNodeVisitor;

$ast = Regex::create()->parse('/foo/i');

// Compile back to string
$pattern = $ast->accept(new CompilerNodeVisitor());
echo $pattern;  // '/foo/i'
```

**Use Cases:**
- Normalize patterns (remove unnecessary whitespace, standardize escapes)
- Round-trip parsing and compilation
- Transform patterns programmatically

---

### OptimizerNodeVisitor

**Purpose:** Applies safe optimizations to make patterns more efficient without changing behavior.

**Optimizations Applied:**

| Before   | After | Why                  |
|----------|-------|----------------------|
| `[0-9]`  | `\d`  | Shorthand is faster  |
| `(?:a)`  | `a`   | Unnecessary group    |
| `a{1}`   | `a`   | Redundant quantifier |
| `\x{61}` | `a`   | Unnecessary escape   |

```php
use RegexParser\Regex;
use RegexParser\NodeVisitor\OptimizerNodeVisitor;

$ast = Regex::create()->parse('/(?:foo)/');
$optimized = $ast->accept(new OptimizerNodeVisitor());

$compiler = new CompilerNodeVisitor();
echo $compiler->visit($optimized);  // '/foo/'
```

---

### ModernizerNodeVisitor

**Purpose:** Converts legacy or verbose syntax to modern equivalents.

**Transformations:**

| Before         | After             |
|----------------|-------------------|
| `(?i)foo(?-i)` | `(?i:foo)`        |
| `(?:foo)`      | `foo` (when safe) |
| `\0`           | `\x{00}`          |

```php
use RegexParser\Regex;
use RegexParser\NodeVisitor\ModernizerNodeVisitor;

$ast = Regex::create()->parse('/(?i)foo/');
$modernized = $ast->accept(new ModernizerNodeVisitor());

$compiler = new CompilerNodeVisitor();
echo $compiler->visit($modernized);  // Modernized version
```

---

## Validation and Linting Visitors

### ValidatorNodeVisitor

**Purpose:** Performs semantic validation of the pattern. Used internally by `Regex::validate()`.

**Checks Performed:**
- Valid backreference targets
- Lookbehind length constraints
- Balanced groups
- Valid group numbers and names

```php
use RegexParser\Regex;
use RegexParser\NodeVisitor\ValidatorNodeVisitor;

$ast = Regex::create()->parse('/\1(foo)/');  // Invalid: \1 before capture
$result = $ast->accept(new ValidatorNodeVisitor());

echo $result->isValid();      // false
echo count($result->getProblems());
```

---

### LinterNodeVisitor

**Purpose:** Checks for performance issues, anti-patterns, and readability problems. Used by CLI linter and PHPStan rule.

**Linting Rules:**

| Rule                   | Description               | Severity |
|------------------------|---------------------------|----------|
| `PossessiveQuantifier` | Use possessive quantifier | warning  |
| `UnnecessaryGroup`     | Remove unnecessary group  | info     |
| `AmbiguousEscape`      | Clarify ambiguous escape  | warning  |
| `ComplexPattern`       | Pattern is complex        | info     |

```php
use RegexParser\Regex;
use RegexParser\NodeVisitor\LinterNodeVisitor;

$ast = Regex::create()->parse('/(a+)+b/');  // ReDoS vulnerable
$result = $ast->accept(new LinterNodeVisitor());

foreach ($result->getIssues() as $issue) {
    echo $issue->getMessage() . "\n";
    // "Pattern may be vulnerable to ReDoS"
}
```

---

### ReDoSAnalyzerNodeVisitor (Internal)

**Purpose:** Internal visitor used by `Regex::redos()` to classify ReDoS risk.

**Risk Levels:**

| Level      | Meaning                     | Action                 |
|------------|-----------------------------|------------------------|
| `safe`     | No exponential backtracking | Accept pattern         |
| `low`      | Minimal risk                | Accept with monitoring |
| `medium`   | Requires specific input     | Consider refactoring   |
| `critical` | Easily exploitable          | Reject pattern         |

```php
use RegexParser\Regex;

$analysis = Regex::create()->redos('/(a+)+b/');
echo $analysis->severity->value;     // 'critical'
echo $analysis->confidence->value;   // 'high'
```

---

### ComplexityScoreNodeVisitor

**Purpose:** Returns a numeric complexity score for a pattern. Useful for CI quality gates.

```php
use RegexParser\Regex;
use RegexParser\NodeVisitor\ComplexityScoreNodeVisitor;

$ast = Regex::create()->parse('/^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/');
$score = $ast->accept(new ComplexityScoreNodeVisitor());

echo $score;  // e.g., 42
```

**Interpretation:**

| Score  | Complexity   |
|--------|--------------|
| 0-20   | Simple       |
| 21-50  | Moderate     |
| 51-100 | Complex      |
| 100+   | Very Complex |

---

### MetricsNodeVisitor

**Purpose:** Collects various metrics about the pattern structure.

```php
use RegexParser\Regex;
use RegexParser\NodeVisitor\MetricsNodeVisitor;

$ast = Regex::create()->parse('/\d{4}-\d{2}-\d{2}/');
$metrics = $ast->accept(new MetricsNodeVisitor());

echo $metrics->getTotalNodeCount();
echo $metrics->getQuantifierCount();
echo $metrics->getCaptureGroupCount();
```

**Available Metrics:**

| Method                   | Description                |
|--------------------------|----------------------------|
| `getTotalNodeCount()`    | Total nodes in AST         |
| `getQuantifierCount()`   | Number of quantifiers      |
| `getCaptureGroupCount()` | Number of capturing groups |
| `getAlternationCount()`  | Number of alternations     |
| `getMaxNestingDepth()`   | Maximum nesting depth      |

---

### LengthRangeNodeVisitor

**Purpose:** Estimates minimum and maximum possible match length.

```php
use RegexParser\Regex;
use RegexParser\NodeVisitor\LengthRangeNodeVisitor;

$ast = Regex::create()->parse('/a{2,4}b*/');
$range = $ast->accept(new LengthRangeNodeVisitor());

echo $range->getMinLength();   // 2 (aa)
echo $range->getMaxLength();   // PHP_INT_MAX (unbounded)
```

---

## Extraction and Generation Visitors

### LiteralExtractorNodeVisitor

**Purpose:** Extracts fixed literals from the pattern, useful for optimization or indexing.

```php
use RegexParser\Regex;
use RegexParser\NodeVisitor\LiteralExtractorNodeVisitor;

$ast = Regex::create()->parse('/user-\d{4}/');
$literals = $ast->accept(new LiteralExtractorNodeVisitor());

echo $literals->getLiterals()[0];  // 'user-'
echo $literals->getPrefix();       // 'user-'
echo $literals->getSuffix();       // ''
```

---

### SampleGeneratorNodeVisitor

**Purpose:** Generates a sample string that matches the pattern. Used by `Regex::generate()`.

```php
use RegexParser\Regex;
use RegexParser\NodeVisitor\SampleGeneratorNodeVisitor;

$ast = Regex::create()->parse('/[A-Z][a-z]{3,5}\d{2}/');
$sample = $ast->accept(new SampleGeneratorNodeVisitor());

echo $sample;  // e.g., "Word12"
```

---

### TestCaseGeneratorNodeVisitor

**Purpose:** Generates test cases for the pattern, useful for QA tooling.

```php
use RegexParser\Regex;
use RegexParser\NodeVisitor\TestCaseGeneratorNodeVisitor;

$ast = Regex::create()->parse('/\d{3}-\d{4}/');
$cases = $ast->accept(new TestCaseGeneratorNodeVisitor());

print_r($cases);
/*
Array (
    [valid] => Array (
        [0] => 123-4567
        [1] => 000-0000
    )
    [invalid] => Array (
        [0] => 12-34567
        [1] => 1234-567
    )
)
*/
```

---

## Presentation and Visualization Visitors

### ExplainNodeVisitor

**Purpose:** Generates a plain-text explanation of what the pattern does. Used by `Regex::explain()`.

```php
use RegexParser\Regex;
use RegexParser\NodeVisitor\ExplainNodeVisitor;

$ast = Regex::create()->parse('/\d{3}-\d{4}/');
$explanation = $ast->accept(new ExplainNodeVisitor());

echo $explanation;
/*
Match exactly 3 digits, then hyphen, then exactly 4 digits.
*/
```

---

### HtmlExplainNodeVisitor

**Purpose:** Generates HTML explanation for use in documentation or web UIs.

```php
use RegexParser\Regex;
use RegexParser\NodeVisitor\HtmlExplainNodeVisitor;

$ast = Regex::create()->parse('/\w+@\w+\.\w+/');
$html = $ast->accept(new HtmlExplainNodeVisitor());

echo $html;
// <span class="regex-token regex-literal">...</span>
```

---

### DumperNodeVisitor

**Purpose:** Generates a debug-friendly AST dump. Useful for development and debugging.

```php
use RegexParser\Regex;
use RegexParser\NodeVisitor\DumperNodeVisitor;

$ast = Regex::create()->parse('/foo/');
$dump = $ast->accept(new DumperNodeVisitor());

echo $dump;
/*
RegexNode {
    delimiter: "/"
    pattern: SequenceNode {
        children: [
            LiteralNode {
                value: "foo"
            }
        ]
    }
    flags: ""
}
*/
```

---

### MermaidNodeVisitor

**Purpose:** Renders the AST as a Mermaid diagram for documentation or visualization.

```php
use RegexParser\Regex;
use RegexParser\NodeVisitor\MermaidNodeVisitor;

$ast = Regex::create()->parse('/a|b/');
$mermaid = $ast->accept(new MermaidNodeVisitor());

echo $mermaid;
/*
graph TD
    RegexNode
    RegexNode --> SequenceNode
    SequenceNode --> AlternationNode
    AlternationNode --> Sequence0
    AlternationNode --> Sequence1
*/
```

---

### AsciiTreeVisitor

**Purpose:** Renders a text-based tree of the AST for quick inspection.

```php
use RegexParser\Regex;
use RegexParser\NodeVisitor\AsciiTreeVisitor;

$ast = Regex::create()->parse('/^a+$/');
$tree = $ast->accept(new AsciiTreeVisitor());

echo $tree;
/*
Regex
\-- Sequence
    |-- Anchor (^)
    |-- Quantifier (+, greedy)
    |   \-- Literal ('a')
    \-- Anchor ($)
*/
```

---

### RailroadSvgVisitor

**Purpose:** Renders a railroad-style SVG diagram suitable for graphical output.

```php
use RegexParser\Regex;
use RegexParser\NodeVisitor\RailroadSvgVisitor;

$ast = Regex::create()->parse('/a|b/');
$svg = $ast->accept(new RailroadSvgVisitor());

echo $svg;
// <svg ...>...</svg>
```

---

### Highlighting Visitors

Base classes for syntax highlighting:

| Visitor                     | Output Format | Use Case   |
|-----------------------------|---------------|------------|
| `ConsoleHighlighterVisitor` | ANSI colors   | CLI output |
| `HtmlHighlighterVisitor`    | HTML spans    | Web output |

```php
use RegexParser\Regex;
use RegexParser\NodeVisitor\ConsoleHighlighterVisitor;

$ast = Regex::create()->parse('/\d+/');
$highlighted = $ast->accept(new ConsoleHighlighterVisitor());

echo $highlighted;
// "\033[32m\\d\033[0m\033[33m+\033[0m"
```

---

## Building Custom Visitors

### Pattern 1: Stateless Visitor (Returns a Value)

```php
use RegexParser\NodeVisitor\AbstractNodeVisitor;

class LiteralCountVisitor extends AbstractNodeVisitor
{
    public function visitLiteralNode(Node\LiteralNode $node): int
    {
        return 1;
    }

    public function visitSequenceNode(Node\SequenceNode $node): int
    {
        return array_sum(
            array_map(fn($child) => $child->accept($this), $node->children)
        );
    }
}

// Usage
$ast = Regex::create()->parse('/hello world/');
$visitor = new LiteralCountVisitor();
$count = $ast->accept($visitor);

echo $count;  // 2
```

---

### Pattern 2: Stateful Visitor (Accumulates State)

```php
use RegexParser\NodeVisitor\AbstractNodeVisitor;

class GroupCollectorVisitor extends AbstractNodeVisitor
{
    private array $groups = [];

    public function visitGroupNode(Node\GroupNode $node): Node\GroupNode
    {
        if ($node->name !== null) {
            $this->groups[] = $node->name;
        }

        return new Node\GroupNode(
            $node->child->accept($this),
            $node->type,
            $node->name
        );
    }

    public function getGroupNames(): array
    {
        return $this->groups;
    }
}

// Usage
$ast = Regex::create()->parse('/(?<year>\d{4})-(?<month>\d{2})/');
$visitor = new GroupCollectorVisitor();
$ast->accept($visitor);

print_r($visitor->getGroupNames());
// Array ( [0] => year [1] => month )
```

---

### Pattern 3: Transforming Visitor

```php
use RegexParser\NodeVisitor\AbstractNodeVisitor;

class UppercaserVisitor extends AbstractNodeVisitor
{
    public function visitLiteralNode(Node\LiteralNode $node): Node\LiteralNode
    {
        return new Node\LiteralNode(
            strtoupper($node->value),
            $node->startPosition,
            $node->endPosition
        );
    }
}

// Usage: Transform /hello/ to /HELLO/
$ast = Regex::create()->parse('/hello/');
$visitor = new UppercaserVisitor();
$newAst = $ast->accept($visitor);

$compiler = new CompilerNodeVisitor();
echo $compiler->visit($newAst);  // '/HELLO/'
```

---

## Visitor Quick Reference

```
┌───────────────────────────────────────────────────────────────────┐
│                    VISITOR QUICK REFERENCE                        │
├───────────────────────────────────────────────────────────────────┤
│                                                                   │
│  COMPILATION & TRANSFORMATION                                     │
│  ├── CompilerNodeVisitor          → Convert AST to pattern string │
│  ├── OptimizerNodeVisitor         → Safe optimizations            │
│  └── ModernizerNodeVisitor        → Modernize legacy syntax       │
│                                                                   │
│  VALIDATION & LINTING                                             │
│  ├── ValidatorNodeVisitor         → Semantic validation           │
│  ├── LinterNodeVisitor            → Performance/readability       │
│  ├── ReDoSAnalyzerNodeVisitor     → ReDoS risk classification     │
│  ├── ComplexityScoreNodeVisitor   → Complexity score              │
│  ├── MetricsNodeVisitor           → Collect metrics               │
│  └── LengthRangeNodeVisitor       → Estimate match length         │
│                                                                   │
│  EXTRACTION & GENERATION                                          │
│  ├── LiteralExtractorNodeVisitor  → Extract literals              │
│  ├── SampleGeneratorNodeVisitor   → Generate matching string      │
│  └── TestCaseGeneratorNodeVisitor → Generate test cases           │
│                                                                   │
│  PRESENTATION & VISUALIZATION                                     │
│  ├── ExplainNodeVisitor           → Plain-text explanation        │
│  ├── HtmlExplainNodeVisitor       → HTML explanation              │
│  ├── DumperNodeVisitor            → Debug AST dump                │
│  ├── MermaidNodeVisitor           → Mermaid diagram               │
│  ├── AsciiTreeVisitor             → ASCII tree                    │
│  ├── RailroadSvgVisitor           → Railroad SVG                  │
│  ├── ConsoleHighlighterVisitor    → ANSI highlighting             │
│  └── HtmlHighlighterVisitor       → HTML highlighting             │
│                                                                   │
└───────────────────────────────────────────────────────────────────┘
```

---

## Common Use Cases

| Use Case                 | Visitor(s)                               |
|--------------------------|------------------------------------------|
| Check pattern validity   | `ValidatorNodeVisitor`                   |
| Check for ReDoS          | `Regex::redos()` (uses internal visitor) |
| Optimize pattern         | `OptimizerNodeVisitor`                   |
| Explain pattern to users | `ExplainNodeVisitor`                     |
| Generate test cases      | `TestCaseGeneratorNodeVisitor`           |
| Count groups/metrics     | `MetricsNodeVisitor`                     |
| Visualize AST            | `MermaidNodeVisitor`, `AsciiTreeVisitor`, `RailroadSvgVisitor` |
| Highlight in CLI         | `ConsoleHighlighterVisitor`              |
| Round-trip parsing       | `CompilerNodeVisitor`                    |

---

## Summary

| Category  | Key Visitors                                                 |
|-----------|--------------------------------------------------------------|
| Base      | `NodeVisitorInterface`, `AbstractNodeVisitor`                |
| Compile   | `CompilerNodeVisitor`, `OptimizerNodeVisitor`                |
| Validate  | `ValidatorNodeVisitor`, `LinterNodeVisitor`                  |
| Analyze   | `ComplexityScoreNodeVisitor`, `MetricsNodeVisitor`           |
| Generate  | `SampleGeneratorNodeVisitor`, `TestCaseGeneratorNodeVisitor` |
| Visualize | `ExplainNodeVisitor`, `MermaidNodeVisitor`, `AsciiTreeVisitor`, `RailroadSvgVisitor` |

---

Previous: [AST Nodes](../nodes/README.md) | Next: [Docs Home](../README.md)
