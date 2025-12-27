# Diagnostics and Error Messages

This page explains how RegexParser reports errors and warnings, how to read
caret snippets, and how to map diagnostics to fixes.

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

## CLI validation output

```bash
regex validate '/(?<=a+)b/'
```

Typical output:

```text
INVALID  /(?<=a+)b/
  Lookbehind is unbounded. PCRE requires a bounded maximum length.
Line 1: (?<=a+)b
            ^
```

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
