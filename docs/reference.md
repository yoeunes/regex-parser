# Regex Parser Rule Reference

Regex Parser ships with a PHPStan rule (`RegexParser\Bridge\PHPStan\PregValidationRule`) that validates `preg_*` patterns for syntax issues, ReDoS risk, and common footguns. This page explains every diagnostic and links to authoritative references so you understand both the warning and how to fix it.

## How to read this page
- **Identifier:** matches the PHPStan rule identifier.
- **When it triggers:** the concrete condition used by the rule.
- **Fix it:** minimal change that removes the warning while keeping intent clear.
- **Proof:** links to PHP.net, the PCRE2 manual, or security guidance that describe the underlying rule.

---

## Flags

### Useless Flag 's' (DOTALL)
**Identifier:** `regex.linter`

**When it triggers:** the pattern sets the `s` (DotAll) modifier but contains no dot tokens (`.`). DotAll only changes how the dot behaves, so without a dot it has no effect.

**Fix it:** drop the flag or introduce a dot intentionally.

```php
// Warning: DotAll does nothing because there is no dot
preg_match('/^user_id:\\d+$/s', $input);

// Preferred
preg_match('/^user_id:\\d+$/', $input);
```

**Proof**
- [PHP: Pattern Modifiers (`s` / DOTALL)](https://www.php.net/manual/en/reference.pcre.pattern.modifiers.php)
- [PCRE2: Pattern Reference (inline modifiers such as `(?s)`)](https://www.pcre.org/current/doc/html/pcre2pattern.html)

---

### Useless Flag 'm' (Multiline)
**Identifier:** `regex.linter`

**When it triggers:** the pattern sets the `m` (multiline) modifier but contains no start/end anchors (`^` or `$`). Multiline mode only changes how those anchors behave.

**Fix it:** remove the flag when you only need a single-line match, or add explicit anchors if you truly want per-line matching.

```php
// Warning: Multiline mode is unused because there are no anchors
preg_match('/search_term/m', $text);

// Preferred
preg_match('/search_term/', $text);
```

**Proof**
- [PHP: Pattern Modifiers (`m` / MULTILINE)](https://www.php.net/manual/en/reference.pcre.pattern.modifiers.php)
- [Regular-Expressions.info: Anchors](https://www.regular-expressions.info/anchors.html)

---

### Useless Flag 'i' (Caseless)
**Identifier:** `regex.linter`

**When it triggers:** the pattern sets the `i` (case-insensitive) modifier but the regex contains no case-sensitive characters (only digits, symbols, or whitespace).

**Fix it:** drop the flag when matching digits/symbols, or keep the flag and add explicit letters if the pattern should be case-insensitive.

```php
// Warning: No letters to justify case-insensitive matching
preg_match('/^\\d{4}-\\d{2}-\\d{2}$/i', $date);

// Preferred
preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $date);
```

**Proof**
- [PHP: Pattern Modifiers (`i` / CASELESS)](https://www.php.net/manual/en/reference.pcre.pattern.modifiers.php)
- [PCRE2: Case Sensitivity and Inline Modifiers](https://www.pcre.org/current/doc/html/pcre2pattern.html)

---

## Security (ReDoS)

### Catastrophic Backtracking
**Identifier:** `regex.redos.critical`, `regex.redos.high`, `regex.redos.medium`, `regex.redos.low`

**When it triggers:** the ReDoS analyzer detects nested quantifiers or overlapping alternatives that can explode backtracking time on non-matching inputs.

**Fix it:** make the ambiguous part atomic or possessive, or refactor to avoid ambiguous repetition.

```php
// Vulnerable: `(a+)+` can backtrack exponentially
preg_match('/(a+)+$/', $input);

// Safer: atomic group
preg_match('/(?>a+)+$/', $input);

// Safer: possessive quantifier
preg_match('/(a++)+$/', $input);
```

**Proof**
- [OWASP: Regular Expression Denial of Service](https://owasp.org/www-community/attacks/Regular_expression_Denial_of_Service_-_ReDoS)
- [PCRE2: Performance Considerations](https://www.pcre.org/current/doc/html/pcre2perform.html)
- [PHP: Backtracking Control and Limits](https://www.php.net/manual/en/regexp.reference.backtrack-control.php)

---

## Advanced Syntax

### Possessive Quantifiers
**Identifier:** `regex.optimization`

**What they are:** quantifiers with a trailing `+` (`*+`, `++`, `?+`, `{m,n}+`) that consume text without ever backtracking.

**When to use:** when the consumed text should not be reconsidered, especially near ambiguous alternation, to prevent backtracking blowups.

```php
// Greedy: may backtrack heavily
preg_match('/".*"/', $input);

// Possessive: consumes once and fails fast
preg_match('/".*+"/', $input);
```

**Proof**
- [PHP: Repetition and Quantifiers](https://www.php.net/manual/en/regexp.reference.repetition.php)
- [PCRE2: Possessive Quantifiers](https://www.pcre.org/current/doc/html/pcre2pattern.html#SEC11)

---

### Atomic Groups
**Identifier:** `regex.optimization`

**What they are:** groups of the form `(?>...)` that disallow backtracking into their contents once matched.

**When to use:** to isolate a part of the pattern that must match exactly once, preventing exponential retries when the rest of the pattern fails.

```php
// Risky: catastrophic backtracking on repeated `a`
preg_match('/(a+)+!/', $input);

// Atomic: once inside the group matches, it cannot backtrack
preg_match('/(?>a+)+!/', $input);
```

**Proof**
- [PHP: Atomic Grouping `(?>...)`](https://www.php.net/manual/en/regexp.reference.onlyonce.php)
- [PCRE2: Atomic Groups](https://www.pcre.org/current/doc/html/pcre2pattern.html#SEC12)

---

### Assertions
**Identifier:** `regex.optimization`

**What they are:** zero-width lookarounds such as lookahead `(?=...)` / `(?!...)` and lookbehind `(?<=...)` / `(?<!...)` that assert context without consuming characters.

**When to use:** to enforce boundaries or context while keeping the main match focused, and to avoid inserting extra capturing groups solely for validation.

```php
// Lookahead: require a trailing digit without consuming it
preg_match('/^[A-Z]{2}(?=\\d$)/', $input);

// Lookbehind: ensure the match is preceded by "ID-"
preg_match('/(?<=ID-)\\d+/', $input);
```

**Proof**
- [PHP: Assertions (Lookahead/Lookbehind)](https://www.php.net/manual/en/regexp.reference.assertions.php)
- [PCRE2: Lookaround Assertions](https://www.pcre.org/current/doc/html/pcre2pattern.html#SEC23)
