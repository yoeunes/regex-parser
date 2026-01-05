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
- Stay accurate, transparent, and easy to integrate.

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
- Analyze potential ReDoS risk (theoretical by default) and provide cautious suggestions.
- Lint codebases via the CLI.
- Provide a visitor API for custom tooling.

## Philosophy & Accuracy

RegexParser separates what it can guarantee from what is heuristic:

- Guaranteed: parsing, AST structure, error offsets, and syntax validation for the targeted PHP/PCRE version.
- Heuristic: ReDoS analysis is structural and conservative; treat it as potential risk unless confirmed.
- Context matters: PCRE version, JIT, and backtrack/recursion limits change practical impact.

## How to report a vulnerability responsibly

If you believe a pattern is exploitable:

1. Run confirmed mode and capture a bounded, reproducible PoC.
2. Include the pattern, input lengths, timings, JIT setting, and PCRE limits.
3. Verify impact in the real code path before filing a security issue.

See [SECURITY.md](SECURITY.md) for reporting channels.

## Safer rewrites (verify behavior)

These techniques reduce backtracking but can change matching behavior. Always validate with tests.

```
/(a+)+$/     -> /a+$/      (semantics often preserved, but verify captures)
/(a+)+$/     -> /a++$/     (possessive, no backtracking)
/(a|aa)+/    -> /a+/       (only if alternation is redundant)
/(a|aa)+/    -> /(?>a|aa)+/ (atomic, avoids backtracking)
```

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

# Check for potential ReDoS risk (theoretical by default)
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
use RegexParser\ReDoS\ReDoSMode;

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

// Check for ReDoS risk (theoretical by default)
$analysis = $regex->redos('/(a+)+$/');
echo $analysis->severity->value; // 'critical', 'safe', etc.

// Optional: attempt bounded confirmation
$confirmed = $regex->redos('/(a+)+$/', mode: ReDoSMode::CONFIRMED);
echo $confirmed->isConfirmed() ? 'confirmed' : 'theoretical';

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
