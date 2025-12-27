# ReDoS Guide: Regular Expression Denial of Service

Regular Expression Denial of Service (ReDoS) happens when a regex engine spends
exponential time backtracking on certain inputs. This is usually triggered by
nested or overlapping quantifiers and is especially dangerous on untrusted input.

## What is catastrophic backtracking?

Most PCRE engines are backtracking-based. When a match fails late, the engine
retries earlier choices to see if a different split could succeed.

Example pattern:

```
/(a+)+b/
```

Example input:

```
aaaaaaaaaaaaaaaaa!
```

Why this explodes:

- The inner `a+` can match the input in many different ways.
- The outer `(...)+` repeats those choices.
- When the final `b` fails, the engine walks back through **all** combinations.

A simplified view of the backtracking tree:

```
input: a a a a a !
        ^^^^^
        split 1
        ^^^^ ^
        split 2
        ^^^ ^^
        split 3
        ...
```

The number of splits grows exponentially with input length.

## Vulnerable example

```php
use RegexParser\Regex;

$regex = Regex::create();
$analysis = $regex->redos('/(a+)+b/');

echo $analysis->severity->value; // critical
```

RegexParser detects nested unbounded quantifiers in the AST and flags this as a
high-risk pattern.

## How RegexParser detects it

RegexParser parses the pattern into an AST and analyzes structure rather than
running the regex. The ReDoS visitor looks for:

- Nested unbounded quantifiers (`+`, `*`, `{m,}`)
- Overlapping alternations inside repetition
- Backreferences that force backtracking
- Missing shielding constructs (atomic groups, possessive quantifiers)

This static approach is safe to run in CI and does not execute the regex.

## Fixing vulnerable patterns

### Option 1: Atomic groups

Atomic groups prevent the engine from backtracking inside the group:

```text
/(?>a+)b/
```

### Option 2: Possessive quantifiers

Possessive quantifiers do not give back characters once matched:

```text
/a++b/
```

### Option 3: Simplify the pattern

In many cases, `(a+)+` is equivalent to `a+`:

```text
/a+b/
```

RegexParser can suggest these fixes via optimization and linting rules.

---

Previous: [Reference](reference.md) | Next: [Cookbook](COOKBOOK.md)
