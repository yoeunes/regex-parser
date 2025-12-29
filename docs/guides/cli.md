# CLI Guide

RegexParser ships with a CLI for parsing, validation, analysis, and linting.
This guide covers everyday usage and CI integration.

## Install

If installed via Composer, use `vendor/bin/regex` (all examples below):

```bash
vendor/bin/regex --help
```

Or install the PHAR:

```bash
curl -Ls https://github.com/yoeunes/regex-parser/releases/latest/download/regex.phar -o ~/.local/bin/regex
chmod +x ~/.local/bin/regex
```

If you use the PHAR, replace `vendor/bin/regex` with `regex`.

## Quick commands

Lint a codebase (best first step):

```bash
vendor/bin/regex lint src/ --format=console --min-savings=2
```

Analyze a single pattern:

```bash
vendor/bin/regex analyze '/(a+)+$/'
```

Analyze a pattern (includes explanation):

```bash
vendor/bin/regex analyze '/^(?<user>\w+)@(?<host>\w+)$/'
```

Highlight for HTML:

```bash
vendor/bin/regex highlight '/^\d{4}-\d{2}-\d{2}$/' --format=html
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
  "exclude": ["vendor", "var"],
  "ide": "phpstorm"
}
```

### IDE Integration

Configure your IDE for clickable file links in the lint output:

**Supported IDE options:**
- `"phpstorm"` - `phpstorm://open?file=%f&line=%l`
- `"textmate"` - `txmt://open?url=file://%f&line=%l`
- `"macvim"` - `mvim://open?url=file://%f&line=%l`
- `"emacs"` - `emacs://open?url=file://%f&line=%l`
- `"sublime"` - `subl://open?url=file://%f&line=%l`
- `"atom"` - `atom://core/open/file?filename=%f&line=%l`
- `"vscode"` - `vscode://file/%f:%l`
- Custom template with `%f` for file and `%l` for line

Example with PhpStorm:
```json
{
  "ide": "phpstorm"
}
```

The pen emoji (✏️) next to file locations will be clickable and open the file at the correct line in your IDE. Set to empty string `""` to disable clickable links.

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
