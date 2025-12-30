# RegexParser Documentation

We wrote these docs as a masterclass. We start with simple mental models, then move into the real machinery: tokens, ASTs, visitors, and ReDoS analysis. If you stick with the flow, you will be able to read a regex like code and contribute to the parser confidently.

> If you are new to regex, start with the tutorial. We will build intuition first and show the AST later.

## The Big Picture

```
Regex string -> Lexer -> TokenStream -> Parser -> RegexNode (AST) -> Visitors -> Results
```

Think of it like this:
- **Lexing** is breaking a sentence into words
- **Parsing** is building a grammar tree from those words
- The **AST** is the DNA of the pattern
- **Visitors** are tour guides walking the DNA and producing answers

### What is RegexParser?

RegexParser is a PHP library that **turns regex patterns into code you can understand**. Instead of staring at cryptic patterns like `/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/i`, it shows you:

```
Email Pattern Structure:
┌─────────────────────────────────────┐
│  Start of string                    │
│  ┌─────────────────────────────┐    │
│  │ One or more:                │    │
│  │   - lowercase letters       │    │
│  │   - digits                  │    │
│  │   - . _ % + -               │    │
│  └─────────────────────────────┘    │
│  │ Literal: @                  │    │
│  ┌─────────────────────────────┐    │
│  │ Domain: letters/digits/-    │    │
│  └─────────────────────────────┘    │
│  │ Literal: .                  │    │
│  ┌─────────────────────────────┐    │
│  │ TLD: 2+ letters             │    │
│  └─────────────────────────────┘    │
│  End of string                      │
└─────────────────────────────────────┘
```

## Choose Your Path

### Learn Regex and the AST

Start with the tutorial and climb step by step.

1. [Tutorial Home](tutorial/README.md) - Complete regex course
2. [Basics](tutorial/01-basics.md) - Your first patterns
3. [Character Classes](tutorial/02-character-classes.md) - `[a-z]`, `\d`, etc.
4. [Anchors](tutorial/03-anchors-boundaries.md) - `^`, `$`, `\b`
5. [Quantifiers](tutorial/04-quantifiers.md) - `*`, `+`, `{n,m}`
6. [Groups](tutorial/05-groups-alternation.md) - `()`, `|`
7. [Lookarounds](tutorial/06-lookarounds.md) - `(?=...)`, `(?<=...)`
8. [Backreferences](tutorial/07-backreferences-recursion.md) - `\1`, `(?R)`
9. [Performance](tutorial/08-performance-redos.md) - ReDoS prevention

### Use RegexParser in Your Project

| Topic | Why It Matters | Link |
| --- | --- | --- |
| Quick Start | Fast setup and core API calls | [QUICK_START.md](QUICK_START.md) |
| CLI Guide | Lint and analyze patterns at scale | [guides/cli.md](guides/cli.md) |
| Regex in PHP | PCRE details and pitfalls | [guides/regex-in-php.md](guides/regex-in-php.md) |
| ReDoS Guide | Security and performance | [REDOS_GUIDE.md](REDOS_GUIDE.md) |
| Cookbook | Ready-to-use patterns | [COOKBOOK.md](COOKBOOK.md) |

### Go Deeper (Internals)

| Topic | What You Learn | Link |
| --- | --- | --- |
| Architecture | How Lexer, Parser, and AST fit together | [ARCHITECTURE.md](ARCHITECTURE.md) |
| AST Traversal | Visitor pattern in practice | [design/AST_TRAVERSAL.md](design/AST_TRAVERSAL.md) |
| Nodes | Full node reference | [nodes/README.md](nodes/README.md) |
| Visitors | Built-in visitors and how to write yours | [visitors/README.md](visitors/README.md) |
| Extending | Add new syntax or analysis | [EXTENDING_GUIDE.md](EXTENDING_GUIDE.md) |

### Maintain and Integrate

| Topic | What You Get | Link |
| --- | --- | --- |
| API Reference | Public API and options | [reference/api.md](reference/api.md) |
| Diagnostics | Error codes and hints | [reference/diagnostics.md](reference/diagnostics.md) |
| FAQ and Glossary | Terms and quick answers | [reference/faq-glossary.md](reference/faq-glossary.md) |
| Maintainers Guide | Integration patterns and release workflow | [MAINTAINERS_GUIDE.md](MAINTAINERS_GUIDE.md) |

