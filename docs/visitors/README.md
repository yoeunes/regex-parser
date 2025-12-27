# AST Visitor Reference

Visitors are the algorithms that run over the AST. They keep the AST stable and
let you add new behaviors without changing node classes.

## How to use a visitor

```php
use RegexParser\Regex;
use RegexParser\NodeVisitor\ExplainNodeVisitor;

$regex = Regex::create();
$ast = $regex->parse('/foo|bar/');

$result = $ast->accept(new ExplainNodeVisitor());
```

## Base visitors

### NodeVisitorInterface

Purpose: contract implemented by all visitors. Every node type has a matching
`visitX()` method.

### AbstractNodeVisitor

Purpose: convenience base class that returns a default value for all nodes.
Use it when you only need to override a few methods.

## Compilation and transformation

### CompilerNodeVisitor

Purpose: converts the AST back into a PCRE string.

```php
$pattern = $ast->accept(new CompilerNodeVisitor());
```

### OptimizerNodeVisitor

Purpose: safe optimizations and normalizations (for example, `[0-9]` -> `\d`).

```php
$optimizedAst = $ast->accept(new OptimizerNodeVisitor());
```

### ModernizerNodeVisitor

Purpose: modernizes legacy syntax to cleaner equivalents.

```php
$modern = $ast->accept(new ModernizerNodeVisitor());
```

## Validation and linting

### ValidatorNodeVisitor

Purpose: semantic validation (backreferences, lookbehind bounds, etc.).
Used internally by `Regex::validate()`.

### LinterNodeVisitor

Purpose: performance and readability lint rules.
Used internally by the CLI linter and PHPStan rule.

### ReDoSProfileNodeVisitor

Purpose: internal profile used by the ReDoS analyzer to classify risk.

### ComplexityScoreNodeVisitor

Purpose: returns a numeric complexity score for a pattern.

### MetricsNodeVisitor

Purpose: collects counts and metrics (node counts, quantifier counts, etc.).

### LengthRangeNodeVisitor

Purpose: estimates the minimum and maximum match length.

## Extraction and generation

### LiteralExtractorNodeVisitor

Purpose: extracts fixed literals and prefix/suffix information.

```php
$literals = $ast->accept(new LiteralExtractorNodeVisitor());
```

### SampleGeneratorNodeVisitor

Purpose: generates a sample string that matches the pattern.
Used by `Regex::generate()`.

### TestCaseGeneratorNodeVisitor

Purpose: generates test cases for patterns (useful for QA tooling).

## Presentation and visualization

### ExplainNodeVisitor

Purpose: plain-text explanation of the pattern.
Used by `Regex::explain()`.

### HtmlExplainNodeVisitor

Purpose: HTML explanation for docs or UIs.
Used by `Regex::explain($regex, 'html')`.

### HighlighterVisitor

Purpose: base class for syntax highlighting visitors.

### ConsoleHighlighterVisitor

Purpose: ANSI console highlighting. Used by the CLI.

### HtmlHighlighterVisitor

Purpose: HTML highlighting for docs and UIs.

### DumperNodeVisitor

Purpose: debug-friendly AST dump.

### MermaidNodeVisitor

Purpose: renders the AST as a Mermaid graph.

### RailroadDiagramVisitor

Purpose: renders a railroad diagram for the pattern.

---

Previous: [AST Nodes](../nodes/README.md) | Next: [Docs Home](../README.md)
