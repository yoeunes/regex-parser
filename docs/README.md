# RegexParser Documentation

Welcome. These docs are a friendly path to learn regex in PHP and to build tooling with RegexParser. You can read them end to end or jump in by need.

If you are new to regex, start with the tutorial. If you are here to extend the parser, start with the architecture guide.

## Documentation Map

Start Here:

- [Tutorial](tutorial/README.md)
- [Quick Start](QUICK_START.md)
- [Regex in PHP](guides/regex-in-php.md)

Use RegexParser:

- [CLI Guide](guides/cli.md)
- [Cookbook](COOKBOOK.md)
- [ReDoS Guide](REDOS_GUIDE.md)

Internals and Contributors:

- [Architecture](ARCHITECTURE.md)
- [AST Traversal](design/AST_TRAVERSAL.md)
- [Nodes Reference](nodes/README.md)
- [Visitors Reference](visitors/README.md)
- [Extending Guide](EXTENDING_GUIDE.md)

Reference:

- [API Reference](reference/api.md)
- [Diagnostics](reference/diagnostics.md)
- [Diagnostics Cheat Sheet](reference/diagnostics-cheatsheet.md)
- [FAQ and Glossary](reference/faq-glossary.md)
- [Maintainers Guide](MAINTAINERS_GUIDE.md)

## The Big Picture

```
/^hello$/i
  |
  v
Lexer  -> TokenStream
Parser -> RegexNode (AST)
          |
          v
       Visitors -> validation, explanation, analysis, transforms
```

**RegexParser** treats a regex pattern like code. The **lexer** turns it into tokens, the **parser** builds a tree, and **visitors** walk that tree to produce results. Every example in these docs uses **RegexParser** as the reference implementation.

## How RegexParser Works Under the Hood

Start with [Architecture](ARCHITECTURE.md). It explains the lexer and parser algorithm, AST invariants, visitor flow, and the ReDoS analysis strategy.

## Tips for Newcomers

- Always include delimiters and flags: `/pattern/flags`.
- Build patterns in small steps and validate early with `bin/regex validate`.
- Use `bin/regex diagram` when a pattern is hard to reason about.

## Getting Help

- Issues: [GitHub Issues](https://github.com/yoeunes/regex-parser/issues)
- Examples: `tests/Integration/`
- Playground: [regex101.com](https://regex101.com) (PCRE2)

---

Previous: [README](../README.md) | Next: [Quick Start](QUICK_START.md)
