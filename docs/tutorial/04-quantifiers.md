# Quantifiers and Greediness

Quantifiers tell the engine how many times a token can repeat.

## Basics

- `?` zero or one
- `*` zero or more
- `+` one or more
- `{m,n}` between m and n times

```php
preg_match('/\d{4}-\d{2}-\d{2}/', '2024-01-01');
```

## Greedy vs lazy

Greedy quantifiers try to match as much as possible. Lazy quantifiers (`*?`, `+?`, `{m,n}?`)
try the smallest match first.

```php
preg_match('/".*"/', '"a" "b"');   // matches "a" "b"
preg_match('/".*?"/', '"a" "b"'); // matches "a"
```

## Possessive quantifiers

Possessive quantifiers (`*+`, `++`, `?+`, `{m,n}+`) do not backtrack once they match.
They are a performance tool when you want to prevent catastrophic backtracking.

```php
preg_match('/\w++\s+id/', 'user id');
```

## Good choice vs bad choice

Good: make the character class explicit.
```php
'/"[^"]*"/'
```

Bad: use `.*` when the domain is known.
```php
'/".*"/'
```

Good: avoid nested variable quantifiers.
```php
'/a+/'
```

Bad: nested quantifiers can trigger ReDoS.
```php
'/(a+)+$/'
```

## With RegexParser

```php
use RegexParser\Regex;
use RegexParser\ReDoS\ReDoSSeverity;

$regex = Regex::create();
$analysis = $regex->redos('/(a+)+$/');

if ($analysis->exceedsThreshold(ReDoSSeverity::HIGH)) {
    echo 'High risk';
}
```

---

Previous: [Anchors and Boundaries](03-anchors-boundaries.md) | Next: [Groups and Alternation](05-groups-alternation.md)
