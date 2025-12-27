# Diagnostics and Error Messages

This page explains how RegexParser reports errors and warnings, how to read
caret snippets, and how to map diagnostics to fixes.

Need quick fixes? See [docs/reference/diagnostics-cheatsheet.md](diagnostics-cheatsheet.md).

## Layers of validation

RegexParser validates in layers:

1. **Parse**: tokenization and AST construction
2. **Semantic validation**: PCRE rules (lookbehind bounds, backrefs, etc.)
3. **Runtime validation** (optional): compile check via `preg_match()`

## ValidationResult fields

`Regex::validate()` returns a `ValidationResult`:

- `isValid` true/false
- `error` human-readable error message
- `errorCode` stable code for programmatic handling
- `offset` byte offset in the pattern body
- `caretSnippet` snippet with a caret under the error
- `hint` fix suggestion (when available)

Example:

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

## How to read diagnostics (CLI examples)

Validation error with caret:

```bash
bin/regex --no-ansi validate '/(?<=a+)b/'
```

```text
INVALID  /(?<=a+)b/
  Lookbehind is unbounded. PCRE requires a bounded maximum length.
Line 1: (?<=a+)b
            ^
```

What to notice:
- The first line is the overall status and the pattern.
- The message explains the semantic rule that failed.
- The caret shows the exact byte offset inside the pattern body.

ReDoS analysis summary:

```bash
bin/regex --no-ansi analyze '/(a+)+$/'
```

```text
Analyze
  Pattern:    /(a+)+$/
  Parse:      OK
  Validation: OK
  ReDoS:      CRITICAL (score 10)

Explanation
Regex matches
  Start Quantified Group (one or more times)
    Capturing group
            'a' (one or more times)
    End group
  End Quantified Group
  Anchor: the end of the string (or line, with /m flag)
```

What to notice:
- Parse/Validation status tells you if the pattern is structurally valid.
- ReDoS severity highlights risk even when syntax is valid.
- The explanation section maps the AST back to human language.

## Lint diagnostics

Linting returns **issues** with identifiers (rule IDs) and optional hints.
These map to the rule reference in `docs/reference.md`.

For machine-readable output:

```bash
regex lint src/ --format=json
```

Each issue includes:
- `issueId` rule identifier
- `message` human-readable explanation
- `line` and `column` in the source file
- optional `hint`

## Common fixes

- **Lookbehind errors**: make the lookbehind length bounded
- **Backreference errors**: ensure the referenced group exists
- **Nested quantifiers**: refactor or use atomic groups/possessive quantifiers

---

Previous: [CLI Guide](../guides/cli.md) | Next: [Lint Rule Reference](../reference.md)
