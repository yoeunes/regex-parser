# Regex Parser Rules Reference

This guide explains the optimization suggestions and security warnings reported by the Regex Parser library.

---

## Optimizations

### Useless Flag 's' (DOTALL)
**Identifier:** `regex.linter`

The `s` modifier (PCRE_DOTALL) changes the behavior of the dot metacharacter `.` so that it matches **newlines** (which it doesn't do by default).

**Why it's an issue:**
If your pattern does not contain a dot `.`, enabling this flag adds internal overhead to the regex engine for absolutely no benefit. It confuses other developers reading your code who might look for a dot that isn't there.

**❌ Bad Practice**
```php
preg_match('/^user_id:\d+$/s', $input);
// The 's' flag is useless here, there is no '.' in the pattern.

```

**✅ Best Practice**

```php
preg_match('/^user_id:\d+$/', $input);

```

**Learn More:**

* [PHP Manual: DotAll Modifier](https://www.php.net/manual/en/reference.pcre.pattern.modifiers.php)
* [Regular-Expressions.info: Dot Matches Newline](https://www.regular-expressions.info/dot.html)

---

###Useless Flag 'm' (Multiline)**Identifier:** `regex.linter`

The `m` modifier (PCRE_MULTILINE) changes the behavior of anchors `^` (start) and `$` (end). By default, they match the start/end of the *string*. With `m`, they match the start/end of *each line*.

**Why it's an issue:**
If your pattern does not contain `^` or `$`, this flag does nothing. Even if it does, using `m` when you intend to match the whole string is a common logic bug.

**❌ Bad Practice**

```php
preg_match('/search_term/m', $text);
// Useless: No anchors (^ or $) are used.

```

**✅ Best Practice**

```php
preg_match('/search_term/', $text);

```

**Learn More:**

* [PHP Manual: Multiline Modifier](https://www.php.net/manual/en/reference.pcre.pattern.modifiers.php)
* [Regular-Expressions.info: Anchors](https://www.regular-expressions.info/anchors.html)

---

###Useless Flag 'i' (Caseless)**Identifier:** `regex.linter`

The `i` modifier makes the match case-insensitive (e.g., matching both `A` and `a`).

**Why it's an issue:**
If your pattern only contains characters that do not have case variants (numbers `0-9`, symbols `_@#`, or whitespace), this flag triggers the case-insensitivity logic unnecessarily.

**❌ Bad Practice**

```php
preg_match('/^\d{4}-\d{2}-\d{2}$/i', $date);
// Useless: Numbers do not have upper/lower case.

```

**✅ Best Practice**

```php
preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);

```

---

##Security (ReDoS)###Catastrophic Backtracking**Identifier:** `regex.redos.critical` / `regex.redos.high`

Your pattern contains **nested quantifiers** or overlapping alternatives that can cause exponential backtracking. This is a severe security vulnerability known as **ReDoS (Regular Expression Denial of Service)**.

**The Danger:**
A malicious user can craft a specific input string (often short, e.g., 50 chars) that forces the regex engine to calculate billions of paths, freezing your server CPU at 100%.

**❌ Vulnerable Pattern**

```php
// Matches strings like "aaaaaaaaaaaa!"
preg_match('/(a+)+$/', $input);

```

*If `$input` is "aaaaaaaaaaaaaaaaaaaa!" (20 'a's and a bang), this takes milliseconds. If the bang is missing, it can take minutes.*

**✅ Safe Alternatives**

1. **Atomic Groups `(?>...)**`: Discards backtracking positions once the group matches.
```php
preg_match('/(?>a+)+$/', $input);

```


2. **Possessive Quantifiers `++**`: Eats characters and never gives them back.
```php
preg_match('/(a++)+$/', $input);

```



**Learn More:**

* [OWASP: Regular Expression Denial of Service](https://owasp.org/www-community/attacks/Regular_expression_Denial_of_Service_-_ReDoS)
* [Regular-Expressions.info: Catastrophic Backtracking](https://www.regular-expressions.info/catastrophic.html)

---

##Advanced Syntax###Possessive Quantifiers**Identifier:** `regex.optimization`

Possessive quantifiers (like `*+`, `++`, `?+`) match as much as they can and **never backtrack**.

**When to use:**
Use them when you know that what has been matched should not be "given back" to try to match the rest of the pattern. They significantly improve performance and security.

**Comparison:**

* `.*` (Greedy): Matches until end, then backtracks one by one if the rest fails.
* `.*+` (Possessive): Matches until end, and fails immediately if the rest doesn't match.

**Learn More:**

* [PHP Manual: Repetition](https://www.php.net/manual/en/regexp.reference.repetition.php)

---

###Atomic Groups**Identifier:** `regex.optimization`

Atomic groups `(?>...)` are non-capturing groups that lock away their contents. Once the engine exits the group, it cannot backtrack into it.

**Example:**
Matching a quoted string without backtracking for every character:

```php
preg_match('/"(?>[^"\\\\]+|\\\\.)*"/', $input);

```

**Learn More:**

* [PHP Manual: Atomic Grouping](https://www.php.net/manual/en/regexp.reference.onlyonce.php)

---

###Assertions**Identifier:** `regex.optimization`

Assertions (Lookahead `(?=...)`, Lookbehind `(?<=...)`) match a position in the string without consuming characters.

* `(?=\d)`: "Assert that what follows is a digit".
* `(?<!\$)`: "Assert that what precedes is NOT a dollar sign".

**Learn More:**

* [PHP Manual: Assertions](https://www.php.net/manual/en/regexp.reference.assertions.php)
