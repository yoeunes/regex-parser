# Character Classes and Escapes

Character classes define a set of characters to match. They are the foundation
of robust, readable patterns.

## Basic character classes

```php
// One digit
preg_match('/[0-9]/', 'Order #7');

// One lowercase letter
preg_match('/[a-z]/', 'PHP');
```

Ranges are inclusive. You can combine them:

```php
preg_match('/[a-z0-9_]/', 'user_1');
```

## Negated classes

A leading `^` inside a class means "anything except":

```php
preg_match('/[^0-9]/', 'abc');
```

## Shorthand classes

```php
// Digits, word chars, whitespace
preg_match('/\d+/', '123');
preg_match('/\w+/', 'abc_123');
preg_match('/\s+/', "\n\t");
```

## Unicode properties

Use the `u` flag and `\p{...}` for Unicode-aware matches.

```php
preg_match('/^\p{L}+$/u', 'cafe');
```

## Good choice vs bad choice

Good: use explicit ranges or Unicode properties.
```php
// ASCII letters
'/^[A-Za-z]+$/'

// Any Unicode letter
'/^\p{L}+$/u'
```

Bad: use `[A-z]` (it includes `[\]^_` between `Z` and `a`).
```php
'/^[A-z]+$/'
```

## See the AST

```php
use RegexParser\Regex;

$regex = Regex::create();
$ast = $regex->parse('/^[a-z0-9_]+$/');
```

---

Previous: [Regex Basics](01-basics.md) | Next: [Anchors and Boundaries](03-anchors-boundaries.md)
