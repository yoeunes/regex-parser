# Backreferences, Subroutines, Conditionals

These features make regexes powerful but are also the hardest to read and the
most likely to create performance issues. Use them deliberately.

## Backreferences

A backreference matches the same text previously captured.

```php
// Match doubled words: "word word"
preg_match('/\b(\w+)\s+\1\b/', 'hello hello');
```

Named backreferences:

```php
preg_match('/(?<word>\w+)\s+\k<word>/', 'hello hello');
```

## Subroutines and recursion

Subroutines let you reuse a group; recursion lets a pattern call itself.

```php
// Balanced parentheses (recursive)
$pattern = '/\((?:[^()]|(?R))*\)/';
```

## Conditional groups

```php
// If group 1 matched, require 'b', else 'c'
$pattern = '/(a)?(?(1)b|c)/';
```

## Good choice vs bad choice

Good: use backreferences for clear, simple constraints.
```php
'/\b(\w+)\s+\1\b/'
```

Bad: deep recursion without limits on untrusted input.
```php
'/\((?:[^()]|(?R))*\)/'
```

If you need nested structures in production, consider a parser instead of regex.

## With RegexParser

```php
use RegexParser\Regex;

$regex = Regex::create();
$result = $regex->validate('/\b(\w+)\s+\1\b/');
```

---

Previous: [Lookarounds and Assertions](06-lookarounds.md) | Next: [Performance and ReDoS](08-performance-redos.md)
