# RegexParser Documentation

We wrote these docs as a masterclass. We start with simple mental models, then move into the real machinery: tokens, ASTs, visitors, and ReDoS analysis. If you stick with the flow, you will be able to read a regex like code and contribute to the parser confidently.

> If you are new to regex, start with the tutorial. We will build intuition first and show the AST later.

## The Big Picture

```
Regex string -> Lexer -> TokenStream -> Parser -> RegexNode (AST) -> Visitors -> Results
```

Think of it like this:
- Lexing is breaking a sentence into words.
- Parsing is building a grammar tree from those words.
- The AST is the DNA of the pattern.
- Visitors are tour guides walking the DNA and producing answers.

## Choose Your Path

### Learn Regex and the AST

Start with the tutorial and climb step by step.

1. `tutorial/README.md`
2. `tutorial/01-basics.md`
3. `tutorial/02-character-classes.md`
4. `tutorial/03-anchors-boundaries.md`
5. `tutorial/04-quantifiers.md`
6. `tutorial/05-groups-alternation.md`
7. `tutorial/06-lookarounds.md`
8. `tutorial/07-backreferences-recursion.md`
9. `tutorial/08-performance-redos.md`
10. `tutorial/09-testing-debugging.md`
11. `tutorial/10-real-world-php.md`

### Use RegexParser in Your Project

| Topic | Why It Matters | Link |
| --- | --- | --- |
| Quick Start | Fast setup and core API calls | `QUICK_START.md` |
| CLI Guide | Lint and analyze patterns at scale | `guides/cli.md` |
| Regex in PHP | PCRE details and pitfalls | `guides/regex-in-php.md` |
| ReDoS Guide | Security and performance | `REDOS_GUIDE.md` |
| Cookbook | Ready-to-use patterns | `COOKBOOK.md` |

### Go Deeper (Internals)

| Topic | What You Learn | Link |
| --- | --- | --- |
| Architecture | How Lexer, Parser, and AST fit together | `ARCHITECTURE.md` |
| AST Traversal | Visitor pattern in practice | `design/AST_TRAVERSAL.md` |
| Nodes | Full node reference | `nodes/README.md` |
| Visitors | Built-in visitors and how to write yours | `visitors/README.md` |
| Extending | Add new syntax or analysis | `EXTENDING_GUIDE.md` |

### Maintain and Integrate

| Topic | What You Get | Link |
| --- | --- | --- |
| API Reference | Public API and options | `reference/api.md` |
| Diagnostics | Error codes and hints | `reference/diagnostics.md` |
| FAQ and Glossary | Terms and quick answers | `reference/faq-glossary.md` |
| Maintainers Guide | Integration patterns and release workflow | `MAINTAINERS_GUIDE.md` |

## A Tiny Example (The AST)

We explain the idea first, then show the code.

An AST is a tree that represents structure, not text. It is how RegexParser understands meaning.

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

> Once you see the tree, you can explain, validate, optimize, and secure the pattern.

## Where to Look Next

If you want to understand how the parser works internally, go to `ARCHITECTURE.md` and then `design/AST_TRAVERSAL.md`.

---

Previous: `../README.md` | Next: `QUICK_START.md`
