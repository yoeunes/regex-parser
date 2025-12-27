# Real-World Patterns in PHP

This chapter focuses on real-world use cases, with practical constraints and
trade-offs. For audited patterns, see the [Cookbook](../COOKBOOK.md).

## Email (practical, not RFC-complete)

```php
$pattern = '/^[a-z0-9]+(?:[._%+-][a-z0-9]+)*+@[a-z0-9-]+(?:\.[a-z0-9-]+)++$/i';
```

Good: anchored, bounded, uses possessive quantifiers.
Bad: overly strict RFC parsers (hard to maintain, easy to get wrong).

## Slugs

```php
$pattern = '/^[a-z0-9]+(?:-[a-z0-9]+)*+$/';
```

## Dates (YYYY-MM-DD)

```php
$pattern = '/^(?<year>\d{4})-(?<month>0[1-9]|1[0-2])-(?<day>0[1-9]|[12][0-9]|3[01])$/';
```

## Log parsing (named groups)

```php
$pattern = '/^(?<level>INFO|WARN|ERROR)\s+(?<code>\d+)\s+(?<message>.+)$/';
```

Good: named groups for clarity.
Bad: `.+` without anchors (matches too much).

## With RegexParser

```php
use RegexParser\Regex;

$regex = Regex::create();
$analysis = $regex->validate($pattern);
```

---

Previous: [Testing and Debugging with RegexParser](09-testing-debugging.md) | Next: [Docs Home](../README.md)
