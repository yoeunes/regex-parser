# AST Node Architecture

This directory contains all AST (Abstract Syntax Tree) node types that represent parsed regular expression patterns.

## Overview

The RegexParser library uses a **Visitor Pattern** architecture where:
- **Nodes** are immutable data structures representing PCRE features
- **Visitors** implement operations on the AST (compilation, validation, explanation, etc.)

**Total Nodes**: 24 types covering all major PCRE features

---

## Quick Reference

### Node Categories

| Category | Nodes | PCRE Features |
|----------|-------|---------------|
| **Root** | RegexNode | Complete pattern with flags |
| **Structure** | SequenceNode, AlternationNode | Concatenation, OR |
| **Grouping** | GroupNode (9 types) | `(...)`, `(?:...)`, `(?>...)`, lookarounds, etc. |
| **Quantifiers** | QuantifierNode (3 types) | `*`, `+`, `?`, `{n,m}` (greedy/lazy/possessive) |
| **Basic Matching** | LiteralNode, DotNode, CharTypeNode | Literals, `.`, `\d`, `\w`, `\s` |
| **Character Classes** | CharClassNode, RangeNode, PosixClassNode | `[a-z]`, `[^0-9]`, `[:alpha:]` |
| **Unicode** | UnicodeNode, UnicodePropNode | `\x{FFFF}`, `\p{L}`, `\P{N}` |
| **Anchors** | AnchorNode, AssertionNode, KeepNode | `^`, `$`, `\b`, `\K` |
| **References** | BackrefNode, SubroutineNode, ConditionalNode | `\1`, `(?R)`, `(?(1)yes\|no)` |
| **Advanced** | CommentNode, PcreVerbNode | `(?#...)`, `(*FAIL)` |
| **Numeric** | OctalNode, OctalLegacyNode | `\o{377}`, `\012` |

---

## Node Hierarchy

```
NodeInterface
└── AbstractNode (startPos, endPos)
    ├── Container Nodes (have children):
    │   ├── RegexNode
    │   ├── SequenceNode
    │   ├── AlternationNode
    │   ├── GroupNode
    │   ├── QuantifierNode
    │   ├── ConditionalNode
    │   └── CharClassNode
    │
    └── Leaf Nodes (no children):
        ├── LiteralNode
        ├── DotNode
        ├── CharTypeNode
        ├── RangeNode
        ├── PosixClassNode
        ├── AnchorNode
        ├── AssertionNode
        ├── KeepNode
        ├── BackrefNode
        ├── SubroutineNode
        ├── UnicodeNode
        ├── UnicodePropNode
        ├── CommentNode
        ├── PcreVerbNode
        ├── OctalNode
        └── OctalLegacyNode
```

---

## Core Nodes

### RegexNode (Root)
Represents the entire regex pattern.

```php
// Pattern: /test/i
new RegexNode(
    pattern: new LiteralNode('test', ...),
    flags: 'i',
    ...
);
```

### SequenceNode
Represents ordered concatenation of nodes.

```php
// Pattern: abc
new SequenceNode([
    new LiteralNode('a', ...),
    new LiteralNode('b', ...),
    new LiteralNode('c', ...),
], ...);
```

### AlternationNode
Represents OR alternatives.

```php
// Pattern: foo|bar
new AlternationNode([
    new LiteralNode('foo', ...),
    new LiteralNode('bar', ...),
], ...);
```

### GroupNode
Represents all grouping constructs.

**Types** (GroupType enum):
- `T_GROUP_CAPTURING`: `(test)`
- `T_GROUP_NON_CAPTURING`: `(?:test)`
- `T_GROUP_NAMED`: `(?<name>test)`
- `T_GROUP_ATOMIC`: `(?>test)`
- `T_GROUP_LOOKAHEAD_POSITIVE`: `(?=test)`
- `T_GROUP_LOOKAHEAD_NEGATIVE`: `(?!test)`
- `T_GROUP_LOOKBEHIND_POSITIVE`: `(?<=test)`
- `T_GROUP_LOOKBEHIND_NEGATIVE`: `(?<!test)`
- `T_GROUP_BRANCH_RESET`: `(?|a|b)`
- `T_GROUP_INLINE_FLAGS`: `(?i:test)`

