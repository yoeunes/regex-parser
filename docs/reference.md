# RegexParser Diagnostics and Rule Reference

This reference is the definitive catalog of diagnostics, lint rules, and optimizations produced by RegexParser. We use it as a lookup when we need to understand why a pattern was flagged and how to fix it.

> If you are still learning regex or the AST, start with `docs/tutorial/README.md`. This document is optimized for precision, not onboarding.
> Examples assume `use RegexParser\Regex;`.

## Table of Contents

| Section | Description |
| --- | --- |
| [Validation Layers](#validation-layers) | How RegexParser analyzes patterns |
| [Flags](#flags) | Flag-related diagnostics |
| [Anchors](#anchors) | Anchor positioning issues |
| [Quantifiers](#quantifiers) | Quantifier-related patterns |
| [Groups](#groups) | Group-related diagnostics |
| [Alternation](#alternation) | Alternation patterns |
| [Character Classes](#character-classes) | Character class issues |
| [Escapes](#escapes) | Escape sequence problems |
| [Inline Flags](#inline-flags) | Inline flag diagnostics |
| [ReDoS Security](#security-redos) | Catastrophic backtracking |
| [Advanced Syntax](#advanced-syntax) | Optimizations and assertions |
| [Compatibility](#compatibility--limitations) | PHP and PCRE2 support |
| [Diagnostics Catalog](#diagnostics-catalog) | Complete error code reference |

---

## Validation Layers

RegexParser validates patterns through three distinct layers, each catching different types of issues:

```
+-------------------------------------------------------------+
|              VALIDATION LAYERS                              |
|-------------------------------------------------------------|
|                                                             |
|  +-----------------------------------------------------+    |
|  | LAYER 1: Parse (Lexer + Parser)                     |    |
|  | |- Tokenizes the pattern                            |    |
|  | |- Builds the AST                                   |    |
|  | +- Catches: syntax errors, malformed patterns       |    |
|  |                                                     |    |
|  | Examples:                                           |    |
|  | - Unbalanced brackets                               |    |
|  | - Invalid escapes                                   |    |
|  | - Missing delimiters                                |    |
|  +-----------------------------------------------------+    |
|                           |                                 |
|                           v                                 |
|  +-----------------------------------------------------+    |
|  | LAYER 2: Semantic Validation                        |    |
|  | |- Checks PCRE rules                                |    |
|  | |- Validates references and groups                  |    |
|  | +- Catches: semantic errors                         |    |
|  |                                                     |    |
|  | Examples:                                           |    |
|  | - Unbounded lookbehinds                             |    |
|  | - Invalid backreferences                            |    |
|  | - Duplicate group names                             |    |
|  +-----------------------------------------------------+    |
|                           |                                 |
|                           v                                 |
|  +-----------------------------------------------------+    |
|  | LAYER 3: Runtime Validation (Optional)              |    |
|  | |- Compiles via PCRE runtime                         |    |
|  | +- Catches: engine-specific issues                  |    |
|  |                                                     |    |
|  | Enable with:                                        |    |
|  |   'runtime_pcre_validation' => true                 |    |
|  +-----------------------------------------------------+    |
|                           |                                 |
|                           v                                 |
|  +-----------------------------------------------------+    |
|  | LAYER 4: Linting & Analysis                         |    |
|  | |- Performance checks                               |    |
|  | |- ReDoS analysis                                   |    |
|  | |- Best practices                                   |    |
|  | +- Catches: warnings and suggestions                |    |
|  +-----------------------------------------------------+    |
|                                                             |
+-------------------------------------------------------------+
```

### PCRE2 Compatibility Contract

RegexParser targets **PHP's PCRE2 engine** (`preg_*`). Key behaviors that may surprise users:

| Behavior | Description | Example |
| --- | --- | --- |
| Forward references | `/\\1(a)/` compiles in PHP | May look invalid but works |
| Branch reset | `(?\\|...)` changes capture numbering | `\\2` can be invalid in branches |
| `\\g{0}` | Invalid in PHP | Use `\\g<0>` or `(?R)` |
| Lookbehind | Must have bounded length | `(?<=a+)` is invalid |

---

## Flags

### Useless Flag 's' (DOTALL)

**Identifier:** `regex.lint.flag.useless.s`

**When it triggers:** The pattern sets the `s` (DotAll) modifier but contains no dot tokens (`.`). The `s` flag only affects `.` behavior.

**Visual Explanation:**
```
+-------------------------------------------------------------+
|  /^\d+$/s  --> NO DOT IN PATTERN                            |
|             --> DOTALL FLAG DOES NOTHING                    |
+-------------------------------------------------------------+
```

**Example:**
```php
// Warning: DotAll does nothing because there is no dot
Regex::create()->validate('/^user_id:\d+$/s');

// Preferred: Remove unnecessary flag
Regex::create()->validate('/^user_id:\d+$/');
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
+-------------------------------------------------------------+
|  /search_term/m  --> NO ANCHORS IN PATTERN                  |
|                 --> MULTILINE FLAG DOES NOTHING             |
+-------------------------------------------------------------+
```

**Example:**
```php
// Warning: Multiline mode is unused because there are no anchors
Regex::create()->validate('/search_term/m');

// Preferred: Remove unnecessary flag
Regex::create()->validate('/search_term/');
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
Regex::create()->validate('/^\d{4}-\d{2}-\d{2}$/i');

// Preferred: Remove unnecessary flag
Regex::create()->validate('/^\d{4}-\d{2}-\d{2}$/');
```

**Fix:** Drop the flag when matching digits/symbols.

---

## Anchors

### Anchor Conflicts

**Identifier:** `regex.lint.anchor.impossible.start`, `regex.lint.anchor.impossible.end`

**When it triggers:**
- `^` appears after consuming tokens (without `m` flag) -- cannot match start
- `$` appears before consuming tokens -- asserts end too early

**Visual Explanation:**
```
+-------------------------------------------------------------+
|              ANCHOR CONFLICTS                               |
|-------------------------------------------------------------|
|                                                             |
|  VALID:                                                     |
|  /^abc/        --> Anchor at start, then literal            |
|  /abc$/        --> Literal, then anchor at end              |
|                                                             |
|  INVALID:                                                   |
|  /a^bc/        --> ^ after consuming 'a'                    |
|  /^/abc        --> $ before consuming anything              |
|                                                             |
+-------------------------------------------------------------+
```

**Fix:** Move anchors to the correct position.

---

## Quantifiers

### Nested Quantifiers (ReDoS Risk)

**Identifier:** `regex.lint.quantifier.nested`

**When it triggers:** A variable quantifier wraps another variable quantifier, creating catastrophic backtracking potential.

**Visual Explanation:**
```
+-------------------------------------------------------------+
|              NESTED QUANTIFIERS EXPLOSION                   |
|-------------------------------------------------------------|
|                                                             |
|  Pattern: /(a+)+b/                                          |
|                                                             |
|  Input: a a a a a !                                         |
|                                                             |
|  Inner (a+) can match:                                      |
|    - 5 a's                                                  |
|    - 4 a's + outer + repeats 2                              |
|    - 3 a's + outer + repeats 3                              |
|    - ...                                                    |
|                                                             |
|  Each additional 'a' adds MORE combinations!                |
|                                                             |
+-------------------------------------------------------------+
```

**Example:**
```php
// VULNERABLE: Nested quantifiers can cause ReDoS
Regex::create()->redos('/(a+)+b/');

// SAFER: Use atomic groups
Regex::create()->redos('/(?>a+)+b/');

// SAFER: Use possessive quantifier
Regex::create()->redos('/(a++)+b/');
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
Regex::create()->redos('/(?:.*)+/');

// SAFER: Make it atomic
Regex::create()->validate('/(?>.*)+/');

// BETTER: Use negated character class
Regex::create()->validate('/(?:[^"]*)+/');  // For double-quoted strings
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
Regex::create()->validate('/(?:foo)/');

// PREFERRED: Remove the group
Regex::create()->validate('/foo/');
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
Regex::create()->validate('/(a|a)/');

// PREFERRED: Use character class
Regex::create()->validate('/[aa]/');  // or just /a/
```

**Fix:** Remove duplicates or use a character class.

---

### Overlapping Alternation Branches

**Identifier:** `regex.lint.alternation.overlap`

**When it triggers:** One literal alternative is a prefix of another.

**Visual Explanation:**
```
+-------------------------------------------------------------+
|              OVERLAPPING ALTERNATIVES                       |
|-------------------------------------------------------------|
|                                                             |
|  Pattern: /(a|aa)+b/                                        |
|                                                             |
|  Input: a a a a a b                                         |
|                                                             |
|  The engine tries:                                          |
|    a + a + a + a + a = "aaaaa"                              |
|    aa + a + a + a = "aaaaa"                                 |
|    a + aa + a + a = "aaaaa"                                 |
|    ...                                                      |
|                                                             |
|  Result: Exponential backtracking!                          |
|                                                             |
+-------------------------------------------------------------+
```

**Example:**
```php
// VULNERABLE: Overlapping branches in repetition
Regex::create()->redos('/(a|aa)+b/');

// SAFER: Use atomic groups
Regex::create()->redos('/(?>a|aa)+b/');

// SIMPLER: Just use a+
Regex::create()->redos('/a+b/');  // Often equivalent
```

**Fix:** Use atomic groups or simplify the pattern.

---

### Overlapping Character Sets

**Identifier:** `regex.lint.overlap.charset`

**When it triggers:** Alternation branches have overlapping character sets.

**Example:**
```php
// WARNING: Overlapping character classes
Regex::create()->validate('/[a-c]|[b-d]/');

// PREFERRED: Merge or use atomic groups
Regex::create()->validate('/[a-d]/');  // Merge
```

---

## Character Classes

### Redundant Character Class Elements

**Identifier:** `regex.lint.charclass.redundant`

**When it triggers:** A character class contains redundant elements or overlapping ranges.

**Example:**
```php
// WARNING: Duplicate letters
Regex::create()->validate('/[a-zA-Za-z]/');

// PREFERRED: Remove duplicates
Regex::create()->validate('/[a-zA-Z]/');

// WARNING: Overlapping ranges
Regex::create()->validate('/[a-fc-d]/');  // 'c' and 'd' already in a-f

// PREFERRED: Use clean ranges
Regex::create()->validate('/[a-f]/');
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
Regex::create()->validate('/\x{110000}/');  // Max is 0x10FFFF

// FIX: Use valid code point
Regex::create()->validate('/\x{10FFFF}/');

// WARNING: Suspicious escape
Regex::create()->validate('/\d/');  // Valid: digit

Regex::create()->validate('/\8/');  // Ambiguous: not a valid escape
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
Regex::create()->validate('/(?i)foo(?-i)/i');  // Global i, then toggle off/on

// PREFERRED: Remove redundancy
Regex::create()->validate('/(?i)foo/i');
```

---

### Inline Flag Override

**Identifier:** `regex.lint.flag.override`

**When it triggers:** An inline flag explicitly unsets a global modifier.

**Example:**
```php
// WARNING: Unset global flag
Regex::create()->validate('/(?-i:foo)i/');

// CONSIDER: Scope the flag instead
Regex::create()->validate('/(?i:foo)/');
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
Regex::create()->redos('/(a+)+$/');  // CRITICAL

// SAFER: Atomic group
Regex::create()->redos('/(?>a+)+$/');  // SAFE

// SAFER: Possessive quantifier
Regex::create()->redos('/(a++)+$/');  // SAFE
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
+-------------------------------------------------------------+
|              GREEDY vs POSSESSIVE                           |
|-------------------------------------------------------------|
|                                                             |
|  GREEDY: /".*"/                                             |
|    - Matches as much as possible                            |
|    - Backtracks on failure                                  |
|    - Can be slow on non-matching input                      |
|                                                             |
|  POSSESSIVE: /".*+"/                                        |
|    - Matches as much as possible                            |
|    - NEVER backtracks                                       |
|    - Fails fast on non-matching input                       |
|                                                             |
+-------------------------------------------------------------+
```

**Example:**
```php
// Greedy: may backtrack heavily
Regex::create()->validate('/".*"/');

// Possessive: consumes once and fails fast
Regex::create()->validate('/".*+"/');
```

---

### Atomic Groups

**What they are:** Groups of the form `(?>...)` that disallow backtracking into their contents once matched.

**Example:**
```php
// Risky: catastrophic backtracking on repeated 'a'
Regex::create()->validate('/(a+)+!/');

// Atomic: once inside the group matches, it cannot backtrack
Regex::create()->validate('/(?>a+)+!/');
```

---

### Assertions

**What they are:** Zero-width lookarounds like `(?=...)` / `(?!...)` / `(?<=...)` / `(?<!...)`.

**Example:**
```php
// Lookahead: require a trailing digit without consuming it
Regex::create()->validate('/^[A-Z]{2}(?=\d$)/');

// Lookbehind: ensure the match is preceded by "ID-"
Regex::create()->validate('/(?<=ID-)\d+/');
```

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
| Branch reset `(?  | ...)`        | Full support          | |

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
