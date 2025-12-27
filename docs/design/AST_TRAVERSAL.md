# AST Traversal Design

This document explains how RegexParser traverses the AST, why the visitor
pattern was chosen, and how to build safe and maintainable traversals.

## Why a visitor pattern?

RegexParser treats the AST as a stable data model. Algorithms (validation,
optimization, explanation) evolve, but node shapes should remain consistent.
The visitor pattern keeps this separation explicit:

- Nodes are immutable data holders.
- Visitors implement behavior.
- Adding a new operation does not require modifying node classes.

This approach is common in compilers and static analyzers because it makes
changes local and testable.

## Double dispatch in practice

Each node implements `accept()` and calls the visitor method that matches its
concrete type. This is double dispatch: the runtime type of both the node and
visitor determine the behavior.

```php
$ast = $regex->parse('/foo|bar/');
$result = $ast->accept(new ExplainNodeVisitor());
```

## Traversal strategy

Visitors in RegexParser are explicit about traversal. Most of them follow a
simple depth-first approach:

- `RegexNode` delegates to `pattern`.
- `SequenceNode` iterates `children`.
- `AlternationNode` iterates `alternatives`.
- `GroupNode` delegates to `child`.
- `QuantifierNode` delegates to `node`.

This keeps control in the visitor and avoids implicit recursion that can hide
performance or correctness issues.

## Return types and state

There are two common patterns in this codebase:

1. Stateless return value
   - Example: `CompilerNodeVisitor` returns strings.
2. Stateful visitor
   - Example: visitors that collect metrics store internal counters and return
     the final result via a getter.

`AbstractNodeVisitor` exists to reduce boilerplate by providing default return
values.

## Transformations

Visitors that transform the AST should return new nodes instead of mutating
existing ones. Nodes are `readonly` and store source positions that should be
preserved. When constructing new nodes, keep the original `startPosition` and
`endPosition` so diagnostics remain stable.

## Best practices

- Prefer explicit traversal over reflection-based recursion.
- Keep visitors pure when possible to make tests deterministic.
- Guard against recursion depth for very large or adversarial inputs.
- For linting and validation, keep error positions stable by preserving offsets.

## Related docs

- [AST Nodes](../nodes/README.md)
- [AST Visitors](../visitors/README.md)
- [Architecture](../ARCHITECTURE.md)

---

Previous: [Architecture](../ARCHITECTURE.md) | Next: [External References](../references/README.md)
