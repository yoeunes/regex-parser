# Lookarounds and Assertions

Lookarounds are zero-width assertions. They check context without consuming
characters.

## Lookahead

```php
// Require trailing digits
preg_match('/^[A-Z]{2}(?=\d{2}$)/', 'AB12');
```

- `(?=...)` positive lookahead
- `(?!...)` negative lookahead

## Lookbehind

```php
// Match digits preceded by "ID-"
preg_match('/(?<=ID-)\d+/', 'ID-42');
```

- `(?<=...)` positive lookbehind
- `(?<!...)` negative lookbehind

In PCRE, lookbehind must have a bounded maximum length.

## Good choice vs bad choice

Good: use lookahead for suffix requirements without capturing.
```php
'/\w+(?=\.)/'
```

Bad: use unbounded lookbehind (invalid in PCRE).
```php
'/(?<=a+)b/'
```

## With RegexParser

```php
use RegexParser\Regex;

$regex = Regex::create(['runtime_pcre_validation' => true]);
$result = $regex->validate('/(?<=a+)b/');

if (!$result->isValid()) {
    echo $result->getErrorMessage();
}
```

---

Previous: [Groups and Alternation](05-groups-alternation.md) | Next: [Backreferences, Subroutines, Conditionals](07-backreferences-recursion.md)
