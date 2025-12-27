# Groups and Alternation

Groups control precedence, capture matches, and express sub-structure. Alternation
(`|`) lets you match one of several branches.

## Capturing vs non-capturing groups

```php
preg_match('/(cat|dog)/', 'a dog', $m);
// $m[1] = 'dog'
```

If you do not need capture, use non-capturing `(?:...)`.

```php
preg_match('/(?:cat|dog)/', 'a dog');
```

## Named groups

```php
preg_match('/(?<user>\w+)@(?<host>\w+)/', 'alice@example', $m);
// $m['user'] = 'alice'
```

## Alternation and precedence

Alternation has low precedence; group it when you need it.

```php
// Matches: 'foo' OR 'barbaz'
'foo|barbaz'

// Matches: 'foobaz' OR 'barbaz'
'(?:foo|bar)baz'
```

## Good choice vs bad choice

Good: use non-capturing groups for structure.
```php
'/^(?:get|post|put|delete)$/i'
```

Bad: capture every group by default.
```php
'/^(get|post|put|delete)$/i'
```

## With RegexParser

```php
use RegexParser\Regex;

$regex = Regex::create();
$ast = $regex->parse('/(?<user>\w+)@(?<host>\w+)/');
```

---

Previous: [Quantifiers and Greediness](04-quantifiers.md) | Next: [Lookarounds and Assertions](06-lookarounds.md)
