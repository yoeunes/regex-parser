# Maintainers Guide

This guide is for framework maintainers, library maintainers, and tooling maintainers
who want to integrate RegexParser as a first-class analysis component.

## Configuration reference (`Regex::create($options)`)

`Regex::create()` accepts a validated array of options. Invalid keys raise `InvalidRegexOptionException`.

| Option | Type | Default | Description |
| --- | --- | --- | --- |
| `cache` | `null` \| `string` \| `CacheInterface` | `FilesystemCache` (`sys_get_temp_dir()/regex-parser/cache`) | Cache for parsed ASTs and analysis results. `null` disables caching (`NullCache`). A string path uses `FilesystemCache`. |
| `max_pattern_length` | `int` | `100_000` | Upper bound on pattern length. Protects against memory and CPU spikes. |
| `max_lookbehind_length` | `int` | `255` | Maximum allowed lookbehind length. Prevents unbounded lookbehinds. |
| `runtime_pcre_validation` | `bool` | `false` | When true, validates against PCRE runtime and provides caret diagnostics. |
| `redos_ignored_patterns` | `array<string>` | `[]` | Patterns to skip during ReDoS analysis. Useful for false positives or trusted patterns. |
| `max_recursion_depth` | `int` | `1024` | Maximum parser recursion depth. Avoids runaway recursion on pathological input. |
| `php_version` | `string` \| `int` | `PHP_VERSION_ID` | Target PHP version for feature validation. Accepts version strings like `"8.2"` or a PHP_VERSION_ID integer. |

Example:

```php
use RegexParser\Regex;

$regex = Regex::create([
    'cache' => '/var/cache/regex',
    'max_pattern_length' => 100_000,
    'max_lookbehind_length' => 255,
    'runtime_pcre_validation' => false,
    'redos_ignored_patterns' => [
        '/^([0-9]{4}-[0-9]{2}-[0-9]{2})$/',
    ],
    'max_recursion_depth' => 1024,
    'php_version' => '8.2',
]);
```

## Exception hierarchy

RegexParser exposes a small, stable exception surface:

- `RegexParserExceptionInterface` (catch-all interface for parser/lexer failures)
- `LexerException` (tokenization errors)
- `ParserException` (grammar or structural errors)

Example handling:

```php
use RegexParser\Exception\LexerException;
use RegexParser\Exception\ParserException;
use RegexParser\Exception\RegexParserExceptionInterface;

try {
    $ast = RegexParser\Regex::create()->parse('/[a-z]+/');
} catch (LexerException|ParserException $e) {
    // expected parse-time errors
} catch (RegexParserExceptionInterface $e) {
    // any other parser/lexer error
}
```

## JSON output schema (CLI linting)

Use `vendor/bin/regex lint --format=json` for machine-readable output. The JSON payload includes a `stats` object
and a `results` array. Each result includes issues and optimization entries for a single pattern occurrence.

Example output:

```json
{
    "stats": {
        "errors": 0,
        "warnings": 1,
        "optimizations": 1
    },
    "results": [
        {
            "file": "src/Service/EmailValidator.php",
            "line": 42,
            "source": "php",
            "pattern": "/^[a-z0-9._%+-]+@[a-z0-9-]+(?:\\.[a-z0-9-]+)+$/i",
            "location": null,
            "issues": [
                {
                    "type": "warning",
                    "message": "Nested quantifiers detected. Consider using atomic groups or possessive quantifiers.",
                    "file": "src/Service/EmailValidator.php",
                    "line": 42,
                    "column": 9,
                    "issueId": "regex.lint.quantifier.nested",
                    "hint": "Use atomic groups (?>...) or possessive quantifiers (*+, ++).",
                    "source": "php"
                }
            ],
            "optimizations": [
                {
                    "file": "src/Service/EmailValidator.php",
                    "line": 42,
                    "optimization": {
                        "original": "/[0-9]+/",
                        "optimized": "/\\d+/",
                        "changes": [
                            "Optimized pattern."
                        ]
                    },
                    "savings": 2,
                    "source": "php"
                }
            ]
        }
    ]
}
```

Notes:

- `issues[]` may include `analysis` or `validation` metadata depending on the rule.
- `optimizations[]` is emitted only when `--no-optimize` is not set and savings exceed `--min-savings`.
- Optional fields may be omitted if the source does not provide them.

---

Previous: [Architecture](ARCHITECTURE.md) | Next: [Extending Guide](EXTENDING_GUIDE.md)