## Core Concepts

### What is an AST?

An **Abstract Syntax Tree (AST)** is a structured representation of your regex pattern:

```
Pattern: /^(?<email>\w+@\w+\.\w+)$/

AST Structure:
RegexNode
└── SequenceNode
    ├── AnchorNode (^)
    ├── GroupNode (named: email)
    │   └── SequenceNode
    │       ├── LiteralNode (\w+)
    │       ├── LiteralNode (@)
    │       ├── LiteralNode (\w+)
    │       ├── LiteralNode (.)
    │       └── LiteralNode (\w+)
    └── AnchorNode ($)
```

This tree lets you:
- Understand what the pattern does
- Find specific parts programmatically
- Transform patterns safely

### What is ReDoS?

**ReDoS** (Regular Expression Denial of Service) happens when a pattern takes exponentially long to match certain inputs:

```php
// Dangerous pattern - DO NOT USE
$pattern = '/(a+)+$/';

// With input "aaaaaaaaaaaaaaaaa!", this can take minutes!
// The engine tries countless combinations.
```

RegexParser detects these patterns before they reach production.

## Quick Examples

### Explain a Pattern

```bash
bin/regex explain '/\d{4}-\d{2}-\d{2}/'
# Output: "Four digits, hyphen, two digits, hyphen, two digits"
```

### Visualize Structure

```bash
bin/regex diagram '/^[a-z]+$/'
# Output: ASCII art showing the pattern structure
```

### Check for Security Issues

```bash
bin/regex analyze '/(a+)+$/'
# Output: CRITICAL - Nested quantifiers detected!
```

### Lint Your Code

```bash
bin/regex lint src/ --format=console
# Output: All regex issues in your project
```

## A Tiny Example (The AST)

We explain the idea first, then show the code.

An AST is a tree that represents structure, not text. It is how RegexParser understands meaning.

```
Pattern: /^(?<user>\w+)@(?<host>\w+)$/

RegexNode
+-- SequenceNode
    |-- AnchorNode("^")
    |-- GroupNode(name: user)
    |   +-- QuantifierNode("+")
    |       +-- CharTypeNode("\\w")
    |-- LiteralNode("@")
    |-- GroupNode(name: host)
    |   +-- QuantifierNode("+")
    |       +-- CharTypeNode("\\w")
    +-- AnchorNode("$")
```

> Once you see the tree, you can explain, validate, optimize, and secure the pattern.

## Tips for Beginners

### 1. Start Simple

```php
// ❌ Don't start with complex patterns
'/^(?:(?:(?:0?[1-9])|(?:1[0-2]))\/(?:(?:0?[1-9])|(?:1[0-9])|(?:2[0-9])|(?:3[01]))\/(?:[0-9]{2})?[0-9]{2})$/'

// ✅ Start with basics
'/hello/'     // Match "hello"
'/[0-9]/'     // Match any digit
'/^start/'    // Match at the beginning
```

### 2. Use the CLI to Experiment

```bash
# See what your pattern does
bin/regex explain '/your-pattern/'

# Visualize it
bin/regex diagram '/your-pattern/'

# Check if it's safe
bin/regex analyze '/your-pattern/'
```

### 3. Test Incrementally

```php
// Build patterns step by step
$pattern = '/^/';           // Start anchor
$pattern .= '[a-z]+/';      // Add lowercase letters
$pattern .= '@/';           // Add @
$pattern .= '[a-z]+\./';    // Add domain with dot
$pattern .= '[a-z]+$/';     // Add TLD and end anchor

// Test at each step!
$result = $regex->validate($pattern);
```

### 4. Validate Before Production

```php
// Always validate patterns before using them
$result = $regex->validate($userPattern);

if (!$result->isValid()) {
    throw new InvalidArgumentException("Invalid pattern: " . $result->getErrorMessage());
}
```

### 5. Watch Out for ReDoS

```bash
# Check any pattern you're unsure about
bin/regex analyze '/your-pattern/'

# If you see "CRITICAL" or "HIGH", rewrite the pattern!
```

## Getting Help

- **Issues**: [GitHub Issues](https://github.com/yoeunes/regex-parser/issues)
- **Examples**: See `tests/Integration/` directory
- **Regex Testing**: [Regex101](https://regex101.com)

---

Previous: [README](../README.md) | Next: [Quick Start](QUICK_START.md)
