# Architecture and Design Notes

This document describes the internal architecture of RegexParser and the design decisions that shape its API,
performance profile, and extension model. It is written for advanced users, framework maintainers, and contributors.

## Design goals

- Precise diagnostics with stable offsets for IDEs and CI tooling.
- Fast, parallel linting over large codebases.
- Clear separation between data structures and algorithms.

## Parsing strategy: hand-written recursive descent

RegexParser uses a hand-written recursive descent parser rather than a generated parser (Yacc/Bison, ANTLR, etc.).
This is a deliberate choice with concrete benefits for a PHP library:

- **Precise error reporting**: errors can be surfaced with exact byte offsets and localized context.
- **Reduced operational overhead**: no generated parser artifacts to ship or regenerate.
- **Debuggability**: the grammar and control flow are visible in plain PHP code, which makes debugging and patching safer.

The parsing pipeline is explicit and linear:

- `src/Lexer.php` tokenizes the pattern into a linear `TokenStream` with offsets.
- `src/Parser.php` recursively consumes that stream to build a typed AST rooted at `Node\RegexNode`.

This keeps the parser both predictable and approachable to contributors, while remaining faithful to PCRE syntax.

## AST traversal: visitor pattern

RegexParser models regexes as a typed AST under `RegexParser\Node\*` and uses the Visitor Pattern for traversal.
This separates the **data structure** (nodes) from the **algorithms** (analysis, compilation, optimization, etc.).

Examples of visitors in the codebase:

- Linting and validation visitors
- Explanation and highlighting visitors
- Optimization and modernization visitors
- ReDoS analysis visitors

This keeps the AST stable while allowing new rules and transformations to evolve independently.
For a deeper explanation of traversal strategy and design trade-offs, see
[docs/design/AST_TRAVERSAL.md](design/AST_TRAVERSAL.md).

## Performance architecture: MapReduce-style linting

The CLI linter is designed as a MapReduce-style pipeline, optimized for large codebases:

- **Map phase**: `RegexPatternExtractor` discovers patterns across source files and emits
  `RegexPatternOccurrence` items.
- **Reduce phase**: `RegexAnalysisService` and `RegexLintService` analyze and aggregate results into
  a single `RegexLintReport` with stats, issues, and optimizations.

### Parallel execution

On CLI runtimes with `pcntl_fork`, the analysis phase parallelizes by chunking patterns and spawning workers.
Each worker analyzes its chunk in isolation and writes a serialized payload to a temporary file. The parent
process reads those payloads and reduces them into a final report. This is IPC via the filesystem; no sockets
or network transport are required.

This design keeps memory usage stable because each worker has an isolated heap and the parent only retains
aggregated results. In internal runs, memory typically stays around ~30MB even when scanning 120k+ files,
though exact numbers depend on the environment and pattern density.

### Why this matters

- **Predictable memory**: workers bound memory growth; the parent only aggregates results.
- **Failure isolation**: a worker crash does not corrupt the parent process.
- **CI scalability**: large repositories can be scanned quickly without a single long-lived heap.

---

Previous: [Cookbook](COOKBOOK.md) | Next: [Maintainers Guide](MAINTAINERS_GUIDE.md)
