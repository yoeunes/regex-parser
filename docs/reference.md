# RegexParser Rule Reference

RegexParser ships with a PHPStan rule (`RegexParser\\Bridge\\PHPStan\\PregValidationRule`) that validates `preg_*` patterns for syntax issues, ReDoS risk, and common performance or readability footguns. This page explains each diagnostic in plain language and links to authoritative references so you understand both the warning and how to fix it.

If you are new to PCRE-style regular expressions in PHP, you can treat this document as a guided tour of the most common mistakes the rule can detect.

## How to read this page
- **Identifier:** matches the PHPStan rule identifier.
- **When it triggers:** the concrete condition used by the rule.
- **Fix it:** minimal change that removes the warning while keeping the intent of the pattern clear.
- **Read more:** links to PHP.net, the PCRE2 manual, or security guidance that describe the underlying rule in depth.

For caret snippets, hints, and error-code semantics, see
[docs/reference/diagnostics.md](reference/diagnostics.md).

---

## PCRE2 Compatibility Contract

RegexParser targets **PHP’s PCRE2 engine** (`preg_*`). Validation is layered:

- **Parse**: lexer + parser build a PCRE-aware AST.
- **Semantic validation**: checks common PCRE rules (group references, branch reset numbering, lookbehind boundedness, Unicode ranges, …).
- **PCRE runtime validation**: optional compile check via `preg_match($regex, '')` (enable with `runtime_pcre_validation`) and report failures as `pcre-runtime`.

Key behaviors that may surprise users coming from “single-pass” validators:

- **Forward references are allowed** (e.g. `/\1(a)/`, `/\k<name>(?<name>a)/` compile in PHP).
- **Branch reset groups** `(?|...)` change capture numbering; `\2` can be invalid even if two captures appear in different branches.
- **`\g{0}` is invalid** in PHP; use `\g<0>` or `(?R)` for recursion.
- **Lookbehind must have a bounded maximum length** (unless adjusted via `(*LIMIT_LOOKBEHIND=...)`).

This reference documents RegexParser diagnostics, not the full PCRE2 spec. If you find a pattern that PHP accepts but RegexParser rejects, please open an issue with a minimal repro.

## Flags

### Useless Flag 's' (DOTALL)
**Identifier:** `regex.lint.flag.useless.s`

**When it triggers:** the pattern sets the `s` (DotAll) modifier but contains no dot tokens (`.`). DotAll only changes how the dot behaves, so without a dot it has no effect.

**Fix it:** drop the flag or introduce a dot intentionally.

```php
// Warning: DotAll does nothing because there is no dot
preg_match('/^user_id:\\d+$/s', $input);

// Preferred
preg_match('/^user_id:\\d+$/', $input);
```

