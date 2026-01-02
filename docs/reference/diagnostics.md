# Diagnostics and Error Messages

This comprehensive guide explains how RegexParser reports errors and warnings, how to read diagnostic output, and how to map diagnostics to fixes.

## Table of Contents

| Section                                             | Description           |
|-----------------------------------------------------|-----------------------|
| [Validation Layers](#validation-layers)             | How validation works  |
| [Reading Diagnostics](#reading-diagnostics)         | Understanding output  |
| [ValidationResult Fields](#validationresult-fields) | Result object details |
| [CLI Examples](#cli-examples)                       | Command-line output   |
| [Lint Diagnostics](#lint-diagnostics)               | Linting output        |
| [Common Fixes](#common-fixes)                       | Quick solutions       |

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

- Parse: unbalanced brackets `[a-z`, invalid escapes `\x`, missing delimiters `foo`
- Semantic: unbounded lookbehind `(?<=a+)`, backreference to non-existent group `\2`, duplicate group names
- Runtime: engine-specific compilation errors
- Linting & analysis: nested quantifiers `(a+)+`, useless flags `/\d+/i`, redundant groups `(?:foo)`

---

## Reading Diagnostics

### ValidationResult Fields

When you call `Regex::validate()`, you get a `ValidationResult` object:

```php
use RegexParser\Regex;

$result = Regex::create()->validate('/[unclosed/');

if (!$result->isValid()) {
    // Access all diagnostic information
    echo $result->isValid();        // false
    echo $result->error;            // "Unterminated character class"
    echo $result->errorCode;        // "regex.syntax.unterminated"
    echo $result->offset;           // 9
    echo $result->caretSnippet;     // See below
    echo $result->hint;             // "Close the bracket with ]"
    echo $result->complexityScore;  // 1
    echo $result->category->value;  // "syntax"
}
```

**Field Reference Table:**

| Field             | Type                    | Description               | Example                          |
|-------------------|-------------------------|---------------------------|----------------------------------|
| `isValid`         | bool                    | Whether pattern passed    | `false`                          |
| `error`           | string\|null            | Human-readable message    | `"Unterminated character class"` |
| `errorCode`       | string\|null            | Stable code for handling  | `"regex.syntax.unterminated"`    |
| `offset`          | int\|null               | Byte offset in pattern    | `9`                              |
| `caretSnippet`    | string\|null            | Visual snippet with caret | See below                        |
| `hint`            | string\|null            | Suggested fix             | `"Close the bracket with ]"`     |
| `complexityScore` | int                     | Pattern complexity        | `1`                              |
| `category`        | ValidationErrorCategory | Error type                | `syntax`                         |

### Understanding Caret Snippets

The `caretSnippet` shows exactly where the error occurred:

```
Pattern: [unclosed
          ^
```

This visual representation helps you quickly locate and fix issues.

---

## CLI Examples

### Validation Error with Caret

```bash
vendor/bin/regex --no-ansi validate '/(?<=a+)b/'
```

**Output:**
```
INVALID  /(?<=a+)b/
  Lookbehind is unbounded. PCRE requires a bounded maximum length.
Line 1: (?<=a+)b
            ^
```

**What to notice:**
1. `INVALID` status indicates failure
2. The message explains the problem
3. The caret (`^`) shows exact position

---

### Successful Validation

```bash
vendor/bin/regex --no-ansi validate '/^[a-z]+$/'
```

**Output:**
```
VALID  /^[a-z]+$/
  Pattern is valid.
```

---

### ReDoS Analysis Summary

```bash
vendor/bin/regex --no-ansi analyze '/(a+)+$/'
```

**Output:**
```
Analyze
  Pattern:    /(a+)+$/
  Parse:      OK
  Validation: OK
  ReDoS:      CRITICAL (score 10)

Explanation
  Match
    Quantified Group (one or more times)
      'a' (one or more times)
    End Group
    Anchor: the end of the string
```

**What to notice:**
- `Parse` and `Validation` show structural validity
- `ReDoS` highlights security concerns even in valid patterns
- `Explanation` translates AST to plain language

---

## CLI Output Types

| Command                 | Output                      | Use Case      |
|-------------------------|-----------------------------|---------------|
| `validate '/pattern/'`  | Validation status + errors  | Quick check   |
| `analyze '/pattern/'`   | Full analysis + explanation | Deep dive     |
| `explain '/pattern/'`   | Plain text explanation      | Documentation |
| `highlight '/pattern/'` | Colored pattern             | Display       |
| `lint src/`             | Issues across codebase      | CI/CD         |

---

## Lint Diagnostics

Linting finds issues beyond validity — performance, security, and best practices.

### CLI Lint Output

```bash
vendor/bin/regex lint src/ --no-ansi
```

**Output:**
```
src/Service/Validator.php:42: warning: Nested quantifiers detected
  Pattern: /^[a-z0-9._%+-]+@[a-z0-9-]+(?:\.[a-z0-9-]+)+$/i
  Hint: Use atomic groups (?>...) or possessive quantifiers (*+, ++).
```

### JSON Output for CI

```bash
vendor/bin/regex lint src/ --format=json
```

**Output:**
```json
{
    "stats": {
        "errors": 0,
        "warnings": 1,
        "optimizations": 1
    },
    "results": [
        {
            "file": "src/Service/Validator.php",
            "line": 42,
            "source": "php",
            "pattern": "/^[a-z0-9._%+-]+@[a-z0-9-]+(?:\\.[a-z0-9-]+)+$/i",
            "issues": [
                {
                    "type": "warning",
                    "message": "Nested quantifiers detected. Consider using atomic groups or possessive quantifiers.",
                    "file": "src/Service/Validator.php",
                    "line": 42,
                    "column": 9,
                    "issueId": "regex.lint.quantifier.nested",
                    "hint": "Use atomic groups (?>...) or possessive quantifiers (*+, ++).",
                    "source": "php"
                }
            ],
            "optimizations": [
                {
                    "file": "src/Service/Validator.php",
                    "line": 42,
                    "optimization": {
                        "original": "/[0-9]+/",
                        "optimized": "/\\d+/"
                    },
                    "savings": 2,
                    "source": "php"
                }
            ]
        }
    ]
}
```

**Issue Fields:**

| Field     | Description                |
|-----------|----------------------------|
| `type`    | `error` or `warning`       |
| `message` | Human-readable explanation |
| `file`    | Source file path           |
| `line`    | Line number                |
| `column`  | Column number              |
| `issueId` | Diagnostic identifier      |
| `hint`    | Suggested fix              |
| `suggestedPattern` | Suggested pattern rewrite (optional) |
| `source`  | Source language            |

---

## Common Fixes

Quick solutions for frequently encountered diagnostics:

### Lookbehind Errors

**Problem:** `Lookbehind is unbounded`

**Solution:** Make the lookbehind bounded or use lookahead

```php
// ERROR: (?<=a+) is unbounded
preg_match('/(?<=a+)b/', $input);

// FIX 1: Use bounded quantifier
preg_match('/(?<=a{1,10})b/', $input);

// FIX 2: Use lookahead + capture
preg_match('/(?=(a+))b\1/', $input);
```

---

### Backreference Errors

**Problem:** `Backreference to non-existent group`

**Solution:** Ensure the referenced group exists

```php
// ERROR: \2 refers to non-existent group
preg_match('/(\w+)\2/', $input);  // Only one group

// FIX 1: Use correct group number
preg_match('/(\w+)\1/', $input);  // \1 refers to group 1

// FIX 2: Add the missing group
preg_match('/(\w+)(\w+)\2/', $input);  // Now \2 exists
```

---

### Nested Quantifiers (ReDoS)

**Problem:** `Nested quantifiers detected`

**Solution:** Use atomic groups or simplify

```php
// VULNERABLE: (a+)+ can cause ReDoS
preg_match('/(a+)+$/', $input);

// FIX 1: Use atomic group
preg_match('/(?>a+)+$/', $input);

// FIX 2: Simplify (often equivalent)
preg_match('/a+$/', $input);
```

---

### Duplicate Group Names

**Problem:** `Duplicate group name`

**Solution:** Use unique names or enable J flag

```php
// ERROR: Duplicate name 'id'
preg_match('/(?<id>\w+)(?<id>\d+)/', $input);

// FIX 1: Use unique names
preg_match('/(?<id>\w+)(?<number>\d+)/', $input);

// FIX 2: Enable J flag for duplicates
preg_match('/(?J)(?<id>\w+)(?<id>\d+)/', $input);
```

---

### Invalid Quantifier Range

**Problem:** `Invalid quantifier range: min > max`

**Solution:** Swap or fix the range

```php
// ERROR: {5,2} is invalid (min > max)
preg_match('/\d{5,2}/', $input);

// FIX: Swap to {2,5}
preg_match('/\d{2,5}/', $input);
```

---

### Character Class Pitfalls

**Problem:** Suspicious ASCII ranges or alternation-like character classes.

**Solution:** Split ranges or use alternation groups.

```php
// WARNING: Includes punctuation between Z and a
preg_match('/[A-z]/', $input);

// FIX: Split ranges
preg_match('/[A-Za-z]/', $input);

// WARNING: | is literal in []
preg_match('/[error|failure]/', $input);

// FIX: Use alternation
preg_match('/(error|failure)/', $input);
```

---

## Error Code Categories

| Category      | Meaning                   | Examples                              |
|---------------|---------------------------|---------------------------------------|
| `syntax`      | Invalid pattern structure | Unterminated class, missing delimiter |
| `semantic`    | Violates PCRE rules       | Unbounded lookbehind, bad backref     |
| `redos`       | ReDoS vulnerability       | Nested quantifiers                    |
| `performance` | Suboptimal pattern        | Redundant group                       |
| `style`       | Code style issue          | Useless flag                          |

---

## Quick Reference

| Error                | Fix                                  |
|----------------------|--------------------------------------|
| Lookbehind unbounded | Add bounds `{1,10}` or use lookahead |
| Bad backreference    | Check group numbers/names            |
| Nested quantifiers   | Use atomic groups or simplify        |
| Duplicate name       | Use unique names or `(?J)`           |
| Invalid range        | Swap min/max in `{min,max}`          |
| Useless flag         | Remove unused flag                   |

---

Previous: [CLI Guide](../guides/cli.md) | Next: [Lint Rule Reference](../reference.md)
