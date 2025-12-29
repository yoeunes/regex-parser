# Architecture: How RegexParser Thinks

This document is for future contributors (and for future us). We explain the internals as if we are onboarding a new teammate: simple first, detailed second.

> We read regex as code. The architecture is built to make that possible.

## The Parsing Pipeline

Before we touch code, we keep one picture in mind.

```
Pattern string
  "/^hello$/i"
       |
       v
+--------------+     +--------------+     +--------------+
|   Lexer      | --> |   Parser     | --> |   AST         |
| TokenStream  |     | RegexNode    |     | Node objects  |
+--------------+     +--------------+     +--------------+
       |
       v
+--------------+
|  Visitors    |
|  Explain     |
|  Validate    |
|  ReDoS       |
+--------------+
```

Lexing is breaking a sentence into words. Parsing is building a grammar tree from those words. The AST is the DNA of the pattern.

## Core Components (What They Do)

| Component | Class | Mental Model | Output |
| --- | --- | --- | --- |
| Lexer | `Lexer` | Split a sentence into tokens | `TokenStream` |
| Parser | `Parser` | Build a grammar tree | `RegexNode` |
| AST | `RegexNode` + nodes | Immutable structure | Node graph |
| Visitors | `NodeVisitorInterface` | Tour guides walking rooms | Values, reports, new AST |
| ReDoS | `ReDoSAnalyzer` | Risk audit | `ReDoSAnalysis` |

> Nodes never change; visitors do the work. That keeps analysis predictable and safe.

## The Lexer: Breaking a Sentence into Words

The lexer scans the pattern body and produces tokens with byte positions. Those positions power error reporting and IDE highlighting.

```php
use RegexParser\Lexer;

$lexer = new Lexer(PHP_VERSION_ID);
$stream = $lexer->tokenize('^hello$', 'i');

foreach ($stream as $token) {
    printf("%s '%s' at %d..%d\n", $token->type->value, $token->value, $token->start, $token->end);
}
```

`TokenStream` is the input to the parser. It keeps both the value and where it came from.

## The Parser: Building the Grammar Tree

Parsing is where structure emerges. We turn tokens into nodes so each construct has a type.

```
Pattern: /a+|b/

RegexNode
+-- AlternationNode
    |-- SequenceNode
    |   +-- QuantifierNode("+")
    |       +-- LiteralNode("a")
    +-- SequenceNode
        +-- LiteralNode("b")
```

The parser is a recursive descent parser (`src/Parser.php`). It delegates to specialized methods like `parseSequence()`, `parseGroup()`, and `parseCharacterClass()`.

## The AST: The DNA of the Pattern

The AST is a typed, immutable tree. It tells us what the pattern means, not how it was written. That is why we can explain, optimize, and validate with confidence.

```
RegexNode
|-- flags: "i"
|-- delimiter: "/"
+-- pattern: SequenceNode
```

All nodes implement `NodeInterface` and extend `AbstractNode`, which carries `startPosition` and `endPosition` for diagnostics.

## The Visitor Pattern (Tour Guide)

We use visitors so we can add new behaviors without touching node classes.

```
Tour guide: Visitor
Rooms:     Nodes

RegexNode.accept(visitor)
        |
        v
visitor.visitRegex(RegexNode)
        |
        v
child.accept(visitor)
```

Example with `ExplainNodeVisitor`:

```php
use RegexParser\Regex;
use RegexParser\NodeVisitor\ExplainNodeVisitor;

$ast = Regex::create()->parse('/\d{2,4}-[a-z]+/');
$explanation = $ast->accept(new ExplainNodeVisitor());
```

If you want the full double-dispatch walkthrough, see `design/AST_TRAVERSAL.md`.

## ReDoS Analysis (Why It Lives on the AST)

ReDoS is about structure, not just text. The AST exposes nested quantifiers, ambiguous branches, and risky backtracking paths. The analyzer walks the tree and assigns a severity, confidence, and hotspots.

```
Pattern: /(a+)+$/

RegexNode
+-- QuantifierNode("+")
    +-- GroupNode
        +-- QuantifierNode("+")
            +-- LiteralNode("a")

Nested quantifiers -> exponential backtracking risk
```

> We always call `Regex::redos()` for safety checks. It uses the AST, not string heuristics.

## Performance and Caching

RegexParser is used in CI and static analysis, so we optimize for large workloads.

- AST caching (`cache` option) avoids repeated parsing.
- Linting uses workers to process files in parallel.
- Token positions allow precise diagnostics without re-parsing.

```
Workers (extract) -> queue -> workers (analyze) -> report
```

## Where to Explore Next

- `design/AST_TRAVERSAL.md` for visitor mechanics
- `nodes/README.md` for node types
- `visitors/README.md` for built-in visitors
- `EXTENDING_GUIDE.md` for adding features

---

Previous: `COOKBOOK.md` | Next: `MAINTAINERS_GUIDE.md`
