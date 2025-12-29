# Extending RegexParser

We designed RegexParser so you can add new behavior without fighting the core. This guide shows the safe extension paths and the files to touch.

> If you can build a visitor, you can add a feature.

## What You Can Extend

| Area | What You Add | Typical Files |
| --- | --- | --- |
| New analysis | A new visitor | `src/NodeVisitor/*` |
| New syntax | A new node + parser logic | `src/Node/*`, `src/Parser.php`, `src/Lexer.php` |
| CLI features | A new command | `src/Cli/Command/*` |
| Framework bridges | New integration layer | `src/Bridge/*` |

## Extension Flow (One Diagram)

```
Idea -> Node -> Parser/Lexer -> Visitor -> Tests -> Docs
```

We keep that order because the AST is the contract between parsing and analysis.

## Path 1: Add a New Visitor (Recommended First)

Visitors are the safest extension point because they do not change syntax.

1. Pick an existing visitor to copy (for example `ExplainNodeVisitor`).
2. Extend `AbstractNodeVisitor`.
3. Override the node methods you care about.
4. Test against a parsed AST.

Example: Count literal nodes.

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

> You can test this with `$ast->accept(new LiteralCountVisitor())`.

## Path 2: Add New Syntax (Node + Parser)

This path requires more care because it changes the AST.

### Step 1: Create a Node Class

Use existing nodes as templates. `CalloutNode`, `PcreVerbNode`, and `ConditionalNode` are good examples.

Checklist:
- Extend `AbstractNode`.
- Implement `accept()` and call the correct `visitX()` method.
- Keep properties `readonly`.

### Step 2: Update the Visitor Interface

Every node needs a matching method in `NodeVisitorInterface` and a default in `AbstractNodeVisitor`.

```
visitYourNewNode(YourNewNode $node): mixed
```

### Step 3: Teach the Lexer and Parser

- If the syntax introduces new tokens, update `src/Lexer.php` and `src/TokenType.php`.
- Add parsing logic in `src/Parser.php` where the construct belongs (group, atom, char class, etc).

### Step 4: Update Compiler and Explain

If the syntax must round-trip or explain correctly, update `CompilerNodeVisitor` and `ExplainNodeVisitor`.

### Step 5: Add Tests

- Parser coverage: `tests/Unit/Parser/*`
- Visitor coverage: `tests/Unit/NodeVisitor/*`
- Integration behavior: `tests/Integration/*`

> If you add a new node, update `docs/nodes/README.md` and `docs/visitors/README.md`.

## Path 3: Extend the CLI

CLI commands live under `src/Cli/Command/*` and are wired in `src/Cli/Application.php`.

Steps:
1. Implement `CommandInterface` (or extend `AbstractCommand`).
2. Register the command in `Application`.
3. Add tests under `tests/Functional/Cli/*`.

## Path 4: Extend Integrations

Bridge code lives under `src/Bridge/*`. Use existing bridges as templates.

- PHPStan: `src/Bridge/PHPStan/RegexParserRule.php`
- Symfony: `src/Bridge/Symfony/*`

## Extension Checklist

```
[ ] Node class added (if needed)
[ ] Visitor interface updated
[ ] Parser + lexer updated
[ ] Compiler / explainer updated
[ ] Tests added
[ ] Docs updated
```

---

Previous: `visitors/README.md` | Next: `MAINTAINERS_GUIDE.md`
