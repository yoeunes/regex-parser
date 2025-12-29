# API Reference

This reference documents the public RegexParser API: how to create a configured instance, which methods are available, and what each return type contains.

> We use `Regex::create()` in all examples so configuration is explicit and validated.

## Entry Points

### Regex::create(array $options = []): Regex

Factory for a configured `Regex` instance.

```
Regex::create([options])
    -> validates options
    -> builds Regex instance
    -> ready for parse/validate/analyze
```

```php
use RegexParser\Regex;

$regex = Regex::create([
    'cache' => '/var/cache/regex-parser',
    'runtime_pcre_validation' => true,
]);
```

### Regex::new(array $options = []): Regex

Alias for `Regex::create()`. Prefer `Regex::create()` for consistency in docs and examples.

### Regex::tokenize(string $regex, ?int $phpVersionId = null): TokenStream

Lex a pattern into a `TokenStream` with byte offsets. Useful for debugging or custom tooling.

```php
use RegexParser\Regex;

$stream = Regex::tokenize('/foo|bar/i');

foreach ($stream as $token) {
    echo $token->type->value." ".$token->value."\n";
}
```

### Regex::clearValidatorCaches(): void

Clears static caches used by validation. Call this in long-running workers.

```php
$regex = Regex::create();

foreach ($patterns as $i => $pattern) {
    $regex->validate($pattern);

    if (0 === ($i + 1) % 100) {
        $regex->clearValidatorCaches();
    }
}
```

## Configuration Options

All options are validated. Unknown keys throw `InvalidRegexOptionException`.

| Option | Type | Default | Description |
| --- | --- | --- | --- |
| `cache` | `null` \| `string` \| `CacheInterface` | `FilesystemCache` | Where parsed ASTs are cached |
| `max_pattern_length` | `int` | `100000` | Maximum pattern length |
| `max_lookbehind_length` | `int` | `255` | Maximum lookbehind length |
| `runtime_pcre_validation` | `bool` | `false` | Compile-check via `preg_match()` |
| `redos_ignored_patterns` | `array<string>` | `[]` | Patterns to skip ReDoS analysis |
| `max_recursion_depth` | `int` | `1024` | Parser recursion guard |
| `php_version` | `string` \| `int` | `PHP_VERSION_ID` | Target PHP version for validation |

## Parsing

### parsePattern(string $pattern, string $flags = '', string $delimiter = '/'): RegexNode

Parses a pattern body plus flags and delimiter into a `RegexNode`.

```php
$ast = Regex::create()->parsePattern('foo|bar', 'i', '/');
```

### parse(string $regex, bool $tolerant = false): RegexNode|TolerantParseResult

Parses a full PCRE string (`/pattern/flags`).

```php
$ast = Regex::create()->parse('/foo|bar/i');

$result = Regex::create()->parse('/[unclosed/i', true);
```

## Validation and Analysis

### validate(string $regex): ValidationResult

Structured validation without throwing exceptions.

```php
$result = Regex::create()->validate('/(?<=a+)b/');

if (!$result->isValid()) {
    echo $result->error;
    echo $result->caretSnippet;
}
```

### analyze(string $regex): AnalysisReport

Full analysis: validation, lint, ReDoS, optimization, explanation, highlighting.

```php
$report = Regex::create()->analyze('/(a+)+b/');
```

### redos(string $regex, ?ReDoSSeverity $threshold = null): ReDoSAnalysis

Targeted ReDoS analysis.

```php
$analysis = Regex::create()->redos('/(a+)+b/');
```

## Transform and Extract

### optimize(string $regex, array $options = []): OptimizationResult

```php
$result = Regex::create()->optimize('/[0-9]+/', [
    'digits' => true,
    'word' => true,
    'ranges' => true,
    'autoPossessify' => false,
    'allowAlternationFactorization' => false,
]);
```

### literals(string $regex): LiteralExtractionResult

```php
$result = Regex::create()->literals('/user-\d{4}/');
```

### generate(string $regex): string

```php
$sample = Regex::create()->generate('/[A-Z][a-z]{3,5}\d{2}/');
```

### explain(string $regex, string $format = 'text'): string

```php
$text = Regex::create()->explain('/\d{3}-\d{4}/');
$html = Regex::create()->explain('/\w+@\w+\.\w+/', 'html');
```

### highlight(string $regex, string $format = 'console'): string

```php
$console = Regex::create()->highlight('/\d+/', 'console');
$html = Regex::create()->highlight('/[a-z]+/', 'html');
```

## Result Objects (Key Fields)

### ValidationResult

| Field | Type | Meaning |
| --- | --- | --- |
| `isValid` | bool | Valid syntax and semantics |
| `error` | string\|null | Error message |
| `errorCode` | string\|null | Stable error code |
| `offset` | int\|null | Byte offset |
| `caretSnippet` | string\|null | Snippet with caret |
| `hint` | string\|null | Suggested fix |
| `complexityScore` | int | Complexity score |
| `category` | ValidationErrorCategory | Error category |

### TolerantParseResult

| Field | Type | Meaning |
| --- | --- | --- |
| `ast` | RegexNode | Best-effort AST |
| `errors` | array | Parse errors |

### AnalysisReport

| Field | Type | Meaning |
| --- | --- | --- |
| `isValid` | bool | Validation status |
| `errors` | array | Validation errors |
| `lintIssues` | array | Lint findings |
| `redos` | ReDoSAnalysis | ReDoS analysis |
| `optimizations` | OptimizationResult | Suggested optimizations |
| `explain` | string | Explanation text |
| `highlighted` | string | Highlighted output |

### ReDoSAnalysis

| Field | Type | Meaning |
| --- | --- | --- |
| `severity` | ReDoSSeverity | Risk level |
| `score` | int | Risk score (0-10) |
| `confidence` | ReDoSConfidence | Confidence level |
| `vulnerablePart` | string\|null | Problematic subpattern |
| `recommendations` | array | Suggested fixes |
| `hotspots` | array | Hotspot locations |
| `suggestedRewrite` | string\|null | Safer rewrite |

### OptimizationResult

| Field | Type | Meaning |
| --- | --- | --- |
| `original` | string | Original pattern |
| `optimized` | string | Optimized pattern |
| `changes` | array | Applied changes |

### LiteralExtractionResult

| Field | Type | Meaning |
| --- | --- | --- |
| `literals` | array | Extracted literals |
| `patterns` | array | Search patterns |
| `confidence` | string | Confidence level |
| `literalSet` | LiteralSet | Raw literal set |

## Exceptions

RegexParser exposes a focused hierarchy. See `docs/reference/diagnostics.md` for error codes and `docs/reference.md` for lint identifiers.

---

Previous: `README.md` | Next: `diagnostics.md`
