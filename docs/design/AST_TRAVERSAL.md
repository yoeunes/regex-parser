# AST Traversal: The Visitor Pattern

This is the most important internal pattern in RegexParser. If you understand this file, you can add new analyses and transformations safely.

> We keep nodes immutable. Visitors are the only place where behavior lives.

## The Tour Guide Analogy

We treat the AST as a museum. The visitor is a tour guide. The guide walks each room in order and produces a report.

```
RegexNode (Entrance)
+-- SequenceNode (Main Hall)
    |-- LiteralNode("foo")
    |-- GroupNode
    |   +-- SequenceNode
    |       +-- LiteralNode("bar")
    +-- AlternationNode
        |-- SequenceNode -> LiteralNode("baz")
        +-- SequenceNode -> LiteralNode("qux")
```

The guide never changes the rooms. The guide only observes or creates a new map.

## Double Dispatch (Step by Step)

We call `accept()` on the node. The node calls the matching `visitX()` method on the visitor. That is double dispatch.

```
$ast->accept($visitor)
   |
   v
RegexNode::accept($visitor)
   |
   v
$visitor->visitRegex($this)
   |
   v
$this->pattern->accept($visitor)
   |
   v
SequenceNode::accept($visitor)
   |
   v
$visitor->visitSequence($this)
   |
   v
foreach ($this->children as $child) {
    $child->accept($visitor);
}
```

Example:

```php
use RegexParser\Regex;
use RegexParser\NodeVisitor\ExplainNodeVisitor;

$ast = Regex::create()->parse('/foo|bar/');
$explanation = $ast->accept(new ExplainNodeVisitor());
```

## Traversal Rules by Node Type

The traversal is depth-first, but each node controls how it delegates.

| Node | Delegates To |
| --- | --- |
| `RegexNode` | `pattern` |
| `SequenceNode` | `children` in order |
| `AlternationNode` | `alternatives` in order |
| `GroupNode` | `child` |
| `QuantifierNode` | `node` |
| `CharClassNode` | `members` (ranges, literals, classes) |

> When you implement a visitor, always follow the node's delegation order. It matches how the parser builds structure.

## Stateless vs Stateful Visitors

Some visitors return a value; others collect state and expose it later.

Stateless example (compiler):

```php
use RegexParser\NodeVisitor\CompilerNodeVisitor;

$pattern = $ast->accept(new CompilerNodeVisitor());
```

Stateful example (metrics):

```php
use RegexParser\NodeVisitor\MetricsNodeVisitor;

$visitor = new MetricsNodeVisitor();
$ast->accept($visitor);
$metrics = $visitor->getMetrics();
```

`AbstractNodeVisitor` provides safe defaults so you only override the nodes you care about.

## Transforming the AST (Be Careful)

When you transform, you must create new nodes and preserve positions. That is how diagnostics stay accurate.

```php
use RegexParser\Node;
use RegexParser\NodeVisitor\AbstractNodeVisitor;

final class RemoveEmptySequenceVisitor extends AbstractNodeVisitor
{
    public function visitSequence(Node\SequenceNode $node): Node\SequenceNode
    {
        $children = [];
        foreach ($node->children as $child) {
            $next = $child->accept($this);
            if (null !== $next) {
                $children[] = $next;
            }
        }

        return new Node\SequenceNode(
            $children,
            $node->startPosition,
            $node->endPosition,
        );
    }
}
```

> If you drop or replace nodes, always keep `startPosition` and `endPosition` from the original structure.

## Common Mistakes (And Fixes)

- Forgetting to return a node from a visit method. Always return a value that matches the expected return type.
- Calling `$node->accept($this)` inside `visitNode` on the same node, which causes infinite recursion.
- Mutating nodes. Nodes are `readonly` for a reason.
- Ignoring unhandled nodes. Use `AbstractNodeVisitor` to get safe defaults.

## Exercises

1. Count capturing groups by looking for `GroupNode` with capturing types.
2. Track maximum nesting depth of `GroupNode` and `QuantifierNode`.
3. Collect anchors in order (`AnchorNode`, lookarounds, `\b`, `\B`).

---

Previous: `ARCHITECTURE.md` | Next: `references/README.md`
