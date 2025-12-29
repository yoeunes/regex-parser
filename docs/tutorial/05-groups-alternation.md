# 05. Groups and Alternation

Groups let you treat multiple tokens as one unit and capture matches. Alternation (`|`) lets you choose between branches.

> Groups control structure. Alternation creates branching in the AST.

## Capturing vs Non-Capturing

- Capturing: `(abc)`
- Non-capturing: `(?:abc)`

Named capturing groups: `(?<name>abc)`

```php
use RegexParser\Regex;

$regex = Regex::create();
$regex->validate('/(?<year>\d{4})-(?<month>\d{2})/');
```

## Alternation

`foo|bar` means "match foo OR bar".

```bash
bin/regex diagram '/foo|bar/'
```

```
RegexNode
+-- AlternationNode
    |-- SequenceNode -> LiteralNode("foo")
    +-- SequenceNode -> LiteralNode("bar")
```

## Exercises

1. Match either `cat` or `dog`.
2. Capture a year and month from `2024-01`.

---

Previous: `04-quantifiers.md` | Next: `06-lookarounds.md`
