# Regex Basics

Regex is a pattern language for matching text. In PHP, you typically use it with
`preg_match`, `preg_replace`, or `preg_split`. RegexParser helps you see the
structure of those patterns and validate them before they hit production.

## Delimiters and flags

PHP regexes are written as `/pattern/flags`.

```php
$pattern = '/hello/i'; // i = case-insensitive
preg_match($pattern, 'HeLLo');
```

Common flags:
- `i` case-insensitive
- `m` multiline (`^` and `$` match line boundaries)
- `s` dot matches newlines
- `u` Unicode mode
- `x` extended mode (ignore whitespace and comments)

## Literals and escaping

Most characters match themselves. Some characters are special and must be
escaped to match literally: `. ^ $ * + ? ( ) [ ] { } | \\`.

```php
preg_match('/\./', 'example.com'); // matches the dot
```

When building regexes from user input, use `preg_quote()`.

```php
$needle = 'a+b';
$pattern = '/'.preg_quote($needle, '/').'/';
```

## Your first match

```php
$pattern = '/cat/';
$subject = 'the cat sat';

if (preg_match($pattern, $subject)) {
    echo 'match';
}
```

## Good choice vs bad choice

Good: use a literal match when the pattern is fixed.
```php
preg_match('/^Status: OK$/', $line);
```

Bad: use regex when a simple string check would do.
```php
str_contains($line, 'Status: OK');
```

## See the structure with RegexParser

```php
use RegexParser\Regex;

$regex = Regex::create();
$ast = $regex->parse('/hello/i');

echo $regex->explain('/hello/i');
```

---

Previous: [Tutorial Home](README.md) | Next: [Character Classes and Escapes](02-character-classes.md)
