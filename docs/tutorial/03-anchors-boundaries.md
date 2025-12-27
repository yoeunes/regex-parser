# Anchors and Boundaries

Anchors let you say "where" a match must happen. They do not consume characters.

## Start and end anchors

- `^` start of subject (or line in `m` mode)
- `$` end of subject (or line in `m` mode)

```php
preg_match('/^user_\d+$/', 'user_42');
```

## Absolute anchors

- `\A` start of subject (never per-line)
- `\z` end of subject (never per-line)

Use these when you do not want `m` to change behavior.

```php
preg_match('/\A\d+\z/', '123');
```

## Word boundaries

`\b` matches a word boundary (between `\w` and non-`\w`).

```php
preg_match('/\bcat\b/', 'a cat sleeps');
```

## Good choice vs bad choice

Good: anchor input validation.
```php
'/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/i'
```

Bad: forget anchors in validation (allows partial matches).
```php
'/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i'
```

## With RegexParser

```php
use RegexParser\Regex;

$regex = Regex::create();
$result = $regex->validate('/^\w+$/');
```

---

Previous: [Character Classes and Escapes](02-character-classes.md) | Next: [Quantifiers and Greediness](04-quantifiers.md)
