# 01. Basics: Literal Matching

We start with the simplest regex: literal text. A regex is just a pattern language that describes strings.

> Before we run anything, we explain the idea. Then we show how RegexParser sees it.

## Literal Patterns

If you want to match "cat", your pattern is just `/cat/`.

```php
use RegexParser\Regex;

$regex = Regex::create();
$result = $regex->validate('/cat/');
```

## The Dot (Any Character)

`.` matches any single character (except newline unless the `s` flag is set).

Pattern: `/c.t/` matches `cat`, `cot`, `c9t`.

## Escaping Special Characters

Characters like `.`, `*`, `+`, `?`, `(`, `)`, `[`, `]`, `{`, `}`, `|`, `^`, `$` are special. Escape them with `\` to match literally.

Pattern: `/\./` matches a literal dot.

## See It With the CLI

```bash
bin/regex explain '/c.t/'
```

```
Match:
  Literal: "c"
  Any character
  Literal: "t"
```

```bash
bin/regex diagram '/c.t/'
```

```
RegexNode
+-- SequenceNode
    |-- LiteralNode("c")
    |-- DotNode(".")
    +-- LiteralNode("t")
```

## Exercises

1. Write a pattern that matches the word `hello`.
2. Write a pattern that matches `h.llo` with a literal dot.
3. Use `bin/regex explain` to see what your pattern means.

---

Previous: `README.md` | Next: `02-character-classes.md`
