# Diagnostics: How RegexParser Explains Problems

This guide shows how RegexParser reports errors and warnings and how to read them quickly.

> Diagnostics are only as useful as their context. We always include byte offsets and caret snippets so tooling can point at the exact location.

## Table of Contents

| Section | Description |
| --- | --- |
| [Validation Layers](#validation-layers) | How validation works |
| [Reading Diagnostics](#reading-diagnostics) | How to interpret results |
| [CLI Examples](#cli-examples) | Console output patterns |
| [CLI Output Types](#cli-output-types) | Console vs JSON |
| [Lint Diagnostics](#lint-diagnostics) | Lint output formats |
| [Common Fixes](#common-fixes) | Fast remediation patterns |
| [Error Code Categories](#error-code-categories) | Error categories |
| [Quick Reference](#quick-reference) | Field summary |

## Validation Layers

We validate in layers so you get precise error messages and stable categories.

```
LAYER 1: Parse (Lexer + Parser)
  - Purpose: Build the AST
  - Catches: Syntax errors, malformed tokens

LAYER 2: Semantic Validation
  - Purpose: Enforce PCRE rules on the AST
  - Catches: Invalid backrefs, lookbehinds, group rules

LAYER 3: Runtime Validation (Optional)
  - Purpose: Compile with PCRE
  - Catches: Engine-specific errors
  - Enable with: runtime_pcre_validation

LAYER 4: Linting and Analysis
  - Purpose: Best practices and security
  - Catches: ReDoS risks, useless flags, redundant groups
```

> We recommend enabling runtime validation when you validate user-provided patterns.

## Reading Diagnostics

### ValidationResult Fields

`Regex::validate()` returns a `ValidationResult` object with structured data.

```php
use RegexParser\Regex;

$result = Regex::create()->validate('/[unclosed/');

if (!$result->isValid()) {
    echo $result->error;        // Message
    echo $result->errorCode;    // Stable code
    echo $result->offset;       // Byte offset
    echo $result->caretSnippet; // Visual marker
}
```

| Field | Type | Meaning |
| --- | --- | --- |
| `isValid` | bool | Valid syntax and semantics |
| `error` | string\|null | Human message |
| `errorCode` | string\|null | Stable code for tooling |
| `offset` | int\|null | Byte offset in pattern |
| `caretSnippet` | string\|null | Visual snippet with caret |
| `hint` | string\|null | Suggested fix |
| `complexityScore` | int | Complexity score |
| `category` | ValidationErrorCategory | Error category |

### Caret Snippets

```
Pattern: [unclosed
          ^
```

We use byte offsets so the caret aligns with the raw pattern string.

## CLI Examples

### Validation Error with Caret

```bash
vendor/bin/regex --no-ansi validate '/(?<=a+)b/'
```

```
INVALID  /(?<=a+)b/
  Lookbehind is unbounded. PCRE requires a bounded maximum length.
Line 1: (?<=a+)b
            ^
```

### Successful Validation

```bash
vendor/bin/regex --no-ansi validate '/^[a-z]+$/'
```

```
VALID  /^[a-z]+$/
  Pattern is valid.
```

### ReDoS Summary

```bash
vendor/bin/regex --no-ansi analyze '/(a+)+$/'
```

```
Analyze
  Pattern:    /(a+)+$/
  Parse:      OK
  Validation: OK
  ReDoS:      CRITICAL (score 10)
```

## CLI Output Types

| Format | Use Case |
| --- | --- |
| `console` | Human-readable output |
| `json` | CI pipelines and tooling |
| `github` | GitHub Actions annotations |
| `checkstyle` | XML for CI systems |
| `junit` | Test report format |

## Lint Diagnostics

### Console Output

Lint output shows the file, line, and the diagnostic detail.

```
[CRITICAL] src/Example.php:43
/(a+)+$/ (ReDoS)
  Nested unbounded quantifiers detected.
```

### JSON Output (CI)

```bash
vendor/bin/regex lint src/ --format=json
```

```json
{
  "results": [
    {
      "file": "src/Example.php",
      "line": 42,
      "pattern": "/(?<=a+)b/",
      "issues": [
        {
          "type": "validation",
          "severity": "error",
          "message": "Variable-length lookbehind is not supported"
        }
      ]
    }
  ]
}
```

## Common Fixes

### Lookbehind Errors

Problem: `(?<=a+)` is unbounded.

Fix: Add a fixed upper bound or rewrite without lookbehind.

### Backreference Errors

Problem: `\1` appears before the first capturing group.

Fix: Move the backreference or make the group earlier in the pattern.

### Nested Quantifiers (ReDoS)

Problem: `(a+)+` creates exponential backtracking.

Fix: Use possessive quantifiers (`a++`) or atomic groups (`(?>a+)`).

### Duplicate Group Names

Problem: `(?<id>...)` repeated.

Fix: Rename the groups or use branch reset groups where allowed.

### Invalid Quantifier Range

Problem: `{5,2}` where min > max.

Fix: Swap or correct the bounds.

## Error Code Categories

| Category | Meaning |
| --- | --- |
| `syntax` | Lexer or parser errors |
| `semantic` | Valid syntax but invalid PCRE rules |
| `pcre-runtime` | Runtime compilation failure |

## Quick Reference

- Use `Regex::validate()` for structured results.
- Use `Regex::analyze()` for full reports.
- Use `Regex::redos()` for security checks.

---

Previous: `api.md` | Next: `diagnostics-cheatsheet.md`