```php
// Pattern: (?<word>\w+)
new GroupNode(
    type: GroupType::T_GROUP_NAMED,
    child: new CharTypeNode('w', ...),
    name: 'word',
    ...
);
```

### QuantifierNode
Represents repetition.

**Types** (QuantifierType enum):
- `T_GREEDY`: `*`, `+`, `?`, `{n,m}`
- `T_LAZY`: `*?`, `+?`, `??`, `{n,m}?`
- `T_POSSESSIVE`: `*+`, `++`, `?+`, `{n,m}+`

```php
// Pattern: a++
new QuantifierNode(
    node: new LiteralNode('a', ...),
    quantifier: '+',
    type: QuantifierType::T_POSSESSIVE,
    ...
);
```

---

## Character Matching Nodes

### LiteralNode
Represents literal characters.

```php
// Pattern: hello
new LiteralNode('hello', ...)
```

### DotNode
Represents the `.` wildcard.

```php
// Pattern: .
new DotNode(...)
```

### CharTypeNode
Represents character type escapes.

**Supported Types**: `d`, `D`, `w`, `W`, `s`, `S`, `h`, `H`, `v`, `V`, `R`

```php
// Pattern: \d
new CharTypeNode('d', ...)
```

### CharClassNode
Represents character classes.

```php
// Pattern: [a-z0-9]
new CharClassNode(
    ranges: [
        new RangeNode('a', 'z', ...),
        new RangeNode('0', '9', ...),
    ],
    negated: false,
    ...
);

// Pattern: [^abc]
new CharClassNode(
    ranges: [
        new LiteralNode('a', ...),
        new LiteralNode('b', ...),
        new LiteralNode('c', ...),
    ],
    negated: true,
    ...
);
```

### RangeNode
Represents character ranges within classes.

```php
// Pattern: a-z
new RangeNode('a', 'z', ...)
```

### PosixClassNode
Represents POSIX character classes.

```php
// Pattern: [:alpha:]
new PosixClassNode('alpha', negated: false, ...)

// Pattern: [:^digit:]
new PosixClassNode('digit', negated: true, ...)
```

---

## Position & Assertion Nodes

### AnchorNode
Represents position anchors.

**Types**: `^`, `$`, `A`, `Z`, `z`

```php
// Pattern: ^
new AnchorNode('^', ...)
```

### AssertionNode
Represents zero-width assertions.

**Types**: `b`, `B`, `G`

```php
// Pattern: \b
new AssertionNode('b', ...)
```

### KeepNode
Represents `\K` keep assertion.

```php
// Pattern: \K
new KeepNode(...)
```

---

## Reference Nodes

### BackrefNode
Represents backreferences.

```php
// Pattern: \1
new BackrefNode(index: 1, name: null, ...)

// Pattern: \k<name>
new BackrefNode(index: null, name: 'name', ...)
```

### SubroutineNode
Represents subroutines and recursion.

```php
// Pattern: (?R)
new SubroutineNode(reference: null, ...)  // Full recursion

// Pattern: (?1)
new SubroutineNode(reference: 1, ...)     // Group 1

// Pattern: (?&name)
new SubroutineNode(reference: 'name', ...) // Named group
```

### ConditionalNode
Represents conditional patterns.

```php
// Pattern: (?(1)yes|no)
new ConditionalNode(
    condition: 1,                    // Backreference condition
    yes: new LiteralNode('yes', ...),
    no: new LiteralNode('no', ...),
    ...
);

// Pattern: (?(?=test)a|b)
new ConditionalNode(
    condition: new GroupNode(...),   // Assertion condition
    yes: new LiteralNode('a', ...),
    no: new LiteralNode('b', ...),
    ...
);
```

