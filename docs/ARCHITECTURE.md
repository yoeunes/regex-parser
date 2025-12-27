# Architecture and Design Notes

This page explains how RegexParser is structured, why it is built this way, and how AST traversal works.
It is intended for advanced users, framework maintainers, and contributors.

## Pipeline overview

RegexParser follows a clear pipeline:

1) PatternParser splits the full PCRE string into delimiter, body, and flags.
2) Lexer tokenizes the pattern into a TokenStream.
3) Parser (recursive descent) builds a typed AST rooted at `Node\RegexNode`.
4) Visitors traverse the AST for validation, explanation, highlighting, optimization, and ReDoS analysis.
5) Compiler visitors turn the AST back into a regex string when needed.

This design keeps parsing, analysis, and presentation concerns separate and testable.

## Parser strategy

- The parser is a hand-written recursive descent parser.
- It aligns well with PCRE grammar and allows context-aware decisions (lookarounds, character classes, flags).
- Tolerant mode returns a partial AST plus errors instead of throwing, which is useful for IDEs and tooling.

## AST model

- Nodes live under `RegexParser\Node\*`.
- Every node stores byte offsets (`startPosition`, `endPosition`) to map back to the original pattern.
- AST node types can evolve within 1.x; the `Regex` facade API and result objects remain stable.

## Traversal algorithm (visitor pattern)

Traversal is done with a visitor pattern:

- Each node implements `accept()` which calls the matching `visit*()` method on the visitor.
- Visitors decide whether and how to recurse; there is no global traversal engine.
- Most built-in visitors perform depth-first traversal.
- Order is left-to-right, following `SequenceNode::$children` and `AlternationNode::$alternatives`.

Typical traversal (pre-order):

```php
$node->accept($visitor);
// visitor calls accept() on children in order
```

If you need post-order behavior (child results before parent), implement it explicitly in your visitor.

## Analysis and transformations

- Validation uses a dedicated visitor to enforce PCRE syntax rules and constraints.
- ReDoS analysis is static and AST-driven, focusing on nested quantifiers, overlap, backreferences, and shielding constructs.
- Optimization and modernization run AST transforms and then recompile the pattern.

## Caching model

- Optional cache via `RegexOptions` stores serialized ASTs.
- Cache entries are versioned with `Regex::CACHE_VERSION`.
- If `php_version` is provided, it is included in the cache key to avoid cross-version mismatches.

## References and background

- Abstract Syntax Tree (AST): https://en.wikipedia.org/wiki/Abstract_syntax_tree
- Recursive descent parser: https://en.wikipedia.org/wiki/Recursive_descent_parser
- Visitor pattern: https://en.wikipedia.org/wiki/Visitor_pattern
- Depth-first search: https://en.wikipedia.org/wiki/Depth-first_search
- Backtracking: https://en.wikipedia.org/wiki/Backtracking
- Crafting Interpreters (AST and visitors): https://craftinginterpreters.com/

---

Previous: [Reference](reference.md) | Next: [Maintainers Guide](MAINTAINERS_GUIDE.md)
