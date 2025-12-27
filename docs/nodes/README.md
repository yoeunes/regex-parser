# AST Node Reference

This page documents every node type in the RegexParser AST. It is written for
library users, tooling authors, and maintainers who need to understand how a
PCRE pattern is modeled.

## How to read this page

Each node includes:
- Purpose: what the node represents
- Example: a PCRE snippet that produces the node
- Fields: public properties you can read
- Notes: common pitfalls or usage guidance

If you are new to regex, start with the tutorial first:
- [Regex Tutorial](../tutorial/README.md)

## Core structure

### RegexNode

Purpose: root of the AST; wraps the pattern body, flags, and delimiter.
Example: `/foo/i`
Fields:
- `pattern` (NodeInterface)
- `flags` (string)
- `delimiter` (string)
Notes: use `Regex::parse()` or `Regex::parsePattern()` to get a `RegexNode`.

### SequenceNode

Purpose: ordered list of nodes that must match in sequence.
Example: `/abc/`
Fields:
- `children` (array<NodeInterface>)
Notes: most patterns compile to a `SequenceNode` unless they are a single atom.

### AlternationNode

Purpose: branches separated by `|`.
Example: `/foo|bar/`
Fields:
- `alternatives` (array<SequenceNode>)
Notes: use non-capturing groups to control precedence: `/(?:foo|bar)baz/`.

### GroupNode

Purpose: grouping construct, including capturing, non-capturing, lookarounds,
atomic groups, and inline flags.
Example: `/(?:foo)/`, `/(?<name>foo)/`, `/(?=foo)/`
Fields:
- `child` (NodeInterface)
- `type` (GroupType)
- `name` (?string)
- `flags` (?string)
Notes: prefer non-capturing groups when you do not need captures.

### QuantifierNode

Purpose: repetition of a child node.
Example: `/a+/`, `/a{2,4}/`, `/a+?/`
Fields:
- `node` (NodeInterface)
- `quantifier` (string)
- `type` (QuantifierType)
Notes: nested variable quantifiers are a common ReDoS risk.

## Literal and token nodes

### LiteralNode

Purpose: literal text (possibly multiple characters).
Example: `/hello/`
Fields:
- `value` (string)
Notes: literals are produced for unescaped characters and escaped literals.

### CharLiteralNode

Purpose: a single escaped code point (unicode, octal, etc.).
Example: `/\x{41}/`, `/\o{101}/`
Fields:
- `originalRepresentation` (string)
- `codePoint` (int)
- `type` (CharLiteralType)
Notes: use `\x{...}` for clarity in Unicode patterns.

### CharTypeNode

Purpose: character type escapes like `\d`, `\w`, `\s` and their negations.
Example: `/\d+/`
Fields:
- `value` (string)
Notes: the `value` is the short type name (for example, `d` or `D`).

### DotNode

Purpose: the dot token `.` (any char except newline unless `s` flag is set).
Example: `/.+/`
Fields: none
Notes: prefer explicit classes over `.` when you know the domain.

### AnchorNode

Purpose: start/end anchors like `^` and `$` (and related anchors).
Example: `/^foo$/`
Fields:
- `value` (string)
Notes: anchors are essential for validation patterns.

### AssertionNode

Purpose: zero-width assertions that are not anchors (for example `\b`).
Example: `/\bword\b/`
Fields:
- `value` (string)
Notes: assertions do not consume characters.

### KeepNode

Purpose: `\K` keep-out; resets the start of the reported match.
Example: `/foo\Kbar/`
Fields: none
Notes: use when you need to keep the match short but still search for context.

### CommentNode

Purpose: inline comments like `(?# comment )`.
Example: `/(?#env)prod|dev/`
Fields:
- `comment` (string)
Notes: useful with `x` mode to keep patterns readable.

## Character class nodes

### CharClassNode

Purpose: character class, including negated classes.
Example: `/[a-z]/`, `/[^0-9]/`
Fields:
- `expression` (NodeInterface)
- `isNegated` (bool)
Notes: avoid `[A-z]`; use `[A-Za-z]`.

### RangeNode

Purpose: a range within a class.
Example: `/[a-f]/`
Fields:
- `start` (NodeInterface)
- `end` (NodeInterface)
Notes: start/end are usually `CharLiteralNode` or `LiteralNode`.

### PosixClassNode

Purpose: POSIX class inside a character class.
Example: `/[[:alpha:]]/`
Fields:
- `class` (string)
Notes: only valid inside `[...]`.

### UnicodePropNode

Purpose: Unicode property escape like `\p{L}` or `\P{Lu}`.
Example: `/^\p{L}+$/u`
Fields:
- `prop` (string)
- `hasBraces` (bool)
Notes: use with the `u` flag for Unicode semantics.

### UnicodeNode

