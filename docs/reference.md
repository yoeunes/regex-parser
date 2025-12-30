# RegexParser Rule Reference

This comprehensive reference documents every diagnostic, lint rule, and optimization that RegexParser produces. It serves as the authoritative guide for understanding what RegexParser checks and how to fix issues.

## Table of Contents

| Section                                      | Description                       |
|----------------------------------------------|-----------------------------------|
| [Validation Layers](#validation-layers)      | How RegexParser analyzes patterns |
| [Flags](#flags)                              | Flag-related diagnostics          |
| [Anchors](#anchors)                          | Anchor positioning issues         |
| [Quantifiers](#quantifiers)                  | Quantifier-related patterns       |
| [Groups](#groups)                            | Group-related diagnostics         |
| [Alternation](#alternation)                  | Alternation patterns              |
| [Character Classes](#character-classes)      | Character class issues            |
| [Escapes](#escapes)                          | Escape sequence problems          |
| [Inline Flags](#inline-flags)                | Inline flag diagnostics           |
| [ReDoS Security](#security-redos)            | Catastrophic backtracking         |
| [Advanced Syntax](#advanced-syntax)          | Optimizations and assertions      |
| [Compatibility](#compatibility--limitations) | PHP and PCRE2 support             |
| [Diagnostics Catalog](#diagnostics-catalog)  | Complete error code reference     |

---

## Validation Layers

RegexParser validates patterns through four layers, each catching different types of issues:

```
Pattern literal
  -> Parse (Lexer + Parser): syntax errors, malformed patterns
  -> Semantic validation: PCRE rules, group and reference checks
  -> Runtime validation (optional): preg_match compilation
  -> Linting & analysis: ReDoS, performance, best practices
```

Examples by layer:

- Parse: unbalanced brackets, invalid escapes, missing delimiters
- Semantic: unbounded lookbehinds, invalid backreferences, duplicate group names
- Runtime: engine-specific compilation errors
- Linting & analysis: ReDoS hotspots, risky quantifiers, optimization hints

### PCRE2 Compatibility Contract

RegexParser targets **PHP's PCRE2 engine** (`preg_*`). Key behaviors that may surprise users:

| Behavior           | Description               | Example                         |
|--------------------|---------------------------|---------------------------------|
| Forward references | Backreference before capture compiles, but the group is unset and the match will fail until it has captured | `/\1(a)/` compiles, but `\1` fails |
| Branch reset       | `(?|...)` changes capture numbering | `\2` can be invalid in branches |
| `\g{0}`            | Invalid in PHP            | Use `\g<0>` or `(?R)`           |
| Lookbehind         | Must have bounded length  | `(?<=a+)` is invalid            |

---

## Flags

### Useless Flag 's' (DOTALL)

**Identifier:** `regex.lint.flag.useless.s`

**When it triggers:** The pattern sets the `s` (DotAll) modifier but contains no dot tokens (`.`). The `s` flag only affects `.` behavior.

**Visual Explanation:**
```
/^\d+$/s
  -> no dot in pattern
  -> s has no effect
```

**Example:**
```php
// Warning: DotAll does nothing because there is no dot
preg_match('/^user_id:\d+$/s', $input);

// Preferred: Remove unnecessary flag
preg_match('/^user_id:\d+$/', $input);
```

**Fix:** Drop the flag or introduce a dot if intended.

**Read more:**
- [PHP: Pattern Modifiers (`s` / DOTALL)](https://www.php.net/manual/en/reference.pcre.pattern.modifiers.php)

---

### Useless Flag 'm' (Multiline)

**Identifier:** `regex.lint.flag.useless.m`

**When it triggers:** The pattern sets the `m` (multiline) modifier but contains no start/end anchors (`^` or `$`).

**Visual Explanation:**
```
/search_term/m
  -> no ^ or $ anchors
  -> m has no effect
```

**Example:**
```php
// Warning: Multiline mode is unused because there are no anchors
preg_match('/search_term/m', $text);

// Preferred: Remove unnecessary flag
preg_match('/search_term/', $text);
```

**Fix:** Remove the flag or add anchors for per-line matching.

**Read more:**
- [PHP: Pattern Modifiers (`m` / MULTILINE)](https://www.php.net/manual/en/reference.pcre.pattern.modifiers.php)

---

### Useless Flag 'i' (Caseless)

**Identifier:** `regex.lint.flag.useless.i`

**When it triggers:** The pattern sets the `i` (case-insensitive) modifier but contains no case-sensitive characters.

**Example:**
```php
// Warning: No letters to justify case-insensitive matching
preg_match('/^\d{4}-\d{2}-\d{2}$/i', $date);

// Preferred: Remove unnecessary flag
preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
```

**Fix:** Drop the flag when matching digits/symbols.

---

## Anchors

### Anchor Conflicts

**Identifier:** `regex.lint.anchor.impossible.start`, `regex.lint.anchor.impossible.end`

**When it triggers:**
- `^` appears after consuming tokens (without `m` flag) — cannot match start
- `$` appears before consuming tokens — asserts end too early

**Visual Explanation:**
```
Valid:
  /^abc/  -> anchor before content
  /abc$/  -> anchor after content
Invalid:
  /a^bc/  -> ^ after consuming 'a'
  /$abc/  -> $ before consuming anything
```

**Fix:** Move anchors to the correct position.

---

## Quantifiers

### Nested Quantifiers (ReDoS Risk)

**Identifier:** `regex.lint.quantifier.nested`

**When it triggers:** A variable quantifier wraps another variable quantifier, creating catastrophic backtracking potential.

**Visual Explanation:**
```
Pattern: /(a+)+b/
Input: "aaaaa!"
Inner (a+) can match 1..n and the outer + repeats 1..n
Result: many backtracking paths
```

**Example:**
```php
// VULNERABLE: Nested quantifiers can cause ReDoS
preg_match('/(a+)+b/', $input);

// SAFER: Use atomic groups
preg_match('/(?>a+)+b/', $input);

// SAFER: Use possessive quantifier
preg_match('/(a++)+b/', $input);
```

**Fix:** Refactor to be deterministic, or use atomic groups/possessive quantifiers.

**Read more:**
- [OWASP: ReDoS](https://owasp.org/www-community/attacks/Regular_expression_Denial_of_Service_-_ReDoS)

---

### Dot-Star in Quantifier

**Identifier:** `regex.lint.dotstar.nested`

**When it triggers:** An unbounded quantifier wraps a dot-star, which can cause extreme backtracking.

**Example:**
```php
// RISKY: .* in repetition can backtrack heavily
preg_match('/(?:.*)+/', $input);

// SAFER: Make the dot-star atomic or possessive
preg_match('/(?>.*)+/', $input);
preg_match('/.*+/', $input);  // If no outer repetition is needed

// BETTER: Use negated character class
preg_match('/[^"]*/', $input);  // For double-quoted strings
```

**Fix:** Make it atomic/possessive or replace `.*` with a specific class.

---

## Groups

### Redundant Non-Capturing Group

**Identifier:** `regex.lint.group.redundant`

**When it triggers:** A non-capturing group wraps a single atomic token without changing precedence.

**Example:**
```php
// WARNING: Unnecessary group
preg_match('/(?:foo)/', $input);

// PREFERRED: Remove the group
preg_match('/foo/', $input);
```

**Fix:** Remove the unnecessary group.

---

## Alternation

### Duplicate Alternation Branches

**Identifier:** `regex.lint.alternation.duplicate`

**When it triggers:** The same literal branch appears more than once.

**Example:**
```php
// WARNING: Duplicate branch
preg_match('/(a|a)/', $input);

// PREFERRED: Use a single literal
preg_match('/a/', $input);
```

**Fix:** Remove duplicates or use a character class.

---

### Overlapping Alternation Branches

**Identifier:** `regex.lint.alternation.overlap`

**When it triggers:** One literal alternative is a prefix of another.

**Visual Explanation:**
```
Pattern: /(a|aa)+b/
Input: "aaaaab"
The engine can split the a's as: a+a+a+a+a or aa+a+a+a, ...
Result: overlapping paths trigger heavy backtracking
```

**Example:**
```php
// VULNERABLE: Overlapping branches in repetition
preg_match('/(a|aa)+b/', $input);

// SAFER: Use atomic groups
preg_match('/(?>a|aa)+b/', $input);

// SIMPLER: Just use a+
preg_match('/a+b/', $input);  // Often equivalent
```

**Fix:** Use atomic groups or simplify the pattern.

---

### Overlapping Character Sets

**Identifier:** `regex.lint.overlap.charset`

**When it triggers:** Alternation branches have overlapping character sets.

**Example:**
```php
// WARNING: Overlapping character classes
preg_match('/[a-c]|[b-d]/', $input);

// SAFER: Use an atomic group to avoid backtracking
preg_match('/(?>[a-c]|[b-d])/', $input);

// IF EQUIVALENT: Merge ranges
preg_match('/[a-d]/', $input);
```

---

## Character Classes

### Redundant Character Class Elements

**Identifier:** `regex.lint.charclass.redundant`

**When it triggers:** A character class contains redundant elements or overlapping ranges.

**Example:**
```php
// WARNING: Duplicate letters
preg_match('/[a-zA-Za-z]/', $input);

// PREFERRED: Remove duplicates
preg_match('/[a-zA-Z]/', $input);

// WARNING: Overlapping ranges
preg_match('/[a-fc-d]/', $input);  // 'c' and 'd' already in a-f

// PREFERRED: Use clean ranges
preg_match('/[a-f]/', $input);
```

**Fix:** Remove duplicates or merge ranges.

---

## Escapes

### Suspicious Escapes

**Identifier:** `regex.lint.escape.suspicious`

**When it triggers:** Escapes that are likely typos or out-of-range values.

**Example:**
```php
// WARNING: Out of range Unicode
preg_match('/\x{110000}/', $input);  // Max is 0x10FFFF

// FIX: Use valid code point
preg_match('/\x{10FFFF}/', $input);

// WARNING: Suspicious escape
preg_match('/\d/', $input);  // Valid: digit

preg_match('/\8/', $input);  // Ambiguous: not a valid escape
```

**Fix:** Correct the codepoint or use valid escapes.

---

## Inline Flags

### Inline Flag Redundant

**Identifier:** `regex.lint.flag.redundant`

**When it triggers:** An inline flag sets/unsets a modifier already in the desired state.

**Example:**
```php
// WARNING: Redundant inline flag
preg_match('/(?i)foo/i', $input);  // Global i already set

// PREFERRED: Remove redundancy
preg_match('/foo/i', $input);
```

---

### Inline Flag Override

**Identifier:** `regex.lint.flag.override`

**When it triggers:** An inline flag explicitly unsets a global modifier.

**Example:**
```php
// WARNING: Unset global flag
preg_match('/(?-i:foo)/i', $input);

// CONSIDER: Scope the flag instead
preg_match('/(?i:foo)bar/', $input);
```

---

## Security (ReDoS)

### Catastrophic Backtracking

**Identifiers:** `regex.redos.critical`, `regex.redos.high`, `regex.redos.medium`, `regex.redos.low`

**When it triggers:** The ReDoS analyzer detects nested quantifiers or overlapping alternatives that can explode backtracking time.

**Risk Levels:**

| Level      | Severity                | Action Required      |
|------------|-------------------------|----------------------|
| `critical` | Easily exploitable      | Refactor immediately |
| `high`     | Requires specific input | Consider refactoring |
| `medium`   | Requires crafted input  | Monitor and plan fix |
| `low`      | Minimal risk            | Accept with logging  |

**Example:**
```php
// VULNERABLE: Exponential backtracking
preg_match('/(a+)+$/', $input);  // CRITICAL

// SAFER: Atomic group
preg_match('/(?>a+)+$/', $input);  // SAFE

// SAFER: Possessive quantifier
preg_match('/(a++)+$/', $input);  // SAFE
```

**Fix:** Make the ambiguous part atomic or possessive, or refactor.

**Read more:**
- [OWASP: Regular Expression Denial of Service](https://owasp.org/www-community/attacks/Regular_expression_Denial_of_Service_-_ReDoS)

---

## Advanced Syntax

### Possessive Quantifiers

**What they are:** Quantifiers with trailing `+` (`*+`, `++`, `?+`, `{m,n}+`) that consume text without backtracking.

**Visual Comparison:**
```
Greedy: /".*"/
  -> matches as much as possible
  -> backtracks on failure

Possessive: /".*+"/
  -> matches as much as possible
  -> never backtracks
```

**Example:**
```php
// Greedy: may backtrack heavily
preg_match('/".*"/', $input);

// Possessive: consumes once and fails fast
preg_match('/".*+"/', $input);
```

---

### Atomic Groups

**What they are:** Groups of the form `(?>...)` that disallow backtracking into their contents once matched.

**Example:**
```php
// Risky: catastrophic backtracking on repeated 'a'
preg_match('/(a+)+!/', $input);

// Atomic: once inside the group matches, it cannot backtrack
preg_match('/(?>a+)+!/', $input);
```

---

### Assertions

**What they are:** Zero-width lookarounds like `(?=...)` / `(?!...)` / `(?<=...)` / `(?<!...)`.

**Example:**
```php
// Lookahead: require a trailing digit without consuming it
preg_match('/^[A-Z]{2}(?=\d$)/', $input);

// Lookbehind: ensure the match is preceded by "ID-"
preg_match('/(?<=ID-)\d+/', $input);
```

---

## Real-World Patterns (from Fixtures)

These examples are copied from `tests/Fixtures/pcre_patterns.php` to show the kinds of patterns RegexParser parses in tests.

### HTML Hex Entities

```
#(&\#x*)([0-9A-F]+);*#iu
```

Matches hex entities like `&#x1F4A9;`, capturing the prefix and hex digits. Case-insensitive (`i`) and Unicode-aware (`u`).

### Nested [indent] Tags (Recursive)

```
#\[indent]((?:[^[]|\[(?!/?indent])|(?R))+)\[/indent]#
```

Recursively matches nested `[indent]...[/indent]` blocks using `(?R)` to re-enter the whole pattern.

---

## Compatibility & Limitations

### Supported PHP Versions

- **PHP 8.2** and above
- Uses readonly classes and enum features

### Target Engine

- **PCRE2** via PHP's `preg_*` functions

### Known Differences from PCRE1

| Feature           | PCRE2        | Note                  |
|-------------------|--------------|-----------------------|
| `\p{...}` Unicode | Full support |                       |
| `\g{0}`           | Invalid      | Use `\g<0>` or `(?R)` |
| Branch reset `(?|...)` | Full support | |

---

## Diagnostics Catalog

| Error Code                          | Message Template                                    | Meaning                               | Fix Example              |
|-------------------------------------|-----------------------------------------------------|---------------------------------------|--------------------------|
| `regex.backref.missing_group`       | Backreference to non-existent group: "{ref}"        | Backreference points to missing group | Change `\2` to `\1`      |
| `regex.backref.missing_named_group` | Backreference to non-existent named group: "{name}" | Named backreference undefined         | Add `(?<name>...)`       |
| `regex.backref.zero`                | Backreference \0 is not valid                       | `\0` invalid in PCRE                  | Use `\g<0>`              |
| `regex.group.duplicate_name`        | Duplicate group name "{name}"                       | Named groups must be unique           | Use different names      |
| `regex.quantifier.invalid_range`    | Invalid quantifier range "{quant}": min > max       | `{3,2}` is invalid                    | Swap to `{2,3}`          |
| `regex.syntax.delimiter`            | Invalid delimiter "{delim}"                         | Delimiter not allowed                 | Use `/pattern/`          |
| `regex.semantic`                    | Various semantic errors                             | Pattern violates PCRE rules           | Add bounds to lookbehind |

---

## Quick Reference Table

| Category    | Rule ID                     | Severity      | Quick Fix                        |
|-------------|-----------------------------|---------------|----------------------------------|
| Flags       | `regex.lint.flag.useless.*` | warning       | Remove unused flag               |
| Anchors     | `regex.lint.anchor.*`       | error         | Move anchors to correct position |
| Quantifiers | `regex.lint.quantifier.*`   | warning/error | Use atomic groups                |
| Groups      | `regex.lint.group.*`        | info          | Remove redundant groups          |
| Alternation | `regex.lint.alternation.*`  | warning       | Simplify or use atomic           |
| Character   | `regex.lint.charclass.*`    | warning       | Remove duplicates                |
| Escapes     | `regex.lint.escape.*`       | warning       | Fix escape sequences             |
| ReDoS       | `regex.redos.*`             | error/warning | Use possessive quantifiers       |

---

Previous: [Quick Start](QUICK_START.md) | Next: [ReDoS Guide](REDOS_GUIDE.md)
