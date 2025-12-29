# Visitors: Behavior on Top of the AST

Visitors are where RegexParser does real work. Nodes are data; visitors are behavior. This separation is why the library stays extensible.

> Think of visitors as tour guides walking the AST museum. Different guides give different tours.

## The Visitor Flow

```
$ast = Regex::create()->parse('/foo|bar/');
$result = $ast->accept(new SomeVisitor());

RegexNode.accept(visitor)
    -> visitRegex(RegexNode)
        -> child.accept(visitor)
            -> visitSequence(SequenceNode)
                -> visitLiteral(LiteralNode)
```

That is double dispatch: the node decides which visitor method to call.

## Built-in Visitors (And Who Uses Them)

| Visitor | Purpose | Used By |
| --- | --- | --- |
| `ValidatorNodeVisitor` | Semantic validation | `Regex::validate()` |
| `LinterNodeVisitor` | Lint rules and best practices | `Regex::analyze()`, CLI `lint` |
| `ReDoSProfileNodeVisitor` | ReDoS risk profiling | `Regex::redos()` |
| `ExplainNodeVisitor` | Human-readable explanation | `Regex::explain('text')` |
| `HtmlExplainNodeVisitor` | HTML explanation | `Regex::explain('html')` |
| `ConsoleHighlighterVisitor` | ANSI highlight | `Regex::highlight('console')` |
| `HtmlHighlighterVisitor` | HTML highlight | `Regex::highlight('html')` |
| `CompilerNodeVisitor` | Recompile AST to string | Internal optimizations |
| `OptimizerNodeVisitor` | Safe rewrites | `Regex::optimize()` |
| `LiteralExtractorNodeVisitor` | Literal extraction | `Regex::literals()` |
| `SampleGeneratorNodeVisitor` | Matching sample generation | `Regex::generate()` |
| `MetricsNodeVisitor` | Complexity and metrics | Validation and analysis |
| `LengthRangeNodeVisitor` | Min/max length | Validation and analysis |
| `MermaidNodeVisitor` | Mermaid diagrams | CLI `diagram` (when enabled) |
| `RailroadDiagramVisitor` | Railroad diagrams | CLI `diagram` |

> If you want to add a new analysis, a new visitor is usually the right choice.

## Running a Visitor Manually

We start with an AST, then accept the visitor.

```php
use RegexParser\Regex;
use RegexParser\NodeVisitor\ExplainNodeVisitor;

$ast = Regex::create()->parse('/\d{4}-\d{2}-\d{2}/');
$explanation = $ast->accept(new ExplainNodeVisitor());
```

## Writing a Custom Visitor

We use `AbstractNodeVisitor` so we only implement the nodes we care about.

```php
use RegexParser\Node;
use RegexParser\NodeVisitor\AbstractNodeVisitor;

final class LiteralCountVisitor extends AbstractNodeVisitor
{
    private int $count = 0;

    public function visitLiteral(Node\LiteralNode $node): int
    {
        $this->count++;
        return $this->count;
    }

    public function getCount(): int
    {
        return $this->count;
    }
}
```

> If you return transformed nodes, preserve positions (`startPosition`, `endPosition`).

## Common Pitfalls

- Returning `null` from a visitor that is expected to return a node.
- Mutating nodes instead of creating new ones.
- Forgetting to traverse children (if you override `visitSequence`, you must walk `children`).

---

Previous: `nodes/README.md` | Next: `EXTENDING_GUIDE.md`
