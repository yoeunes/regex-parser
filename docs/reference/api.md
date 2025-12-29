# API Reference

This page documents the public API surface of RegexParser: entry points,
configuration options, return objects, and the exception map.

## Entry points

### Regex::create(array $options = []): Regex

Creates a configured Regex instance.

```php
use RegexParser\Regex;

$regex = Regex::create([
    'max_pattern_length' => 100_000,
    'max_lookbehind_length' => 255,
    'cache' => null,
    'redos_ignored_patterns' => [],
    'runtime_pcre_validation' => false,
    'max_recursion_depth' => 1024,
    'php_version' => '8.2',
]);
```

### Regex::new(array $options = []): Regex

Alias for `Regex::create()`.

### Regex::tokenize(string $regex, ?int $phpVersionId = null): TokenStream

Lexes a regex into a `TokenStream` with positional offsets.

## Configuration options

All options are validated; unknown keys throw `InvalidRegexOptionException`.

| Option | Type | Default | Description |
| --- | --- | --- | --- |
| `max_pattern_length` | `int` | `100_000` | Maximum pattern length accepted. |
| `max_lookbehind_length` | `int` | `255` | Maximum allowed lookbehind length. |
| `cache` | `null` \| `string` \| `CacheInterface` | `null` | `null` uses `NullCache`. String uses `FilesystemCache`. |
| `redos_ignored_patterns` | `array<string>` | `[]` | Patterns to skip during ReDoS analysis. |
| `runtime_pcre_validation` | `bool` | `false` | Compile-check via `preg_match()` for caret diagnostics. |
| `max_recursion_depth` | `int` | `1024` | Parser recursion guard. |
| `php_version` | `string` \| `int` | `PHP_VERSION_ID` | Target PHP version for feature validation. |

## Parsing

### parsePattern(string $pattern, string $flags = '', string $delimiter = '/')

Parses a pattern body plus flags/delimiter into a `RegexNode`.

### parse(string $regex, bool $tolerant = false)

Parses a full PCRE string (`/pattern/flags`).

- `tolerant = false`: returns `RegexNode` or throws.
- `tolerant = true`: returns `TolerantParseResult` with an AST plus errors.

## Validation and analysis

### validate(string $regex): ValidationResult

Returns a structured validation result (no exception bubbling).

### analyze(string $regex): AnalysisReport

Aggregates validation, lint, ReDoS analysis, optimization, and explanation
into a single report.

### redos(string $regex, ?ReDoSSeverity $threshold = null): ReDoSAnalysis

Returns ReDoS severity, score, hotspots, and recommendations.

## Transform and extract

### optimize(string $regex, array $options = []): OptimizationResult

Optimization options:
- `digits` (bool, default true): `[0-9]` -> `\d`
- `word` (bool, default true): `[A-Za-z0-9_]` -> `\w`
- `ranges` (bool, default true): normalize ranges
- `autoPossessify` (bool, default false): opportunistic possessive quantifiers

### literals(string $regex): LiteralExtractionResult

Returns literals and prefix/suffix data for fast prefilters.

### generate(string $regex): string

Generates a sample string that matches the regex.

### explain(string $regex, string $format = 'text'): string

`format` is `text` or `html`.

### highlight(string $regex, string $format = 'console'): string

`format` is `console` or `html`.

## Result objects

### ValidationResult

Fields: `isValid`, `error`, `errorCode`, `offset`, `caretSnippet`, `hint`,
`complexityScore`, `category` (`ValidationErrorCategory`).

### TolerantParseResult

Fields: `ast` (RegexNode), `errors` (array<Throwable>).

### AnalysisReport

Fields: `isValid`, `errors`, `lintIssues`, `redos`, `optimizations`,
`explain`, `highlighted`.

### OptimizationResult

Fields: `original`, `optimized`, `changes`.

### LiteralExtractionResult

Fields: `literals`, `patterns`, `confidence`, `literalSet` (LiteralSet).

### ReDoSAnalysis

Fields: `severity`, `score`, `vulnerablePart`, `vulnerableSubpattern`, `error`,
`recommendations`, `findings`, `hotspots`, `suggestedRewrite`, `trigger`,
`confidence`, `falsePositiveRisk`.

## Exception map

- `RegexParserExceptionInterface`: catch-all for library errors.
- `InvalidRegexOptionException`: invalid `Regex::create()` options.
- `LexerException`: tokenization failure.
- `ParserException`: syntax/structure errors.
- `SyntaxErrorException`: specialized parser error for invalid syntax.
- `SemanticErrorException`: semantic validation failure (includes `hint`).
- `RecursionLimitException`: exceeded max recursion depth.
- `ResourceLimitException`: exceeded resource limits while parsing.
- `RegexException`: base exception with `position`, `snippet`, `errorCode`.

---

Previous: [Reference Index](README.md) | Next: [Diagnostics](diagnostics.md)
