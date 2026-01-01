# 📚 RegexParser Documentation

Welcome to the RegexParser documentation! Whether you're new to regular expressions or an experienced developer, these docs will help you understand, validate, and improve your regex patterns.

👉 **New to regex?** Start with the [Tutorial](tutorial/README.md) - no prior knowledge needed!

👉 **Need quick results?** Try the [Quick Start Guide](QUICK_START.md) for immediate value.

👉 **Want to extend RegexParser?** Check out the [Architecture](ARCHITECTURE.md) and [Extending Guide](EXTENDING_GUIDE.md).

## 🗺️ Documentation Map

### 🧑‍🎓 Learning Path (Beginners)

Start here if you're new to regular expressions:

- **[Regex Tutorial](tutorial/README.md)** - Learn regex from scratch
- **[Quick Start](QUICK_START.md)** - Get immediate results
- **[Regex in PHP](guides/regex-in-php.md)** - PHP-specific regex details

### 🛠️ Using RegexParser

Practical guides for common tasks:

- **[CLI Guide](guides/cli.md)** - Full command reference
- **[Cookbook](COOKBOOK.md)** - Ready-to-use patterns and recipes
- **[ReDoS Guide](REDOS_GUIDE.md)** - Security and performance best practices

### 🏗️ For Developers & Contributors

Understand the internals and extend RegexParser:

- **[Architecture](ARCHITECTURE.md)** - Internal design and algorithms
- **[AST Traversal](design/AST_TRAVERSAL.md)** - How the tree is processed
- **[Nodes Reference](nodes/README.md)** - All AST node types
- **[Visitors Reference](visitors/README.md)** - Custom analysis tools
- **[Extending Guide](EXTENDING_GUIDE.md)** - Build your own tools

### 📚 Reference Materials

Complete documentation and troubleshooting:

- **[API Reference](reference/api.md)** - Complete PHP API documentation
- **[Diagnostics](reference/diagnostics.md)** - Error types and messages
- **[Diagnostics Cheat Sheet](reference/diagnostics-cheatsheet.md)** - Quick error reference
- **[FAQ and Glossary](reference/faq-glossary.md)** - Common questions and terms
- **[Maintainers Guide](MAINTAINERS_GUIDE.md)** - For project maintainers

## 🎯 The Big Picture

```
/^hello$/i
  |
  v
Lexer  -> TokenStream (breaks pattern into pieces)
Parser -> RegexNode (AST) (builds a tree structure)
          |
          v
       Visitors -> validation, explanation, analysis, transforms
```

**RegexParser** treats a regex pattern like code:

- **Lexer**: Breaks the pattern into tokens (like words in a sentence)
- **Parser**: Builds a tree structure (AST) from those tokens
- **Visitors**: Walk the tree to produce results (validation, explanation, etc.)

Every example in these docs uses **RegexParser** as the reference implementation.

## 🔍 How RegexParser Works Under the Hood

Want to understand the magic? Start with [Architecture](ARCHITECTURE.md). It explains:

- The lexer and parser algorithms
- AST structure and invariants
- Visitor pattern implementation
- ReDoS analysis strategy
- Performance considerations

## 💡 Tips for Newcomers

- **Always include delimiters and flags**: `/pattern/flags` (e.g., `/hello/i`)
- **Build patterns step by step**: Start simple, then add complexity
- **Validate early**: Use `bin/regex validate` to catch errors quickly
- **Visualize complex patterns**: Use `bin/regex diagram` when patterns get complicated
- **Explain patterns**: Use `bin/regex explain` to understand what your pattern does

## 🆘 Getting Help

- **Issues & Bug Reports**: [GitHub Issues](https://github.com/yoeunes/regex-parser/issues)
- **Real-world Examples**: Check the `tests/Integration/` directory
- **Interactive Playground**: [regex101.com](https://regex101.com) (PCRE2 mode)
- **Community**: Join our [Discord community](#) for live help

## 📖 Key Concepts Explained

Need to understand a specific concept? Check out these dedicated pages:

- **[What is an AST?](concepts/ast.md)** - Abstract Syntax Tree explained simply
- **[Understanding Visitors](concepts/visitors.md)** - How the visitor pattern works
- **[ReDoS Deep Dive](concepts/redos.md)** - Regular Expression Denial of Service vulnerabilities
- **[PCRE vs Other Engines](concepts/pcre.md)** - PHP's regex engine and its features

**Don't know where to start?** Try these learning paths:

- **New to regex?** → [Tutorial](tutorial/README.md) → [Quick Start](QUICK_START.md)
- **Need to validate patterns?** → [CLI Guide](guides/cli.md) → [ReDoS Guide](REDOS_GUIDE.md)
- **Want to extend RegexParser?** → [Architecture](ARCHITECTURE.md) → [Extending Guide](EXTENDING_GUIDE.md)

## 🎓 Learning Resources

- **[Regex Tutorial](tutorial/README.md)** - Complete step-by-step guide
- **[FAQ & Glossary](reference/faq-glossary.md)** - Common questions answered
- **[Cookbook](COOKBOOK.md)** - Practical examples and patterns

---

📖 **Previous**: [Main README](../README.md) | 🚀 **Next**: [Quick Start](QUICK_START.md)
