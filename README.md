<p align="center">
    <img src="art/banner.svg?v=1" alt="RegexParser" width="100%">
</p>

<p align="center">
    <strong>Treat Regular Expressions as Code.</strong>
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

# RegexParser: Parse, Analyze, and Secure PHP Regex

> **New to regex?** The [Regex Tutorial](docs/tutorial/README.md) covers everything from basics to PCRE mastery.

RegexParser is a PHP 8.2+ library that parses regular expressions into a typed AST. Built for tool maintainers who need to analyze, validate, or transform regex patterns in their CI/CD pipelines, static analyzers, or framework integrations.

## CLI at a Glance

### Lint Your Codebase

```bash
$ vendor/bin/regex lint src/
```

![Regex Lint Output](docs/assets/regex-lint.png)

### Parse Patterns

```bash
$ vendor/bin/regex parse '/^hello world$/'
RegexNode
└── SequenceNode
    ├── AnchorNode("^")
    ├── LiteralNode("hello")
    ├── LiteralNode(" ")
    ├── LiteralNode("world")
    └── AnchorNode("$")
```

### Analyze ReDoS Risk

```bash
$ vendor/bin/regex analyze '/(a+)+$/'
ReDoS Analysis
  Pattern:  /(a+)+$/
  Severity: CRITICAL
  Fix:      Use possessive quantifiers or atomic groups
```

### Explain Patterns

```bash
$ vendor/bin/regex explain '/\d{4}-\d{2}-\d{2}/'
"Match exactly 4 digits, hyphen, 2 digits, hyphen, 2 digits"
```

### Highlight Syntax

```bash
$ vendor/bin/regex highlight '/\d+/'
[32m\d[0m[33m+[0m
```

## What RegexParser Provides

```
┌─────────────────────────────────────────────────────────────────┐
│                    RegexParser Architecture                     │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│   Input Pattern         Parser           AST                    │
│   "/^hello$/i"  ──►  Tokenizer ──►  RegexNode                   │
│                                   └── SequenceNode              │
│                                           └── [LiteralNode]     │
│                                                                 │
│                            │                                    │
│                            ▼                                    │
│   ┌─────────────────────────────────────────────────────────┐   │
│   │                     Visitors                            │   │
│   ├─────────────────────────────────────────────────────────┤   │
│   │  ValidatorNodeVisitor    → Validation errors            │   │
│   │  LinterNodeVisitor       → Code quality issues          │   │
│   │  ReDoSAnalyzerNodeVisitor → Security vulnerabilities    │   │
│   │  ExplainNodeVisitor      → Human-readable output        │   │
│   │  CompilerNodeVisitor     → Pattern reconstruction       │   │
│   └─────────────────────────────────────────────────────────┘   │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### For Static Analysis Tools

```php
use RegexParser\Regex;

// Detect regex issues in user code
$ast = Regex::create()->parse($userPattern);
$result = $ast->accept(new LinterNodeVisitor());

foreach ($result->getIssues() as $issue) {
    // Report to PHPStan/Psalm/Rector
    reportIssue($issue->getMessage(), $issue->getSeverity());
}
```

### For Framework Integrations

```php
// Symfony validator constraint
class RegexConstraintValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        $regex = Regex::create(['runtime_pcre_validation' => true]);
        $result = $regex->validate($constraint->pattern);

        if (!$result->isValid()) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ error }}', $result->error)
                ->addViolation();
        }

        // Check ReDoS safety
        $analysis = $regex->redos($constraint->pattern);
        if ($analysis->severity->value !== 'safe') {
            $this->context->buildViolation($constraint->redosMessage)
                ->setParameter('{{ severity }}', $analysis->severity->value)
                ->addViolation();
        }
    }
}
```

### For CI/CD Pipelines

```bash
# JSON output for CI integration
vendor/bin/regex lint src/ --format=json > regex-issues.json
```

```json
{
  "stats": { "errors": 2, "warnings": 5 },
  "results": [
    {
      "file": "src/Service/Payment.php",
      "line": 128,
      "pattern": "/(a+)+$/",
      "issues": [
        {
          "type": "error",
          "message": "Nested quantifiers detected",
          "issueId": "regex.lint.quantifier.nested"
        }
      ]
    }
  ]
}
```

## Installation

```bash
composer require yoeunes/regex-parser
```

## Quick API Reference

```php
use RegexParser\Regex;

