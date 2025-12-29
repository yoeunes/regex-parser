# 07. Backreferences and Recursion

Backreferences let you match the same text that was captured earlier. Recursion lets you reuse groups as subpatterns.

> These features increase expressiveness but also increase complexity.

## Backreferences

Pattern: `/^(\w+)\1$/` matches doubled words like `testtest`.

```php
use RegexParser\Regex;

$regex = Regex::create();
$regex->validate('/^(\w+)\1$/');
```

AST uses `BackrefNode` for these references.

## Subroutines and Recursion

Pattern: `/(?<paren>\((?:[^()]++|(?&paren))*\))/` matches balanced parentheses.

This uses recursion via `SubroutineNode`.

## Exercises

1. Match repeated words with a backreference.
2. Explore the AST of a recursive pattern with `bin/regex diagram`.

---

Previous: `06-lookarounds.md` | Next: `08-performance-redos.md`
