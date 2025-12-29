# 08. Performance and ReDoS

Regex performance is about structure. Ambiguous patterns create backtracking explosions.

> We can detect most of these problems statically with `Regex::redos()`.

## The Risk Pattern

`/(a+)+$/` looks small but can be catastrophic.

```php
use RegexParser\Regex;

$analysis = Regex::create()->redos('/(a+)+$/');
```

## CLI Check

```bash
bin/regex analyze '/(a+)+$/'
```

You will see a `CRITICAL` severity and a recommended fix.

## Safer Alternatives

- Atomic group: `/(?>a+)+$/`
- Possessive quantifier: `/(a++)+$/`
- Simplify: `/a+$/`

## Exercises

1. Run `bin/regex debug` on a risky pattern.
2. Rewrite it to be safe and re-run `bin/regex analyze`.

---

Previous: `07-backreferences-recursion.md` | Next: `09-testing-debugging.md`
