# 🔤 PCRE vs Other Engines

**PCRE** (Perl Compatible Regular Expressions) is the regex engine used by PHP's `preg_*` functions. Understanding PCRE helps you write better patterns and avoid compatibility issues.

## 🎯 What is PCRE?

PCRE is a regular expression engine that:

- Powers PHP's `preg_match()`, `preg_replace()`, etc.
- Is based on Perl's regex syntax
- Supports advanced features like lookarounds, recursion, and Unicode
- Uses backtracking for pattern matching

## 🔍 PCRE in PHP

### PHP's Regex Functions

```php
// PCRE functions in PHP
preg_match('/pattern/', $subject);      // Match pattern
preg_replace('/pattern/', 'replacement', $subject); // Replace
preg_split('/pattern/', $subject);      // Split by pattern
preg_match_all('/pattern/', $subject, $matches); // Find all matches
```

### PCRE Version in PHP

```php
// Check your PCRE version
echo PCRE_VERSION; // e.g., "10.42 2022-12-11"
```

## 🆚 PCRE vs Other Regex Engines

| Feature               | PCRE (PHP) | JavaScript | Python | .NET |
|-----------------------|------------|------------|--------|------|
| Lookaheads            | ✅ Yes      | ✅ Yes      | ✅ Yes  | ✅ Yes |
| Lookbehinds           | ✅ Yes      | ❌ No       | ✅ Yes  | ✅ Yes |
| Variable-length lookbehind | ❌ No | ❌ No | ❌ No | ✅ Yes |
| Recursion             | ✅ Yes      | ❌ No       | ❌ No   | ✅ Yes |
| Atomic groups         | ✅ Yes      | ❌ No       | ✅ Yes  | ✅ Yes |
| Possessive quantifiers| ✅ Yes      | ❌ No       | ❌ No   | ✅ Yes |
| Unicode properties    | ✅ Yes      | ✅ Yes      | ✅ Yes  | ✅ Yes |
| Named groups          | ✅ Yes      | ✅ Yes      | ✅ Yes  | ✅ Yes |
| Branch reset          | ✅ Yes      | ❌ No       | ❌ No   | ❌ No  |

## 🧩 PCRE-Specific Features

### 1. Recursion

```php
// Match balanced parentheses
$pattern = '/\((?:[^()]++|(?R))*\)/';
preg_match($pattern, '(a(b)c)', $matches);
```

### 2. Branch Reset

```php
// Reset capture numbering per branch
$pattern = '/(?|(a)|(b)|(c))+/';
preg_match($pattern, 'abc', $matches);
```

### 3. Atomic Groups

```php
// Prevent backtracking
$pattern = '/(?>a+)b/';
preg_match($pattern, 'aaaa!', $matches); // Fails quickly
```

### 4. Possessive Quantifiers

```php
// No backtracking
$pattern = '/a++b/';
preg_match($pattern, 'aaaa!', $matches); // Fails quickly
```

## 💡 PCRE Best Practices

### 1. Use Delimiters

```php
// Always include delimiters
$pattern = '/^hello$/i'; // Good
$pattern = '^hello$';     // Bad - missing delimiters
```

### 2. Specify Flags

```php
// Common flags
$pattern = '/hello/i';  // Case-insensitive
$pattern = '/hello/s';  // Dot matches newline
$pattern = '/hello/m';  // Multiline mode
$pattern = '/hello/u';  // Unicode mode
$pattern = '/hello/x';  // Extended (ignore whitespace)
```

### 3. Escape Special Characters

```php
// Escape regex metacharacters
$literal = preg_quote('user@input.com', '/');
$pattern = '/' . $literal . '/';
```

### 4. Use Raw Patterns

```php
// Use single quotes to avoid escaping
$pattern = '/\d{3}-\d{4}/'; // Good
$pattern = "/\d{3}-\d{4}/"; // Also works but harder to read
```

## 🔗 Related Concepts

- **[ReDoS Deep Dive](redos.md)** - PCRE's backtracking vulnerabilities
- **[Architecture](../ARCHITECTURE.md)** - How RegexParser handles PCRE
- **[Regex in PHP Guide](../guides/regex-in-php.md)** - PHP-specific regex details

## 📚 Further Reading

- [PCRE Documentation](https://www.pcre.org/) - Official PCRE docs
- [PHP Regex Functions](https://www.php.net/manual/en/book.pcre.php) - PHP manual
- [Regex101 PCRE Reference](https://regex101.com/) - Interactive tester

---

📖 **Previous**: [ReDoS Deep Dive](redos.md) | 🚀 **Next**: [Concepts Home](README.md)