Purpose: Unicode code point escape.
Example: `/\x{1F600}/`
Fields:
- `code` (string)
Notes: prefer hex escapes to avoid encoding ambiguity.

### ControlCharNode

Purpose: control character escape like `\cM`.
Example: `/\cM/`
Fields:
- `char` (string)
- `codePoint` (int)
Notes: rarely needed in application patterns.

### ScriptRunNode

Purpose: script run verb assertions like `(*script_run:Latin)`.
Example: `/(*script_run:Latin)\p{L}+/u`
Fields:
- `script` (string)
Notes: advanced PCRE2 feature for Unicode script validation.

### ClassOperationNode

Purpose: set operations inside character classes (`&&`, `--`).
Example: `/[a-z&&[^aeiou]]/`
Fields:
- `type` (ClassOperationType)
- `left` (NodeInterface)
- `right` (NodeInterface)
Notes: use to build precise sets without long manual ranges.

### ClassOperationType (enum)

Values:
- `INTERSECTION` for `&&`
- `SUBTRACTION` for `--`

### CharLiteralType (enum)

Values:
- `UNICODE`, `UNICODE_NAMED`, `OCTAL`, `OCTAL_LEGACY`

## Groups, references, and control flow

### GroupType (enum)

Values (examples):
- `T_GROUP_CAPTURING`: `(foo)`
- `T_GROUP_NON_CAPTURING`: `(?:foo)`
- `T_GROUP_NAMED`: `(?<name>foo)`
- `T_GROUP_LOOKAHEAD_POSITIVE`: `(?=foo)`
- `T_GROUP_LOOKAHEAD_NEGATIVE`: `(?!foo)`
- `T_GROUP_LOOKBEHIND_POSITIVE`: `(?<=foo)`
- `T_GROUP_LOOKBEHIND_NEGATIVE`: `(?<!foo)`
- `T_GROUP_INLINE_FLAGS`: `(?i:foo)`
- `T_GROUP_ATOMIC`: `(?>foo)`
- `T_GROUP_BRANCH_RESET`: `(?|foo|bar)`

### BackrefNode

Purpose: backreference to a previous capture.
Example: `/(a)\1/`, `/(?<w>\w+)\k<w>/`
Fields:
- `ref` (string)
Notes: powerful but can hurt performance and readability.

### SubroutineNode

Purpose: subroutine calls like `(?1)` or `(?&name)`.
Example: `/(a)(?1)/`
Fields:
- `reference` (string)
- `syntax` (string)
Notes: useful for recursion and reuse; use with care.

### ConditionalNode

Purpose: conditional pattern `(?(condition)yes|no)`.
Example: `/(a)?(?(1)b|c)/`
Fields:
- `condition` (NodeInterface)
- `yes` (NodeInterface)
- `no` (NodeInterface)
Notes: conditions can be group numbers, names, or assertions.

### DefineNode

Purpose: `(?DEFINE...)` block for defining subpatterns.
Example: `/(?(DEFINE)(?<d>\d+))(?&d)/`
Fields:
- `content` (NodeInterface)
Notes: used with subroutine calls for reusable fragments.

### CalloutNode

Purpose: callouts like `(?C)` or `(?C42)`.
Example: `/(?C42)foo/`
Fields:
- `identifier` (int|string|null)
- `isStringIdentifier` (bool)
Notes: debugging hook in PCRE; uncommon in application code.

### PcreVerbNode

Purpose: PCRE verbs like `(*ACCEPT)` or `(*FAIL)`.
Example: `/(?:foo)(*ACCEPT)/`
Fields:
- `verb` (string)
Notes: can change matching control flow and backtracking behavior.

### LimitMatchNode

Purpose: `(*LIMIT_MATCH=...)` verb.
Example: `/(*LIMIT_MATCH=1000)foo/`
Fields:
- `limit` (int)
Notes: protective limit against runaway backtracking.

### VersionConditionNode

Purpose: version checks in conditionals `(?(VERSION>=10.0)yes|no)`.
Example: `/(?(VERSION>=10.30)foo|bar)/`
Fields:
- `operator` (string)
- `version` (string)
Notes: mostly useful for engine-compatibility shims.

## Supporting types

### NodeInterface

Purpose: base interface for all AST nodes.
Notes: defines the `accept()` method for visitors.

### AbstractNode

Purpose: base class for nodes with position tracking.
Fields:
- `startPosition` (int)
- `endPosition` (int)
Notes: positions refer to offsets in the pattern body, not the full `/.../flags`.

### QuantifierType (enum)

Values:
- `T_GREEDY` for `*`, `+`, `{m,n}`
- `T_LAZY` for `*?`, `+?`, `{m,n}?`
- `T_POSSESSIVE` for `*+`, `++`, `{m,n}+`

---

Previous: [Docs Home](../README.md) | Next: [AST Visitors](../visitors/README.md)
