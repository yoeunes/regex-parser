# RegexParser Documentation

Welcome to the RegexParser documentation! Whether you're new to regular expressions or an experienced developer, this page will help you find the right resource.

---

## 🤔 What is RegexParser?

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

---

## 🎯 Choose Your Path

### I'm Completely New to Regex

Start here to learn regex from the beginning:

1. **[Regex Tutorial](tutorial/README.md)** - A complete, step-by-step guide
   - No prior knowledge required
   - Covers basics to advanced PCRE features
   - Includes examples you can run

2. **[Regex in PHP](guides/regex-in-php.md)** - How regex works in PHP
   - Understand `preg_match`, `preg_replace`, etc.
   - Common pitfalls and how to avoid them
   - PHP-specific behavior

3. **[Quick Start](QUICK_START.md)** - Get productive quickly
   - 10 common use cases with code
   - Copy-paste examples

### I Know Regex, Want to Use RegexParser

1. **[CLI Guide](guides/cli.md)** - Command-line tool usage
2. **[Cookbook](COOKBOOK.md)** - Ready-to-use patterns
3. **[ReDoS Guide](REDOS_GUIDE.md)** - Prevent catastrophic backtracking

### I Want to Integrate RegexParser

1. **[API Reference](reference/api.md)** - Complete method documentation
2. **[Architecture](ARCHITECTURE.md)** - How it works internally
3. **[Extending Guide](EXTENDING_GUIDE.md)** - Build custom visitors

---

## 📚 Core Concepts

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

---

## 🚀 Quick Examples

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

---

## 📖 Documentation by Topic

### Learning Regex

| Topic                                                     | Description           |
|-----------------------------------------------------------|-----------------------|
| [Tutorial Home](tutorial/README.md)                       | Complete regex course |
| [Basics](tutorial/01-basics.md)                           | Your first patterns   |
| [Character Classes](tutorial/02-character-classes.md)     | `[a-z]`, `\d`, etc.   |
| [Anchors](tutorial/03-anchors-boundaries.md)              | `^`, `$`, `\b`        |
| [Quantifiers](tutorial/04-quantifiers.md)                 | `*`, `+`, `{n,m}`     |
| [Groups](tutorial/05-groups-alternation.md)               | `()`, `               |` |
| [Lookarounds](tutorial/06-lookarounds.md)                 | `(?=...)`, `(?<=...)` |
| [Backreferences](tutorial/07-backreferences-recursion.md) | `\1`, `(?R)`          |
| [Performance](tutorial/08-performance-redos.md)           | ReDoS prevention      |

### Using RegexParser

| Topic                                  | Description                |
|----------------------------------------|----------------------------|
| [Quick Start](QUICK_START.md)          | 10 common use cases        |
| [CLI Guide](guides/cli.md)             | Command-line reference     |
| [Regex in PHP](guides/regex-in-php.md) | PHP regex functions        |
| [Cookbook](COOKBOOK.md)                | Safe patterns ready to use |
| [ReDoS Guide](REDOS_GUIDE.md)          | Security best practices    |

### Reference

| Topic                                       | Description                  |
|---------------------------------------------|------------------------------|
| [API Reference](reference/api.md)           | All methods and classes      |
| [Diagnostics](reference/diagnostics.md)     | Validation error codes       |
| [FAQ & Glossary](reference/faq-glossary.md) | Common questions & terms     |
| [AST Nodes](nodes/README.md)                | Node type reference          |
| [Visitors](visitors/README.md)              | Visitor implementation guide |

### Advanced

| Topic                                    | Description        |
|------------------------------------------|--------------------|
| [Architecture](ARCHITECTURE.md)          | Library design     |
| [AST Traversal](design/AST_TRAVERSAL.md) | How visitors work  |
| [Extending](EXTENDING_GUIDE.md)          | Custom visitors    |
| [Maintainers](MAINTAINERS_GUIDE.md)      | Contribution guide |

---

## 💡 Tips for Beginners

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

---

## 🆘 Getting Help

- **Issues**: [GitHub Issues](https://github.com/yoeunes/regex-parser/issues)
- **Examples**: See `tests/Integration/` directory
- **Regex Testing**: [Regex101](https://regex101.com)

---

## 📝 Documentation License

This documentation is part of the RegexParser project and follows the same license (MIT).

---

<p align="center">
  <a href="tutorial/README.md">Start Learning Regex →</a>
</p>

---

Previous: [README](../README.md) | Next: [Quick Start](QUICK_START.md)
