# 06. Lookarounds

Lookarounds assert context without consuming characters. They are powerful and also easy to misuse.

> Lookarounds are assertions. They check, then step back.

## Lookahead

- Positive: `(?=...)`
- Negative: `(?!...)`

Example: match `foo` only if followed by `bar`.

Pattern: `/foo(?=bar)/`

## Lookbehind

- Positive: `(?<=...)`
- Negative: `(?<!...)`

Example: match digits preceded by `ID-`.

Pattern: `/(?<=ID-)\d+/`

```php
use RegexParser\Regex;

$regex = Regex::create(['runtime_pcre_validation' => true]);
$result = $regex->validate('/(?<=ID-)\d+/');
```

> Lookbehinds must be bounded in PCRE. RegexParser will tell you when they are not.

## AST Node

Lookarounds are represented as `AssertionNode`.

---

Previous: `05-groups-alternation.md` | Next: `07-backreferences-recursion.md`