---

## Unicode & Encoding Nodes

### UnicodeNode
Represents Unicode character escapes.

```php
// Pattern: \x{1F600}
new UnicodeNode(codepoint: '1F600', ...)

// Pattern: \u{FFFF}
new UnicodeNode(codepoint: 'FFFF', ...)
```

### UnicodePropNode
Represents Unicode property escapes.

```php
// Pattern: \p{L}
new UnicodePropNode(property: 'L', negated: false, ...)

// Pattern: \P{Nd}
new UnicodePropNode(property: 'Nd', negated: true, ...)
```

### OctalNode
Represents octal escapes.

```php
// Pattern: \o{377}
new OctalNode(value: '377', ...)
```

### OctalLegacyNode
Represents legacy octal escapes.

```php
// Pattern: \012
new OctalLegacyNode(value: '012', ...)
```

---

## Advanced Feature Nodes

### CommentNode
Represents inline comments.

```php
// Pattern: (?#this is a comment)
new CommentNode(text: 'this is a comment', ...)
```

### PcreVerbNode
Represents PCRE control verbs.

```php
// Pattern: (*FAIL)
new PcreVerbNode(verb: 'FAIL', arg: null, ...)

// Pattern: (*MARK:label)
new PcreVerbNode(verb: 'MARK', arg: 'label', ...)
```

---

## Working with Nodes

### Traversing the AST

Use the Visitor pattern:

```php
use RegexParser\Parser;
use RegexParser\NodeVisitor\CompilerNodeVisitor;

$parser = new Parser();
$ast = $parser->parse('/test/i');

$visitor = new CompilerNodeVisitor();
$compiled = $ast->accept($visitor);

echo $compiled; // "/test/i"
```

### Creating Custom Visitors

Implement `NodeVisitorInterface`:

```php
use RegexParser\NodeVisitor\NodeVisitorInterface;

class MyVisitor implements NodeVisitorInterface
{
    public function visitRegex(RegexNode $node): mixed
    {
        return $node->pattern->accept($this);
    }

    public function visitLiteral(LiteralNode $node): mixed
    {
        return "Literal: {$node->value}";
    }

    // Implement all other visit methods...
}
```

### Built-in Visitors

1. **CompilerNodeVisitor**: Regenerate PCRE pattern
2. **ValidatorNodeVisitor**: Semantic validation
3. **ExplainVisitor**: Human-readable explanation
4. **SampleGeneratorVisitor**: Generate sample strings

---

## Node Properties

All nodes extend `AbstractNode` with:
- `startPos: int` - 0-based start offset in original pattern
- `endPos: int` - 0-based end offset (exclusive)

All properties are `readonly` - nodes are immutable once created.

---

## Adding New Nodes

See `NODES_AUDIT_REPORT.md` for complete extension guide.

**Quick Steps**:
1. Create node class extending `AbstractNode`
2. Implement `accept(NodeVisitorInterface $visitor)`
3. Update all visitors with new `visit*()` method
4. Add parser logic
5. Update `NodeRegistry`
6. Add tests

---

## Node Registry

Access node metadata programmatically:

```php
use RegexParser\Node\NodeRegistry;

// Get all nodes
$nodes = NodeRegistry::getAllNodes();

// Get count
$count = NodeRegistry::getNodeCount(); // 24

// Get nodes by feature
$groupingNodes = NodeRegistry::getNodesByFeature()['Grouping'];

// Get metadata
$metadata = NodeRegistry::getNodeMetadata(LiteralNode::class);
```

---

## See Also

- `NodeRegistry.php` - Complete node metadata
- `NODES_AUDIT_REPORT.md` - Detailed audit and analysis
- `../NodeVisitor/` - Visitor implementations
- `../../tests/` - Usage examples
