<p align="center">
    <img src="art/banner.svg?v=1" alt="RegexParser" width="100%">
</p>

<p align="center">
    <strong>Treat regular expressions as code.</strong>
</p>

<p align="center">
    <a href="https://www.linkedin.com/in/younes--ennaji"><img src="https://img.shields.io/badge/author-@yoeunes-blue.svg" alt="Author Badge"></a>
    <a href="https://github.com/yoeunes/regex-parser/releases"><img src="https://img.shields.io/github/tag/yoeunes/regex-parser.svg" alt="GitHub Release Badge"></a>
    <a href="https://github.com/yoeunes/regex-parser/blob/main/LICENSE"><img src="https://img.shields.io/badge/license-MIT-brightgreen.svg" alt="License Badge"></a>
    <a href="https://packagist.org/packages/yoeunes/regex-parser"><img src="https://img.shields.io/packagist/dt/yoeunes/regex-parser.svg" alt="Packagist Downloads Badge"></a>
    <a href="https://github.com/yoeunes/regex-parser"><img src="https://img.shields.io/github/stars/yoeunes/regex-parser.svg" alt="GitHub Stars Badge"></a>
    <a href="https://packagist.org/packages/yoeunes/regex-parser"><img src="https://img.shields.io/packagist/php-v/yoeunes/regex-parser.svg" alt="Supported PHP Version Badge"></a>
</p>

---

# RegexParser: Parse and analyze PCRE patterns in PHP

RegexParser is a PHP 8.2+ library that parses PCRE regex literals into a typed AST and runs analysis through visitors. It is built for learning, validation, and tooling in PHP projects.

Project goals:
- Make regex approachable for newcomers with clear explanations and practical examples.
- Provide a stable foundation for validation, linting, and security analysis.
- Aim to become a common community reference for working with regex in PHP by staying accurate, transparent, and easy to integrate.

If you are new to regex, start with the [Regex Tutorial](docs/tutorial/README.md). If you want a short overview, see the [Quick Start Guide](docs/QUICK_START.md).

## Getting started

```bash
# Install the library
composer require yoeunes/regex-parser

# Try the CLI
vendor/bin/regex explain '/\d{4}-\d{2}-\d{2}/'
```

## What RegexParser provides

- Parse `/pattern/flags` into a structured AST.
- Validate syntax and semantics with precise error locations.
- Explain patterns in plain English.
- Analyze ReDoS risk and suggest safer alternatives.
- Lint codebases via the CLI.
- Provide a visitor API for custom tooling.

## How it works

- `Regex::parse()` splits the literal into pattern and flags.
- The lexer produces a token stream.
- The parser builds an AST (`RegexNode`).
- Visitors walk the AST to validate, explain, analyze, or transform.

For the full architecture, see [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

## CLI quick tour

```bash
# Parse and validate a pattern
vendor/bin/regex parse '/^hello world$/'

# Get plain English explanation
vendor/bin/regex explain '/\d{4}-\d{2}-\d{2}/'

# Check for security issues (ReDoS)
vendor/bin/regex analyze '/(a+)+$/'

# Colorize pattern for better readability
vendor/bin/regex highlight '/\d+/'

# Lint your entire codebase
vendor/bin/regex lint src/
```

![Regex Lint Output](docs/assets/regex-lint.png)

## PHP API at a glance

```php
use RegexParser\Regex;

$regex = Regex::create([
    'runtime_pcre_validation' => true,
]);

// Parse a pattern into AST
$ast = $regex->parse('/^hello world$/i');

// Validate pattern safety
$result = $regex->validate('/(?<=test)foo/');
if (!$result->isValid()) {
    echo $result->getErrorMessage();
}

// Check for ReDoS vulnerabilities
$analysis = $regex->redos('/(a+)+$/');
echo $analysis->severity->value; // 'critical', 'safe', etc.

// Get human-readable explanation
echo $regex->explain('/\d{4}-\d{2}-\d{2}/');
```

## Integrations

RegexParser integrates with common PHP tooling:

- **Symfony bundle**: [docs/guides/cli.md](docs/guides/cli.md)
- **PHPStan**: `vendor/yoeunes/regex-parser/extension.neon`
- **Rector**: Custom refactoring rules
- **GitHub Actions**: `vendor/bin/regex lint` in your CI pipeline

## Performance

RegexParser ships lightweight benchmark scripts in `benchmarks/` to track parser, compiler, and formatter throughput.

- Run formatter benchmarks: `php benchmarks/benchmark_formatters.php`
- Run all benchmarks: `for file in benchmarks/benchmark_*.php; do echo "Running $file"; php "$file"; echo; done`

## Documentation

Start here:
- [Docs Home](docs/README.md)
- [Quick Start](docs/QUICK_START.md)
- [Tutorial](docs/tutorial/README.md)

Key references:
- [Architecture](docs/ARCHITECTURE.md)
- [API Reference](docs/reference/api.md)
- [Diagnostics](docs/reference/diagnostics.md)
- [FAQ & Glossary](docs/reference/faq-glossary.md)

## Contributing

Contributions are welcome! See [`CONTRIBUTING.md`](CONTRIBUTING.md) to get started.

```bash
# Set up development environment
composer install

# Run tests
composer phpunit

# Check code style
composer phpcs

# Run static analysis
composer phpstan
```

## License

Released under the [MIT License](LICENSE).

## Support

If you run into issues or have questions, please open an issue on GitHub: <https://github.com/yoeunes/regex-parser/issues>.
