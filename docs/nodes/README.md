# AST Nodes: The Vocabulary of RegexParser

This reference lists every node class in the RegexParser AST. We keep nodes small and immutable so visitors can reason about structure safely.

> If you are new to regex, start with `docs/tutorial/README.md`. We will build the intuition first and then come back here.

## A Tiny AST Example

We always start with structure before code. Here is what a simple pattern looks like as nodes.

```
Pattern: /^(?<user>\w+)@(?<host>\w+)$/

RegexNode
+-- SequenceNode
    |-- AnchorNode("^")
    |-- GroupNode(name: user)
    |   +-- QuantifierNode("+")
    |       +-- CharTypeNode("\\w")
    |-- LiteralNode("@")
    |-- GroupNode(name: host)
    |   +-- QuantifierNode("+")
    |       +-- CharTypeNode("\\w")
    +-- AnchorNode("$")
```

```php
use RegexParser\Regex;

$ast = Regex::create()->parse('/^(?<user>\w+)@(?<host>\w+)$/');
```

## Node Map (By Category)

### Root and Structure

| Node | Typical Syntax | Notes |
| --- | --- | --- |
| `RegexNode` | `/pattern/flags` | Root node with delimiter, flags, and `pattern` child |
| `SequenceNode` | `abc` | Ordered list of nodes |
| `AlternationNode` | `a|b` | Alternatives (`SequenceNode` per branch) |

### Grouping and Flow

| Node | Typical Syntax | Notes |
| --- | --- | --- |
| `GroupNode` | `(abc)`, `(?:abc)`, `(?=abc)` | Capturing, non-capturing, lookarounds, inline flags |
| `ConditionalNode` | `(?(1)yes|no)` | Branch based on group/condition |
| `DefineNode` | `(?(DEFINE)...)` | Defines subroutines without matching |
| `SubroutineNode` | `(?&name)` or `(?1)` | Reuse a group by name or number |
| `KeepNode` | `\K` | Reset start of match |
| `VersionConditionNode` | `(?(VERSION>=10.0)yes|no)` | PCRE version-dependent branches |
| `LimitMatchNode` | `(*LIMIT_MATCH=1000)` | PCRE match limit verb |

### Quantifiers

| Node | Typical Syntax | Notes |
| --- | --- | --- |
| `QuantifierNode` | `+`, `*`, `?`, `{m,n}` | Wraps a child node and a `QuantifierType` |

### Literals and Characters

| Node | Typical Syntax | Notes |
| --- | --- | --- |
| `LiteralNode` | `hello` | Plain literal sequences |
| `CharLiteralNode` | `\x41` or `\u{1F600}` | Escaped literals |
| `CharTypeNode` | `\d`, `\w`, `\s` | Character type shorthands |
| `DotNode` | `.` | Matches any character (with flags) |
| `ControlCharNode` | `\cA` | Control characters |
| `UnicodeNode` | `\x{...}` | Unicode code point literal |

### Character Classes

| Node | Typical Syntax | Notes |
| --- | --- | --- |
| `CharClassNode` | `[a-z]` | Contains class members |
| `RangeNode` | `a-z` | Range inside a class |
| `PosixClassNode` | `[[:digit:]]` | POSIX classes inside `[]` |
| `UnicodePropNode` | `\p{L}` or `\P{L}` | Unicode property |
| `ScriptRunNode` | `\p{scx=Latin}` | Script runs |
| `ClassOperationNode` | `[a-z&&[^aeiou]]` | Intersection and subtraction |

### Anchors and Assertions

| Node | Typical Syntax | Notes |
| --- | --- | --- |
| `AnchorNode` | `^`, `$`, `\b`, `\A` | Position anchors |
| `AssertionNode` | `(?=...)`, `(?!...)`, `(?<=...)` | Lookarounds |

### Backreferences and Verbs

| Node | Typical Syntax | Notes |
| --- | --- | --- |
| `BackrefNode` | `\1`, `\k<name>` | Backreference to group |
| `PcreVerbNode` | `(*FAIL)`, `(*SKIP)` | PCRE verbs |
| `CalloutNode` | `(?C)` | Callout support |
| `CommentNode` | `(?# comment )` | Inline comments |

## Key Node Fields (The Ones You Will Read Most)

We explain the common fields before you look at code.

- `RegexNode::pattern` is the root of the pattern body.
- `SequenceNode::children` is ordered left to right.
- `AlternationNode::alternatives` are branch sequences.
- `GroupNode::type` tells you if the group is capturing, atomic, lookaround, etc.
- `QuantifierNode::type` (from `QuantifierType`) tells you greedy, lazy, or possessive.

## When You Need to Go Deeper

If you are writing a custom visitor, jump to `docs/visitors/README.md` and `docs/design/AST_TRAVERSAL.md`.

---

Previous: `reference/faq-glossary.md` | Next: `visitors/README.md`