**Read more**
- [PHP: Pattern Modifiers (`s` / DOTALL)](https://www.php.net/manual/en/reference.pcre.pattern.modifiers.php)
- [PCRE2: Pattern Reference (inline modifiers such as `(?s)`)](https://www.pcre.org/current/doc/html/pcre2pattern.html)

---

### Useless Flag 'm' (Multiline)
**Identifier:** `regex.lint.flag.useless.m`

**When it triggers:** the pattern sets the `m` (multiline) modifier but contains no start/end anchors (`^` or `$`). Multiline mode only changes how those anchors behave.

**Fix it:** remove the flag when you only need a single-line match, or add explicit anchors if you truly want per-line matching.

```php
// Warning: Multiline mode is unused because there are no anchors
preg_match('/search_term/m', $text);

// Preferred
preg_match('/search_term/', $text);
```

**Read more**
- [PHP: Pattern Modifiers (`m` / MULTILINE)](https://www.php.net/manual/en/reference.pcre.pattern.modifiers.php)
- [Regular-Expressions.info: Anchors](https://www.regular-expressions.info/anchors.html)

---

### Useless Flag 'i' (Caseless)
**Identifier:** `regex.lint.flag.useless.i`

**When it triggers:** the pattern sets the `i` (case-insensitive) modifier but the regex contains no case-sensitive characters (only digits, symbols, or whitespace).

**Fix it:** drop the flag when matching digits/symbols, or keep the flag and add explicit letters if the pattern should be case-insensitive.

```php
// Warning: No letters to justify case-insensitive matching
preg_match('/^\\d{4}-\\d{2}-\\d{2}$/i', $date);

// Preferred
preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $date);
```

**Read more**
- [PHP: Pattern Modifiers (`i` / CASELESS)](https://www.php.net/manual/en/reference.pcre.pattern.modifiers.php)
- [PCRE2: Case Sensitivity and Inline Modifiers](https://www.pcre.org/current/doc/html/pcre2pattern.html)

---

## Anchors

### Anchor Conflicts
**Identifier:** `regex.lint.anchor.impossible.start`, `regex.lint.anchor.impossible.end`

**When it triggers:**

- `^` appears after consuming tokens (and `m` is not enabled), so it can no longer match “start of subject”.
- `$` appears before consuming tokens, so it asserts end-of-subject too early.

**Fix it:** move anchors to the correct position, or enable multiline mode intentionally.

**Read more**
- [PHP: Anchors `^` and `$`](https://www.php.net/manual/en/regexp.reference.anchors.php)

---

## Quantifiers

### Nested Quantifiers
**Identifier:** `regex.lint.quantifier.nested`

**When it triggers:** a variable quantifier wraps another variable quantifier (e.g. `(a+)+`). This is a classic catastrophic backtracking shape.

**Fix it:** refactor to be deterministic, or use atomic groups / possessive quantifiers.

**Read more**
- [OWASP: ReDoS](https://owasp.org/www-community/attacks/Regular_expression_Denial_of_Service_-_ReDoS)
- [PHP: Atomic groups `(?>...)`](https://www.php.net/manual/en/regexp.reference.onlyonce.php)

---

### Dot-Star in Quantifier
**Identifier:** `regex.lint.dotstar.nested`

**When it triggers:** an unbounded quantifier wraps a dot-star (e.g. `(?:.*)+`). This can cause extreme backtracking on non-matching input.

**Fix it:** make it atomic/possessive or replace `.*` with a more specific class.

**Read more**
- [PCRE2: Performance considerations](https://www.pcre.org/current/doc/html/pcre2perform.html)

---

## Groups

### Redundant Non-Capturing Group
**Identifier:** `regex.lint.group.redundant`

**When it triggers:** a non-capturing group `(?:...)` wraps a single atomic token and does not change precedence.

**Fix it:** remove the group.

---

## Alternation

### Duplicate Alternation Branches
**Identifier:** `regex.lint.alternation.duplicate`

**When it triggers:** the same literal branch appears more than once (e.g. `(a|a)`).

**Fix it:** remove duplicates; consider a character class when appropriate (e.g. `(a|b)` → `[ab]`).

---

### Overlapping Alternation Branches
**Identifier:** `regex.lint.alternation.overlap`

**When it triggers:** one literal alternative is a prefix of another (e.g. `(a|aa)`). Inside repetition this is a common ReDoS trigger.

**Fix it:** order longer branches first or make the alternation atomic.

**Read more**
- [OWASP: ReDoS](https://owasp.org/www-community/attacks/Regular_expression_Denial_of_Service_-_ReDoS)

### Overlapping Character Sets in Alternations
**Identifier:** `regex.lint.overlap.charset`

**When it triggers:** alternation branches have overlapping character sets (e.g. `[a-c]|[b-d]`). This may cause unnecessary backtracking.

**Fix it:** consider reordering alternatives or using atomic groups to improve performance.

**Read more**
- [OWASP: ReDoS](https://owasp.org/www-community/attacks/Regular_expression_Denial_of_Service_-_ReDoS)

---

## Character Classes

### Redundant Character Class Elements
**Identifier:** `regex.lint.charclass.redundant`

**When it triggers:** a character class contains redundant literals or overlapping ranges (e.g. `[a-zA-Za-z]`).

**Fix it:** remove duplicates or merge ranges.

---

## Escapes

### Suspicious Escapes
**Identifier:** `regex.lint.escape.suspicious`

**When it triggers:** escapes that are likely typos or out-of-range values (e.g. `\x{110000}`).

**Fix it:** correct the codepoint or use a valid escape for your target engine.

**Read more**
- [PHP: Character escapes](https://www.php.net/manual/en/regexp.reference.escape.php)

---

## Inline Flags

### Inline Flag Redundant
**Identifier:** `regex.lint.flag.redundant`

**When it triggers:** an inline flag sets/unsets a modifier that is already in the desired state given the surrounding flags.

**Fix it:** remove redundant inline flags to improve readability.

---

### Inline Flag Override
**Identifier:** `regex.lint.flag.override`

**When it triggers:** an inline flag explicitly unsets a global modifier (e.g. `/(?-i:foo)/i`).

**Fix it:** consider scoping the global modifier instead, or document why the override is needed.

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

**Read more**
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

**Read more**
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

**Read more**
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

**Read more**
- [PHP: Assertions (Lookahead/Lookbehind)](https://www.php.net/manual/en/regexp.reference.assertions.php)
- [PCRE2: Lookaround Assertions](https://www.pcre.org/current/doc/html/pcre2pattern.html#SEC23)

---

## Compatibility & Limitations

### Supported PHP Versions
- PHP 8.2 and above (uses readonly classes and enum features).

### Target Engine
- **PCRE2** via PHP's `preg_*` functions.
- Notes on differences from PCRE1 or other engines:
  - Full Unicode support with `\p{...}` and `\P{...}`.
  - `\g{0}` is invalid in PHP; use `\g<0>` or `(?R)` for recursion.
  - Branch reset groups `(?|...)` fully implemented with correct capture numbering.

### Known Not-Supported Constructs
- None by v1.0; aims for full PCRE2 compliance.
- If you encounter a pattern that PHP accepts but RegexParser rejects, please report it.

---

## Diagnostics Catalog

| Error Code | Message Template | Meaning | Fix Example |
|------------|------------------|---------|-------------|
| `regex.backref.missing_group` | Backreference to non-existent group: "{ref}" | A backreference points to a group number or name that doesn't exist. | Change `\2` to `\1` if only one group exists. |
| `regex.backref.missing_named_group` | Backreference to non-existent named group: "{name}" | Named backreference refers to a group not defined in the pattern. | Ensure `(?<name>...)` precedes `\k<name>`. |
| `regex.backref.zero` | Backreference \0 is not valid. | `\0` is not a valid backreference in PCRE. | Use `\g<0>` for whole-pattern recursion. |
| `regex.group.duplicate_name` | Duplicate group name "{name}" at position {pos}. | Named groups must have unique names unless J flag is set. | Use different names or add `(?J)` for duplicate names. |
| `regex.quantifier.invalid_range` | Invalid quantifier range "{quant}": min > max. | Quantifier minimum exceeds maximum. | Fix `{3,2}` to `{2,3}`. |
| `regex.syntax.delimiter` | Invalid delimiter "{delim}". | Delimiter not allowed. | Use `/pattern/` instead of `!pattern!` if `!` is invalid. |
| `regex.semantic` | Various semantic errors. | Pattern violates PCRE rules (e.g., unbounded lookbehind). | Add bounds to lookbehind or use assertions. |

---

## Output Formats

### JSON Format for CI/IDE Integration

When using `--format=json` with the lint command, output follows this schema:

```json
{
  "stats": {
    "errors": 1,
    "warnings": 0,
    "optimizations": 1
  },
  "results": [
    {
      "file": "src/Example.php",
      "line": 15,
      "pattern": "/(a)\\2/",
      "location": null,
      "issues": [
        {
          "type": "error",
          "message": "Backreference to non-existent group: \\2",
          "line": 15,
          "column": 20,
          "issueId": "regex.backref.missing_group"
        }
      ],
      "optimizations": [
        {
          "file": "src/Example.php",
          "line": 15,
          "optimization": {
            "original": "/[0-9]+/",
            "optimized": "/\\d+/"
          },
          "savings": 2
        }
      ]
    }
  ]
}
```

- `stats`: Totals for the entire run (`errors`, `warnings`, `optimizations`).
- `results[]`: One entry per pattern occurrence (file + line).
- `results[].issues[]`: Array of diagnostic issues.
  - `type`: "error" | "warning"
  - `message`: Human-readable description
  - `line`, `column`: Position in source file
  - `issueId`: Diagnostic identifier (when available)
- `results[].optimizations[]`: Suggested optimizations.
  - `optimization`: Object with `original` and `optimized` strings
  - `savings`: Character savings count

Additional fields may be present for detailed analysis (e.g., validation or ReDoS metadata).


## Memory Management

### Memory Cache Limiting
RegexParser uses caching for validation operations to improve performance. To prevent memory leaks in long-running processes, the ValidatorNodeVisitor implements cache size limits:

- **Maximum cache entries**: 1000 items per cache
- **Automatic cleanup**: When cache reaches limit size
- **Manual cleanup**: `Regex::create()->clearValidatorCaches()`

```php
// For long-running processes
$regex = Regex::create();
$regex->clearValidatorCaches(); // Reset caches periodically
```

---

Previous: [Quick Start](QUICK_START.md) | Next: [ReDoS Guide](REDOS_GUIDE.md)
