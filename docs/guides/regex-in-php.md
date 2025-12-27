# Regex in PHP (preg_* fundamentals)

This guide explains how regex works in PHP, with the details that matter for
PCRE2 and real-world applications. It complements the tutorial by focusing on
PHP-specific behavior and pitfalls.

## Delimiters

PHP regex patterns use delimiters:

```php
$pattern = '/hello/i';
$pattern = '#hello#i';
```

Pick a delimiter that does not appear in your pattern. If it does, escape it:

```php
$pattern = '#https?://example\.com#';
```

## Pattern modifiers (flags)

Common flags:
- `i` case-insensitive
- `m` multiline (`^` and `$` match line boundaries)
- `s` dot matches newlines
- `u` Unicode mode
- `x` extended mode (ignore whitespace and comments)

Less common but useful:
- `A` anchor to start (like `\A` for the whole pattern)
- `D` dollar matches end only (like `\z`)
- `U` ungreedy by default

## Core functions

```php
preg_match('/\d+/', 'Order 42');
preg_match_all('/\w+/', 'a b c', $matches);
preg_replace('/\s+/', '-', 'hello world');
preg_split('/,\s*/', 'a, b, c');
preg_grep('/^admin/', $users);
```

Use `preg_quote()` when inserting user input into patterns:

```php
$needle = '+(test)';
$pattern = '/'.preg_quote($needle, '/').'/';
```

## Errors and diagnostics

When a pattern fails to compile or a match errors out, use:

```php
preg_last_error();
preg_last_error_msg();
```

RegexParser exposes similar diagnostics with caret snippets and hints:

```php
use RegexParser\Regex;

$regex = Regex::create(['runtime_pcre_validation' => true]);
$result = $regex->validate('/(?<=a+)b/');

if (!$result->isValid()) {
    echo $result->getErrorMessage();
    echo $result->getCaretSnippet();
    echo $result->getHint();
}
```

## Backtracking and limits

PCRE2 is a backtracking engine. For untrusted input, always combine safe
patterns with engine limits:

- `pcre.backtrack_limit`
- `pcre.recursion_limit`

These can be set in `php.ini` or at runtime:

```php
ini_set('pcre.backtrack_limit', '1000000');
ini_set('pcre.recursion_limit', '100000');
```

Use RegexParser's ReDoS analyzer to spot risky patterns early.

## Unicode gotchas

- Use the `u` flag when matching Unicode text.
- `\p{...}` properties require `u`.
- Normalization matters. Two visually identical strings may not be byte-equal.

If you work with user-generated content, consider normalizing input before
matching.

## Where RegexParser helps

- Validate patterns before they hit production
- Explain regexes in human language for reviews and docs
- Detect ReDoS risks and suggest safer rewrites

---

Previous: [Docs Home](../README.md) | Next: [CLI Guide](cli.md)
