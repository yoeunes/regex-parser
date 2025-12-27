# CLI Guide

RegexParser ships with a CLI for parsing, validation, analysis, and linting.
This guide covers everyday usage and CI integration.

## Install

Use the project binary when developing in this repo:

```bash
bin/regex --help
```

If installed via Composer, use `vendor/bin/regex`.

Or install the PHAR:

```bash
curl -Ls https://github.com/yoeunes/regex-parser/releases/latest/download/regex.phar -o ~/.local/bin/regex
chmod +x ~/.local/bin/regex
```

## Quick commands

Analyze a single pattern:

```bash
regex analyze '/(a+)+$/'
```

Analyze a pattern (includes explanation):

```bash
regex analyze '/^(?<user>\w+)@(?<host>\w+)$/'
```

Highlight for HTML:

```bash
regex highlight '/^\d{4}-\d{2}-\d{2}$/' --format=html
```

## Lint a codebase

```bash
regex lint src/ --format=console --min-savings=2
```

Useful options:
- `--no-redos` skip ReDoS analysis
- `--no-validate` skip validation errors
- `--no-optimize` disable optimization suggestions
- `--jobs` parallel workers (requires `pcntl`)

## Configuration file

Place a `regex.json` (or `regex.dist.json`) in your repo:

```json
{
  "format": "console",
  "minSavings": 2,
  "jobs": 4,
  "exclude": ["vendor", "var"]
}
```

## Ignore inline

```php
preg_match('/(a+)+$/', $input); // @regex-ignore-next-line
```

## CI example (GitHub Actions)

```yaml
name: regex-lint
on: [pull_request]

jobs:
  regex:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      - run: composer install --no-interaction --no-progress
      - run: vendor/bin/regex lint src/ --format=github
```

---

Previous: [Regex in PHP](regex-in-php.md) | Next: [Diagnostics](../reference/diagnostics.md)
