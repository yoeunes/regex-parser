# Performance and ReDoS

Backtracking engines try many paths when a match fails. Ambiguous patterns can
explode in time complexity. This is the root of ReDoS vulnerabilities.

## Common risk patterns

- Nested quantifiers: `(a+)+`
- Overlapping alternations: `(a|aa)+`
- Dot-star inside repetition: `(?:.*)+`

## Safer alternatives

- Use atomic groups `(?>...)`
- Use possessive quantifiers `++` `*+` `?+`
- Make branches mutually exclusive

```php
// Risky
'/(a+)+$/'

// Safer: atomic
'/(?>a+)+$/'

// Safer: possessive
'/(a++)+$/'
```

## Order alternations by length

```php
// Risky inside repetition (shorter branch first)
'/(c|cat)+/'

// Better (longer branch first)
'/(cat|c)+/'
```

## With RegexParser

```php
use RegexParser\Regex;
use RegexParser\ReDoS\ReDoSSeverity;

$regex = Regex::create();
$analysis = $regex->redos('/(a+)+$/');

if ($analysis->exceedsThreshold(ReDoSSeverity::HIGH)) {
    echo $analysis->severity->value;
}
```

CLI:

```bash
bin/regex analyze '/(a+)+$/'
```

---

Previous: [Backreferences, Subroutines, Conditionals](07-backreferences-recursion.md) | Next: [Testing and Debugging with RegexParser](09-testing-debugging.md)
