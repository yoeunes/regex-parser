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

# **RegexParser**: Parse, analyze, and learn **PCRE** in PHP

**RegexParser** is a PHP 8.2+ library that turns **PCRE** patterns into a typed **AST** and runs analysis through **visitors**. The goal is twofold: make regex approachable for newcomers and give tool authors a reliable foundation for validation, linting, and security analysis.

New to regex? Start with the [Regex Tutorial](docs/tutorial/README.md).

## Start Here

- Docs home: [`docs/README.md`](docs/README.md)
- Quick onboarding: [`docs/QUICK_START.md`](docs/QUICK_START.md)
- PCRE in PHP: [`docs/guides/regex-in-php.md`](docs/guides/regex-in-php.md)

## What RegexParser Does

- Parse `/pattern/flags` into a structured AST
- Validate syntax and semantics with precise byte offsets
- Explain patterns in plain language
- Analyze ReDoS risk and suggest safer alternatives
- Power CLI linting for codebases and CI
- Provide a visitor API for custom tools

## How It Works (High Level)

```
/^hello$/i
  |
  v
Lexer  -> TokenStream
Parser -> RegexNode (AST)
          |
          v
       Visitors -> validation, explanation, analysis, transforms
```

See [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) for the full internal design and algorithms.

## CLI Quick Tour

```bash
vendor/bin/regex parse '/^hello world$/'
vendor/bin/regex explain '/\d{4}-\d{2}-\d{2}/'
vendor/bin/regex analyze '/(a+)+$/'
vendor/bin/regex highlight '/\d+/'
vendor/bin/regex lint src/
```

![Regex Lint Output](docs/assets/regex-lint.png)

## PHP API at a Glance

```php
use RegexParser\Regex;

$regex = Regex::create([
    'runtime_pcre_validation' => true,
]);

$ast = $regex->parse('/^hello world$/i');

$result = $regex->validate('/(?<=test)foo/');
if (!$result->isValid()) {
    echo $result->getErrorMessage();
}

$analysis = $regex->redos('/(a+)+$/');
echo $analysis->severity->value;

echo $regex->explain('/\d{4}-\d{2}-\d{2}/');
```

## Integrations

- Symfony bundle: [`docs/guides/cli.md`](docs/guides/cli.md)
- PHPStan: `vendor/yoeunes/regex-parser/extension.neon`
- Rector rules and custom refactors
- GitHub Actions via `vendor/bin/regex lint`

## Documentation

- Learn regex: [`docs/tutorial/README.md`](docs/tutorial/README.md)
- CLI usage: [`docs/guides/cli.md`](docs/guides/cli.md)
- Cookbook: [`docs/COOKBOOK.md`](docs/COOKBOOK.md)
- ReDoS: [`docs/REDOS_GUIDE.md`](docs/REDOS_GUIDE.md)
- API reference: [`docs/reference/api.md`](docs/reference/api.md)
- Internals: [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md)
- Extending: [`docs/EXTENDING_GUIDE.md`](docs/EXTENDING_GUIDE.md)

## Contributing

Contributions are welcome. See [`CONTRIBUTING.md`](CONTRIBUTING.md) to get started.

```bash
composer install
composer phpunit
composer phpcs
composer phpstan
```

## License

Released under the [MIT License](LICENSE).

---

<p align="center">
  <b>Made by <a href="https://www.linkedin.com/in/younes--ennaji/">Younes ENNAJI</a></b>
</p>
