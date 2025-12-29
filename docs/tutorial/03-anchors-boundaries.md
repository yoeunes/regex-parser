# 03. Anchors and Boundaries

Anchors let you describe *where* a match must occur, not just *what* it contains.

> Anchors do not consume characters. They assert positions.

## Start and End Anchors

- `^` matches the start of the string.
- `$` matches the end of the string.

Pattern: `/^cat$/` matches only the exact string `cat`.

```php
use RegexParser\Regex;

$regex = Regex::create();
$regex->validate('/^cat$/');
```

## Word Boundaries

`\b` matches the boundary between a word character and a non-word character.

Pattern: `/\bcat\b/` matches `cat` but not `concatenate`.

## See the AST

```bash
bin/regex diagram '/^cat$/'
```

```
RegexNode
+-- SequenceNode
    |-- AnchorNode("^")
    |-- LiteralNode("cat")
    +-- AnchorNode("$")
```

## Exercises

1. Match a line that starts with `ERROR`.
2. Match the word `id` as a whole word.

---

Previous: `02-character-classes.md` | Next: `04-quantifiers.md`
