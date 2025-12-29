# Regex Masterclass (RegexParser Edition)

We wrote this tutorial as a guided path. We start with intuition, then move toward structure, then use the AST to explain what the engine is doing.

> We will use RegexParser CLI and API throughout, so you learn regex and the parser at the same time.

## Learning Path

### Basics

1. `01-basics.md`
2. `02-character-classes.md`
3. `03-anchors-boundaries.md`

### Intermediate

4. `04-quantifiers.md`
5. `05-groups-alternation.md`
6. `06-lookarounds.md`

### Advanced

7. `07-backreferences-recursion.md`
8. `08-performance-redos.md`

### Internals and Applied

9. `09-testing-debugging.md`
10. `10-real-world-php.md`

After chapter 10, continue into internals: `docs/ARCHITECTURE.md` and `docs/design/AST_TRAVERSAL.md`.

## Tools We Use

### CLI (Explains and Diagrams)

```bash
bin/regex explain '/^cat.*dog$/'
```

```
Match:
  Anchor: start of string
  Literal: "cat"
  Any character, zero or more times
  Literal: "dog"
  Anchor: end of string
```

```bash
bin/regex diagram '/^cat.*dog$/'
```

```
RegexNode
+-- SequenceNode
    |-- AnchorNode("^")
    |-- LiteralNode("cat")
    |-- QuantifierNode("*")
    |   +-- DotNode(".")
    |-- LiteralNode("dog")
    +-- AnchorNode("$")
```

### PHP API

```php
use RegexParser\Regex;

$regex = Regex::create();
$ast = $regex->parse('/^cat.*dog$/');
$explanation = $regex->explain('/^cat.*dog$/');
```

## How to Use This Tutorial

- Read chapters in order if you are new.
- Use `bin/regex explain` to turn symbols into words.
- Use `bin/regex diagram` to see the AST shape.
- Keep a small scratch file so you can try variations.

---

Previous: `../README.md` | Next: `01-basics.md`
