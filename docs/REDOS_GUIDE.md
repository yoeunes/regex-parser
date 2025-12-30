# ReDoS Guide

ReDoS (Regular Expression Denial of Service) happens when a regex takes exponential time to match certain inputs. This guide explains the risky shapes, how RegexParser detects them, and how to fix them.

## What Is ReDoS?

Most PCRE engines use backtracking. When a pattern has multiple ways to match the same input, the engine may explore a huge number of paths before it can fail or succeed.

Example:

```
Pattern: /(a+)+b/
Input:   "aaaaa!"
```

`(a+)` can consume 1..n characters, and the outer `+` can repeat the group 1..n times. When the final `b` fails, the engine tries many combinations of those choices.

## Risky Pattern Shapes

RegexParser focuses on structural patterns that cause backtracking blowups:

- Nested unbounded quantifiers: `(a+)+`, `(.*)*`
- Overlapping alternation inside repetition: `(a|aa)+`
- Backreference loops inside repetition: `(\w+)\1+`
- Very large bounded repeats: `{1,10000}` (lower severity but still slow)

These are not always unsafe, but they are the common sources of catastrophic backtracking.

## How RegexParser Detects ReDoS

RegexParser analyzes the AST without executing the pattern:

```
/pattern/flags
  |
  v
Lexer -> Parser -> RegexNode
                   |
                   v
             ReDoSProfileNodeVisitor
                   |
                   v
             ReDoSAnalysis (severity, findings, hints)
```

Key heuristics in `ReDoSProfileNodeVisitor` include:

- Star-height detection (nested unbounded quantifiers)
- Alternation overlap detection via `CharSetAnalyzer`
- Backreference loops combined with repetition
- Atomic groups and possessive quantifiers reducing severity

## Using RegexParser

### CLI

```bash
bin/regex analyze '/(a+)+$/'
```

### PHP

```php
use RegexParser\Regex;

$analysis = Regex::create()->redos('/(a+)+b/');
echo $analysis->severity->value;
```

## Fixing Vulnerable Patterns

### Use possessive quantifiers

```
Vulnerable: /(a+)+b/
Safer:      /a++b/
```

Possessive quantifiers do not backtrack.

### Use atomic groups

```
Vulnerable: /(a+)+b/
Safer:      /(?>a+)b/
```

Atomic groups commit to the first successful match inside the group.

### Simplify nested repeats

```
Vulnerable: /(a+)+b/
Equivalent: /a+b/
```

### Prefer character classes over alternation

```
Vulnerable: /(a|b)+c/
Safer:      /[ab]+c/
```

### Bound your repeats

```
Vulnerable: /(\d+)+/
Safer:      /\d{1,10}/
```

## Quick Reference: Bad vs Better

```
(a+)+        -> a++        or (?>a+)
(a|aa)+      -> a+
(\d+)+       -> \d++       or \d{1,10}
(.+)+        -> .++        or .{1,100}
(a|b)+       -> [ab]+
(\w+\d+)+    -> (?>\w+\d+)+
```

## Defense in Depth

- Validate patterns before they reach production.
- Use input length limits when matching against untrusted data.
- Prefer deterministic patterns in hot paths.

---

Previous: [Cookbook](COOKBOOK.md) | Next: [Architecture](ARCHITECTURE.md)
