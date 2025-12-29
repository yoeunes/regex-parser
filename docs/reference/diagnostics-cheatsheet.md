# Diagnostics Cheat Sheet

This is the fast path: what an issue means and how to fix it. We use RegexParser examples instead of raw `preg_*` calls so you can drop them directly into tooling.

## Quick Fix Index

| Diagnostic | Quick Fix |
| --- | --- |
| [Lookbehind is unbounded](#lookbehind-is-unbounded) | Add bounds or rewrite |
| [Backreference to non-existent group](#backreference-to-non-existent-group) | Check group numbers/names |
| [Duplicate group name](#duplicate-group-name) | Use unique names or `(?J)` |
| [Invalid quantifier range](#invalid-quantifier-range) | Swap min/max |
| [Nested quantifiers detected](#nested-quantifiers-detected) | Use atomic/possessive |
| [Dot-star in repetition](#dot-star-in-repetition) | Make atomic or narrow |
| [Overlapping alternation branches](#overlapping-alternation-branches) | Order or refactor |
| [Redundant non-capturing group](#redundant-non-capturing-group) | Remove group |
| [Useless flag](#useless-flag) | Remove flag |
| [Invalid delimiter](#invalid-delimiter) | Use proper delimiter |

## Lookbehind is unbounded

Problem: PCRE requires lookbehinds to have a maximum length.

```php
use RegexParser\Regex;

// ERROR
Regex::create()->validate('/(?<=a+)b/');

// FIX 1: Bounded quantifier
Regex::create()->validate('/(?<=a{1,100})b/');

// FIX 2: Rewrite with a lookahead
Regex::create()->validate('/(?=(a+))b\1/');
```

## Backreference to non-existent group

Problem: The backreference points to a group that does not exist yet.

```php
use RegexParser\Regex;

// ERROR: \2 does not exist
Regex::create()->validate('/(\w+)\2/');

// FIX 1: Use the correct group number
Regex::create()->validate('/(\w+)\1/');

// FIX 2: Add the missing group
Regex::create()->validate('/(\w+)(\s*)\2/');

// Named backreference example
Regex::create()->validate('/(?<name>\w+)\k<name>/');
```

## Duplicate group name

Problem: Named groups must be unique unless `(?J)` is set.

```php
use RegexParser\Regex;

// ERROR
Regex::create()->validate('/(?<id>\w+)(?<id>\d+)/');

// FIX 1: Unique names
Regex::create()->validate('/(?<word>\w+)(?<number>\d+)/');

// FIX 2: Allow duplicates with J
Regex::create()->validate('/(?J)(?<id>\w+)(?<id>\d+)/');
```

## Invalid quantifier range

Problem: Minimum exceeds maximum.

```php
use RegexParser\Regex;

// ERROR
Regex::create()->validate('/\d{5,2}/');

// FIX
Regex::create()->validate('/\d{2,5}/');
```

## Nested quantifiers detected

Problem: Nested variable quantifiers can explode backtracking.

```php
use RegexParser\Regex;

// RISKY
Regex::create()->redos('/(a+)+b/');

// FIX 1: Atomic group
Regex::create()->validate('/(?>a+)+b/');

// FIX 2: Possessive quantifier
Regex::create()->validate('/(a++)+b/');

// FIX 3: Simplify
Regex::create()->validate('/a+b/');
```

## Dot-star in repetition

Problem: `.*` inside `+` or `*` is a common ReDoS hotspot.

```php
use RegexParser\Regex;

// RISKY
Regex::create()->redos('/(?:.*)+x/');

// FIX 1: Possessive
Regex::create()->validate('/(?:.*+)x/');

// FIX 2: Narrow the class
Regex::create()->validate('/(?:[^x]*)x/');
```

## Overlapping alternation branches

Problem: One branch is a prefix of another inside repetition.

```php
use RegexParser\Regex;

// RISKY
Regex::create()->redos('/(a|aa)+b/');

// FIX 1: Atomic group
Regex::create()->validate('/(?>a|aa)+b/');

// FIX 2: Simplify
Regex::create()->validate('/a+b/');
```

## Redundant non-capturing group

Problem: The group does not change precedence or capture behavior.

```php
use RegexParser\Regex;

// WARNING
Regex::create()->validate('/(?:foo)/');

// FIX
Regex::create()->validate('/foo/');
```

## Useless flag

Problem: The flag has no effect on the pattern.

```php
use RegexParser\Regex;

// WARNING: DotAll flag does nothing
Regex::create()->validate('/^\d+$/s');

// FIX
Regex::create()->validate('/^\d+$/');
```

## Invalid delimiter

Problem: Pattern is missing a valid delimiter.

```php
use RegexParser\Regex;

// ERROR
Regex::create()->validate('^foo$');

// FIX
Regex::create()->validate('/^foo$/');
```

---

Previous: `diagnostics.md` | Next: `faq-glossary.md`
