# RegexParser Architecture

This document explains how RegexParser works under the hood. It is written for future maintainers and contributors who want to understand the AST, the parsing pipeline, and the analysis algorithms.

## Pipeline Overview

```
/^hello$/i
  |
  v
PatternParser -> {pattern, flags}
Lexer         -> TokenStream (byte offsets)
Parser        -> RegexNode (AST)
Visitors      -> validation, explanation, analysis, transforms
```

RegexParser treats a regex as code. The lexer turns a pattern string into tokens, the parser builds a tree of nodes, and visitors walk that tree to produce results.

## Step 1: Parse the Regex Literal

`Regex::parse()` accepts a full PCRE literal (`/pattern/flags`). Internally, the literal is split into:

- Pattern body (the text between delimiters)
- Delimiter (the chosen boundary character)
- Flags (`i`, `m`, `s`, `u`, `x`, and more)

This happens in `RegexParser\Internal\PatternParser`. The output is then passed to the lexer.

## Step 2: Lexer (Tokenization)

`src/Lexer.php` scans the pattern body as bytes and emits tokens with start/end offsets. The lexer is stateful because PCRE syntax changes meaning depending on context. The main states include:

- Default text
- Character class (`[...]`)
- Quoted literal blocks (`\Q...\E`)
- Extended mode comments and whitespace (`/x`)

The lexer output is a `TokenStream`, which is a linear sequence of `Token` objects. Tokens are positional, and offsets are byte-based so diagnostics line up with the original string.

## Step 3: Parser (Recursive Descent)

`src/Parser.php` is a handwritten recursive descent parser. It walks the `TokenStream` and builds an AST that reflects PCRE precedence:

- Atoms (literals, classes, groups)
- Quantifiers
- Concatenation (sequence)
- Alternation (`|`)

The parser entry point is `parse()`, which delegates to smaller methods such as:

```
parse()
  -> parseSequence()
      -> parseGroup()
      -> parseCharacterClass()
      -> parseQuantifier()
      -> parseAssertion()
      -> parseLiteral()
```

Errors raised here become `SyntaxErrorException` or `SemanticErrorException` and include byte offsets for IDE integration.

## Step 4: AST Structure

Every node:

- Is immutable (`readonly`)
- Holds `startPosition` and `endPosition` byte offsets
- Implements `NodeInterface::accept()`

The root node is `RegexNode`, which wraps the parsed pattern and flags.

Example AST shape:

```
Pattern: /^(?<email>\w+@\w+\.\w+)$/

RegexNode
└── SequenceNode
    ├── AnchorNode("^")
    ├── GroupNode(name: email)
    │   └── SequenceNode
    │       ├── QuantifierNode("+") -> CharTypeNode("\\w")
    │       ├── LiteralNode("@")
    │       ├── QuantifierNode("+") -> CharTypeNode("\\w")
    │       ├── LiteralNode(".")
    │       └── QuantifierNode("+") -> CharTypeNode("\\w")
    └── AnchorNode("$")
```

Node definitions live in `src/Node/`. The full node reference is in [docs/nodes/README.md](nodes/README.md).

## Step 5: Visitors and Traversal

Visitors encapsulate behavior. Each node calls the correct method on the visitor, enabling double-dispatch:

```
$node->accept($visitor)
  -> $visitor->visitXxx($node)
```

Built-in visitors live in `src/NodeVisitor/` and include:

- `ValidatorNodeVisitor`
- `ExplainNodeVisitor`
- `CompilerNodeVisitor`
- `ReDoSProfileNodeVisitor`
- `OptimizerNodeVisitor`

Traversal details are in [docs/design/AST_TRAVERSAL.md](design/AST_TRAVERSAL.md).

## Diagnostics and Validation

Validation runs against the AST and produces structured errors. `Regex::validate()` returns a `ValidationResult` containing:

- `isValid()`
- Error message and hint
- Byte offset and caret snippet

Diagnostics codes and explanations are documented in [docs/reference/diagnostics.md](reference/diagnostics.md).

## ReDoS Analysis (Static)

ReDoS analysis uses the AST and never executes the regex. `ReDoSAnalyzer` builds a `ReDoSProfileNodeVisitor` with a `CharSetAnalyzer`, then walks the tree.

Core heuristics include:

- Nested unbounded quantifiers (star height > 1)
- Overlapping alternation branches inside repetition
- Backreference loops within unbounded quantifiers
- Large bounded quantifiers (low risk, but flagged)
- Atomic groups and possessive quantifiers lowering severity

Analysis results are wrapped in `ReDoSAnalysis` and include severity, findings, and suggested rewrites. See [docs/REDOS_GUIDE.md](REDOS_GUIDE.md) for user-facing guidance.

## CLI Lint Pipeline

The CLI linter is a two-stage pipeline:

```
Paths -> RegexPatternExtractor -> RegexPatternOccurrence[]
      -> RegexAnalysisService  -> RegexLintReport
      -> Formatter (console/json/github)
```

When `--jobs` is used and `pcntl_fork` is available, both extraction and analysis run in parallel workers. Each worker handles a chunk of files or patterns, and the parent process aggregates results.

## Caching and Limits

RegexParser can cache ASTs via `CacheInterface`. By default it uses a filesystem cache under the system temp directory. You can disable caching with `cache => null` in `Regex::create()` options.

Limits are enforced in `RegexOptions`:

- `max_pattern_length`
- `max_lookbehind_length`
- `max_recursion_depth`
- `php_version` (feature validation)

## Extension Points

When you add a new PCRE construct, you typically update:

- `src/Node/*` to define a node
- `src/Parser.php` and `src/Lexer.php` to recognize syntax
- `src/NodeVisitor/*` to support traversal
- Tests and fixtures for valid/invalid cases

See [docs/EXTENDING_GUIDE.md](EXTENDING_GUIDE.md) for the full workflow.

---

Previous: [Cookbook](COOKBOOK.md) | Next: [Maintainers Guide](MAINTAINERS_GUIDE.md)
