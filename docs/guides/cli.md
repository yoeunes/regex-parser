# CLI Guide

The CLI is the fastest way to learn what RegexParser sees. We can parse, analyze, lint, and visualize patterns without writing code.

> Use `vendor/bin/regex` for Composer installs or `regex` for the PHAR.

## Install and Run

```bash
# Composer install
vendor/bin/regex --help
```

```bash
# PHAR install
curl -Ls https://github.com/yoeunes/regex-parser/releases/latest/download/regex.phar -o ~/.local/bin/regex
chmod +x ~/.local/bin/regex
regex --help
```

## Command Overview

| Command | Purpose |
| --- | --- |
| `parse` | Parse and recompile a pattern |
| `analyze` | Validate, check ReDoS, explain |
| `explain` | Explain a pattern in plain language |
| `debug` | Deep ReDoS analysis with heatmap |
| `diagram` | ASCII AST diagram |
| `highlight` | Console or HTML highlighting |
| `validate` | Syntax and semantic validation |
| `lint` | Scan a codebase for regex issues |
| `self-update` | Update PHAR |
| `help` | Show help |

## Global Options

| Option | Description |
| --- | --- |
| `--ansi` | Force ANSI colors |
| `--no-ansi` | Disable ANSI colors |
| `-q, --quiet` | Suppress output |
| `--silent` | Same as `--quiet` |
| `--php-version <ver>` | Target PHP version for validation |
| `--help` | Show help |

## Command Examples

### Parse

```bash
vendor/bin/regex parse '/^hello world$/'
```

```
Pattern:    /^hello world$/
Recompiled: /^hello world$/
```

### Analyze (Validation + ReDoS + Explanation)

```bash
vendor/bin/regex analyze '/(a+)+$/'
```

```
Analyze
  Pattern:    /(a+)+$/
  Parse:      OK
  Validation: OK
  ReDoS:      CRITICAL (score 10)

Explanation
  ...
```

### Explain (Text Only)

```bash
vendor/bin/regex explain '/^user-\\d+$/'
```

### Diagram (AST)

```bash
vendor/bin/regex diagram '/^[a-z]+@[a-z]+\.[a-z]+$/i'
```

```
RegexNode
+-- SequenceNode
    |-- AnchorNode("^")
    |-- QuantifierNode("+")
    |   +-- CharClassNode("[a-z]")
    |-- LiteralNode("@")
    |-- QuantifierNode("+")
    |   +-- CharClassNode("[a-z]")
    |-- LiteralNode(".")
    |-- QuantifierNode("+")
    |   +-- CharClassNode("[a-z]")
    +-- AnchorNode("$")
```

### Highlight

```bash
vendor/bin/regex highlight '/^hello$/'
vendor/bin/regex highlight '/^hello$/' --format=html
```

### Validate

```bash
vendor/bin/regex validate '/(?<=a+)b/'
```

```
INVALID  /(?<=a+)b/
  Lookbehind is unbounded. PCRE requires a bounded maximum length.
Line 1: (?<=a+)b
            ^
```

### Lint

```bash
vendor/bin/regex lint src/ --format=console
```

```
[PASS] No issues found.
```

## Lint Configuration File

Create `regex.json` or `regex.dist.json` in your project root:

```json
{
  "format": "console",
  "minSavings": 2,
  "jobs": 4,
  "exclude": ["vendor", "var", "tests"],
  "ide": "phpstorm"
}
```

| Option | Type | Description |
| --- | --- | --- |
| `format` | string | console, json, github, checkstyle, junit |
| `minSavings` | int | Minimum optimization savings |
| `jobs` | int | Parallel workers |
| `exclude` | array | Paths to exclude |
| `ide` | string | Link formatter for IDEs |

## Lint Output Formats

| Format | Use Case |
| --- | --- |
| `console` | Human-readable output |
| `json` | CI/CD pipelines |
| `github` | GitHub Actions annotations |
| `checkstyle` | CI XML format |
| `junit` | Test report format |

## Common Issues

- Missing delimiters: use `/pattern/`, `#pattern#`, or `~pattern~`.
- Colors not showing: add `--ansi`.

## Learn More

- `../tutorial/README.md`
- `../REDOS_GUIDE.md`
- `../reference/diagnostics.md`

---

Previous: `regex-in-php.md` | Next: `../reference/diagnostics.md`
