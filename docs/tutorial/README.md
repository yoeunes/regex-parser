# Regex Tutorial (PHP + RegexParser)

This tutorial is a structured path from first regex to advanced PCRE features.
Each chapter includes syntax notes, PHP examples, good vs bad choices, and how
RegexParser can explain or validate what you wrote.

Prerequisites:
- Basic PHP (functions, strings)
- No prior regex knowledge required

## Chapters

1. [Regex Basics](01-basics.md)
2. [Character Classes and Escapes](02-character-classes.md)
3. [Anchors and Boundaries](03-anchors-boundaries.md)
4. [Quantifiers and Greediness](04-quantifiers.md)
5. [Groups and Alternation](05-groups-alternation.md)
6. [Lookarounds and Assertions](06-lookarounds.md)
7. [Backreferences, Subroutines, Conditionals](07-backreferences-recursion.md)
8. [Performance and ReDoS](08-performance-redos.md)
9. [Testing and Debugging with RegexParser](09-testing-debugging.md)
10. [Real-World Patterns in PHP](10-real-world-php.md)

## How to use this track

- Read the chapter, then run the examples in a PHP REPL or a small script.
- For each pattern, try `Regex::explain()` and `Regex::validate()` to see how the
  AST and diagnostics map to what you wrote.
- Use `bin/regex highlight` to visualize your pattern when it starts getting dense.

If you want a short path instead, read:
- [Quick Start](../QUICK_START.md)
- [Cookbook](../COOKBOOK.md)
- [ReDoS Guide](../REDOS_GUIDE.md)

---

Previous: [Docs Home](../README.md) | Next: [Regex Basics](01-basics.md)