$regex = Regex::create([
    'cache' => '/var/cache/regex-parser',
    'runtime_pcre_validation' => true,
]);

// Parse pattern into AST
$ast = $regex->parse('/^hello world$/i');

// Validate syntax
$result = $regex->validate('/(?<=test)foo/');
if (!$result->isValid()) {
    echo $result->error;  // "Variable-length lookbehind is not supported"
}

// Check ReDoS safety
$analysis = $regex->redos('/(a+)+$/');
echo $analysis->severity->value;  // 'critical'

// Get plain-English explanation
echo $regex->explain('/\d{4}-\d{2}-\d{2}/');
// "Four digits, hyphen, two digits, hyphen, two digits"
```

## Integrations

### Symfony

```bash
bin/console regex:lint src/ --format=console
```

Enable the bundle:

```php
// config/bundles.php
Yoeunes\RegexParser\Symfony\RegexParserBundle::class => ['all' => true],
```

### PHPStan

```neon
includes:
    - vendor/yoeunes/regex-parser/extension.neon
```

Reports patterns like `preg_match('/(a+)+$/', $input)` as ReDoS vulnerabilities.

### Rector

Use RegexParser to detect and automatically fix regex issues in refactoring rules.

### GitHub Actions

```yaml
name: Regex Lint
on: [push, pull_request]

jobs:
  lint:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      - run: composer install --no-interaction
      - run: vendor/bin/regex lint src/ --format=github
```

## Features

| Feature            | Description                                                  |
|--------------------|--------------------------------------------------------------|
| **Parser**         | Full PCRE2 AST with typed nodes                              |
| **Validator**      | Syntax + semantic error detection                            |
| **ReDoS Analyzer** | Static detection of catastrophic backtracking                |
| **Explainer**      | Human-readable pattern descriptions                          |
| **Optimizer**      | Performance suggestions (shorthands, possessive quantifiers) |
| **CLI**            | Full-featured command-line tool                              |
| **Visitors**       | Extensible analysis via visitor pattern                      |
| **Caching**        | Persistent AST cache for CI performance                      |
| **PHPStan Rule**   | First-party integration for static analysis                  |

## Documentation

### 🎯 New to Regex

Start here to learn regex from scratch:

1. **[Regex Tutorial](docs/tutorial/README.md)** - Complete step-by-step guide
2. **[Quick Start](docs/QUICK_START.md)** - Common use cases with examples
3. **[Regex in PHP](docs/guides/regex-in-php.md)** - How regex works in PHP

### 🔧 Use RegexParser Tools

1. **[CLI Guide](docs/guides/cli.md)** - Command-line usage
2. **[Cookbook](docs/COOKBOOK.md)** - Ready-to-use patterns
3. **[ReDoS Guide](docs/REDOS_GUIDE.md)** - Prevent catastrophic backtracking

### 🛠 Integrate or Extend

1. **[API Reference](docs/reference/api.md)** - Full API documentation
2. **[Architecture](docs/ARCHITECTURE.md)** - How it works internally
3. **[Extending Guide](docs/EXTENDING_GUIDE.md)** - Custom visitors and analysis

## Contributing

Contributions are welcome! Please read [CONTRIBUTING.md](CONTRIBUTING.md) before submitting PRs.

```bash
composer install
composer phpunit     # Run tests
composer phpcs       # Code style
composer phpstan     # Static analysis
```

## License

Released under the [MIT License](LICENSE).

---

<p align="center">
  <b>Made with ❤️ by <a href="https://www.linkedin.com/in/younes--ennaji/">Younes ENNAJI</a></b>
</p>
