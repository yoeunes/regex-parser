# Regex in PHP (Through the Lens of RegexParser)

PHP uses PCRE2 for `preg_*` functions. In this guide we explain PHP regex behavior, but we always show how RegexParser sees the same patterns so you can debug and validate with confidence.

> If you know PHP but not regex, start with `docs/tutorial/README.md` first.

## The Shape of a PHP Regex

A PHP regex is a single string with a delimiter, pattern body, and flags.

```
Pattern: /^[a-z]+@[a-z]+\.[a-z]+$/i

/  ^[a-z]+@[a-z]+\.[a-z]+$  /  i
^  pattern body               ^  flags
|  delimiter                  |
```

RegexParser extracts the body and flags the same way PHP does.

```php
use RegexParser\Regex;

$ast = Regex::create()->parse('/^[a-z]+@[a-z]+\.[a-z]+$/i');
```

## The preg_* Family (Quick Map)

| Function | Purpose |
| --- | --- |
| `preg_match` | First match |
| `preg_match_all` | All matches |
| `preg_replace` | Replace |
| `preg_split` | Split |
| `preg_grep` | Filter array |

We can validate any pattern we plan to pass into these functions with RegexParser.

```php
$result = Regex::create()->validate('/^user-[0-9]+$/');
```

## Flags (Modifiers)

Flags change how the engine interprets the pattern.

| Flag | Meaning |
| --- | --- |
| `i` | Case-insensitive |
| `m` | Multiline anchors |
| `s` | Dot matches newline |
| `u` | Unicode |
| `x` | Extended (ignore whitespace) |

RegexParser stores flags on the root node:

```php
$ast = Regex::create()->parse('/hello/i');
$flags = $ast->flags; // "i"
```

## Delimiters

If your pattern includes `/`, choose a different delimiter like `#` or `~`.

```php
Regex::create()->validate('#https?://example\.com#');
```

> This is the most common reason a pattern fails to parse in PHP.

## Unicode and PCRE2

PHP uses PCRE2, so Unicode properties such as `\p{L}` are supported when the `u` flag is set.

```php
Regex::create()->validate('/^\p{L}+$/u');
```

## A Safe Workflow for PHP Regex

1. Validate with RegexParser.
2. Check ReDoS risk.
3. Add runtime limits where needed.

```php
$regex = Regex::create(['runtime_pcre_validation' => true]);

$result = $regex->validate('/(a+)+$/');
$analysis = $regex->redos('/(a+)+$/');
```

## Common Pitfalls

| Pitfall | Fix |
| --- | --- |
| Missing delimiter | Use `/pattern/` or `#pattern#` |
| Unbounded lookbehind | Add a max length or rewrite |
| Backreference before group | Move the backreference |
| Nested quantifiers | Use atomic or possessive |

---

Previous: `../tutorial/10-real-world-php.md` | Next: `cli.md`
