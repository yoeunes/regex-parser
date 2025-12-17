<p align="center">
    <img src="art/banner.svg?v=1" alt="RegexParser" width="100%">
</p>

<p align="center">
    <strong>Treat Regular Expressions as Code.</strong>
</p>

<p align="center">
    <a href="https://github.com/yoeunes/regex-parser/actions"><img src="https://img.shields.io/github/actions/workflow/status/yoeunes/regex-parser/ci.yml" alt="CI Status"></a>
    <a href="https://www.linkedin.com/in/younes--ennaji"><img src="https://img.shields.io/badge/author-@yoeunes-blue.svg" alt="Author Badge"></a>
    <a href="https://github.com/yoeunes/regex-parser"><img src="https://img.shields.io/badge/source-yoeunes/regex--parser-blue.svg" alt="Source Code Badge"></a>
    <a href="https://github.com/yoeunes/regex-parser/releases"><img src="https://img.shields.io/github/tag/yoeunes/regex-parser.svg" alt="GitHub Release Badge"></a>
    <a href="https://github.com/yoeunes/regex-parser/blob/main/LICENSE"><img src="https://img.shields.io/badge/license-MIT-brightgreen.svg" alt="License Badge"></a>
    <a href="https://packagist.org/packages/yoeunes/regex-parser"><img src="https://img.shields.io/packagist/dt/yoeunes/regex-parser.svg" alt="Packagist Downloads Badge"></a>
    <a href="https://github.com/yoeunes/regex-parser"><img src="https://img.shields.io/github/stars/yoeunes/regex-parser.svg" alt="GitHub Stars Badge"></a>
    <a href="https://packagist.org/packages/yoeunes/regex-parser"><img src="https://img.shields.io/packagist/php-v/yoeunes/regex-parser.svg" alt="Supported PHP Version Badge"></a>
</p>

---

**RegexParser transforms opaque PCRE strings into a structured Abstract Syntax Tree.**

It brings static analysis, security auditing, and automated refactoring to PHP's most powerful yet misunderstood tool. Stop treating regexes as magic strings; start treating them as logic.

### Core Capabilities

* **Deep Parsing** — Full support for advanced PCRE2 syntax including subroutines, conditionals, and recursion.
* **Security Auditing** — Detects Catastrophic Backtracking (ReDoS) risks and vulnerabilities at analysis time.
* **Documentation** — Automatically generates human-readable explanations, HTML visualizations, and valid sample strings.
* **Transformation** — Manipulate the AST to optimize or refactor patterns programmatically.
* **Integration** — First-class support for Symfony, PHPStan, Psalm, and Rector workflows.

> *"Think of it as `nikic/php-parser` — but for regexes."*

---

## Table of Contents

- [Installation](#installation)
- [Quick Start](#quick-start)
  - [Validate a regex](#validate-a-regex)
  - [Explain a regex](#explain-a-regex)
  - [Check ReDoS safety](#check-redos-safety)
- [Advanced Usage](#advanced-usage)
  - [Parsing bare patterns vs PCRE strings](#parsing-bare-patterns-vs-pcre-strings)
  - [Working with the AST](#working-with-the-ast)
   - [Writing a custom AST visitor](#writing-a-custom-ast-visitor)
   - [Optimizing and recompiling patterns](#optimizing-and-recompiling-patterns)
   - [Auto-Modernize Legacy Patterns](#auto-modernize-legacy-patterns)
   - [Syntax Highlighting](#syntax-highlighting)
- [ReDoS Analysis](#redos-analysis)
  - [What is ReDoS?](#what-is-redos)
  - [How RegexParser detects it](#how-regexparser-detects-it)
  - [Severity levels](#severity-levels)
- [Framework & Tooling Integration](#framework--tooling-integration)
  - [Symfony](#symfony)
  - [PHPStan](#phpstan)
  - [Psalm](#psalm)
  - [Rector](#rector)
- [Performance & Caching](#performance--caching)
- [API Overview](#api-overview)
- [Versioning & BC Policy](#versioning--bc-policy)
- [Support the Project](#support-the-project)
- [Contributing](#contributing)
- [License](#license)

---

## Installation

```bash
composer require yoeunes/regex-parser
```

Requires **PHP 8.2+**.

---

## Quick Start

### Validate a regex

*“Is this regex even valid?”*

```php
use RegexParser\Regex;

$regex = Regex::create();

// Full PCRE string: /pattern/flags
$result = $regex->validate('/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/i');

if ($result->isValid()) {
    echo "OK ✅\n";
} else {
    echo "Invalid regex: ".$result->getErrorMessage()."\n";
}
```

There’s also a tolerant parser:

```php
$tolerant = $regex->parseTolerant('/(unclosed(');

if ($tolerant->hasErrors()) {
    foreach ($tolerant->errors as $error) {
        echo "Error: ".$error->getMessage()."\n";
    }
}

// You still get a partial AST:
$ast = $tolerant->ast;
```

---

### Explain a regex

*“What does this pattern actually do?”*

```php
use RegexParser\Regex;

$regex = Regex::create();

echo $regex->explain('/^(?<user>[a-z0-9_]+)\.(?<domain>[a-z.]+)$/i');
```

Output example (simplified):

```text
Start of string
Named group "user":
  One or more of: letters, digits or underscore
Literal "."
Named group "domain":
  One or more of: letters or dots
End of string
```

You can also generate **HTML** explanations for documentation or debug UIs:

```php
$html = $regex->htmlExplain('/(foo|bar)+\d{2,4}/');
```

---

### Check ReDoS safety

*“Can this regex blow up my CPU?”*

```php
use RegexParser\Regex;
use RegexParser\ReDoS\ReDoSSeverity;

$regex = Regex::create();

$pattern  = '/^(a+)+$/'; // classic catastrophic backtracking example
$analysis = $regex->analyzeReDoS($pattern);

echo "Severity: ".$analysis->severity->value.PHP_EOL;
echo "Score: ".$analysis->score.PHP_EOL;

if (!$analysis->isSafe()) {
    echo "Hotspot: ".($analysis->vulnerablePart ?? 'unknown').PHP_EOL;

    foreach ($analysis->recommendations as $recommendation) {
        echo "- ".$recommendation.PHP_EOL;
    }
}

// Quick boolean check (for CI, input validation, etc.)
if (!$regex->isSafe($pattern, ReDoSSeverity::HIGH)) {
    throw new \RuntimeException('Regex is not safe enough for untrusted input.');
}
```

Under the hood it inspects quantifiers, nested groups, backreferences and character sets using a real AST, not just regex‑on‑regex strings.

---

## Configuration / Options

`Regex::create()` accepts a small, validated option array (or a `RegexOptions` value object via `RegexOptions::fromArray()`):

- `max_pattern_length` (int, default: `Regex::DEFAULT_MAX_PATTERN_LENGTH`).
- `cache` (`null` | path string | `RegexParser\Cache\CacheInterface`).
- `redos_ignored_patterns` (list of strings to skip in ReDoS analysis).

Unknown or invalid keys throw `RegexParser\Exception\InvalidRegexOptionException`.

---

## Advanced Usage

### Parsing bare patterns vs PCRE strings

Most high‑level methods (`parse`, `validate`, `analyzeReDoS`) expect a **full PCRE string**:

```php
$ast = $regex->parse('/pattern/ims');
```

If you only have the body, `parsePattern()` will wrap delimiters/flags for you:

```php
$ast = $regex->parsePattern('a|b', '#', 'i');
```

If you already have just the pattern body, you can go lower‑level:

```php
use RegexParser\Lexer;
use RegexParser\Parser;

$lexer  = new Lexer();
$parser = new Parser();

$stream = $lexer->tokenize('a|b');
$ast    = $parser->parse($stream, flags: '', delimiter: '/', patternLength: strlen('a|b'));
```

---

### Working with the AST

Every parsed regex becomes a tree of node objects under `RegexParser\Node\*`.

Example:

```php
use RegexParser\Regex;
use RegexParser\Node\AlternationNode;
use RegexParser\Node\LiteralNode;

$regex = Regex::create();
$ast   = $regex->parse('/foo|bar/');

$pattern = $ast->pattern;

if ($pattern instanceof AlternationNode) {
    foreach ($pattern->branches as $branch) {
        foreach ($branch->children as $child) {
            if ($child instanceof LiteralNode) {
                echo "Literal: ".$child->value.PHP_EOL;
            }
        }
    }
}
```

Each node exposes:

* `startPosition` / `endPosition`: byte offsets in the original pattern
* Node‑specific properties (e.g. `QuantifierNode::$min`, `$max`, `$type`)

---

### Writing a custom AST visitor

For experts: the “right” way to analyse patterns is to implement your own visitor.

```php
namespace App\Regex;

use RegexParser\Node\LiteralNode;
use RegexParser\Node\QuantifierNode;
use RegexParser\Node\RegexNode;
use RegexParser\Node\SequenceNode;
use RegexParser\NodeVisitor\AbstractNodeVisitor;

/**
 * @extends AbstractNodeVisitor<int>
 */
final class LiteralCountVisitor extends AbstractNodeVisitor
{
    protected function defaultReturn(): int
    {
        return 0;
    }

    public function visitRegex(RegexNode $node): int
    {
        return $node->pattern->accept($this);
    }

    public function visitLiteral(LiteralNode $node): int
    {
        return 1;
    }

    // Aggregate over sequences and groups:
    public function visitSequence(SequenceNode $node): int
    {
        $sum = 0;
        foreach ($node->children as $child) {
            $sum += $child->accept($this);
        }

        return $sum;
    }

    // For nodes you don't care about, just recurse or return 0
    public function visitQuantifier(QuantifierNode $node): int
    {
        return $node->node->accept($this);
    }
}
```

Usage:

```php
use App\Regex\LiteralCountVisitor;
use RegexParser\Regex;

$regex = Regex::create();
$ast   = $regex->parse('/ab(c|d)+/');

$visitor = new LiteralCountVisitor();
$count   = $ast->accept($visitor); // e.g. 3
```

Because `NodeVisitorInterface` is templated, static analysers can infer the return type (`int` here).

---

### Optimizing and recompiling patterns

You can round‑trip a pattern through AST → optimizer → compiler:

```php
use RegexParser\Regex;
use RegexParser\NodeVisitor\OptimizerNodeVisitor;
use RegexParser\NodeVisitor\CompilerNodeVisitor;

$regex = Regex::create();
$ast   = $regex->parse('/(a|a)/');

$optimizer = new OptimizerNodeVisitor();
$optimizedAst = $ast->accept($optimizer);

$compiler = new CompilerNodeVisitor();
$optimizedPattern = $optimizedAst->accept($compiler);

echo $optimizedPattern; // e.g. '/(a)/'
```

This makes it easy to implement automated refactorings (via Rector) or style rules for regexes.

---
## Auto-Modernize Legacy Patterns

Clean up messy or legacy regexes automatically:

```php
use RegexParser\Regex;

$regex = Regex::create();
$modern = $regex->modernize('/[0-9]+\-[a-z]+\@(?:gmail)\.com/');

echo $modern; // Outputs: /\d+-[a-z]+@gmail\.com/
```

**What it does:**
- Converts `[0-9]` → `\d`, `[a-zA-Z0-9_]` → `\w`, `[\t\n\r\f\v]` → `\s`
- Removes unnecessary escaping (e.g., `\@` → `@`)
- Modernizes backrefs (`\1` → `\g{1}`)
- Preserves exact behavior — no functional changes

Perfect for refactoring legacy codebases or cleaning up generated patterns.

---
## Syntax Highlighting

Make complex regexes readable with automatic syntax highlighting:

```php
use RegexParser\Regex;

$regex = Regex::create();

// For console output
echo $regex->highlightCli('/^[0-9]+(\w+)$/');
// Outputs: ^[0-9]+(\w+)$ with ANSI colors

// For web display
echo $regex->highlightHtml('/^[0-9]+(\w+)$/');
// Outputs: <span class="regex-anchor">^</span>[<span class="regex-type">\d</span>]+(<span class="regex-type">\w</span>+)$
```

**Color Scheme:**
- **Meta-characters** (`(`, `)`, `|`, `[`, `]`): Blue - Structure
- **Quantifiers** (`*`, `+`, `?`, `{...}`): Yellow - Repetition
- **Escapes/Types** (`\d`, `\w`, `\n`): Green - Special chars
- **Anchors/Assertions** (`^`, `$`, `\b`): Magenta - Boundaries
- **Literals**: Default - Plain text

HTML output uses `<span class="regex-*">` classes for easy styling.

---

## ReDoS Analysis

### What is ReDoS?

Regular Expression Denial of Service happens when a regex engine spends **exponential time** on certain inputs. This is particularly bad when patterns are applied to **untrusted input** (HTTP, user forms, logs, etc.).

Classic examples:

* `/(a+)+$/` on `aaaaaaaaaaaaaaaa!`
* `/^(a|a?)+$/` on long strings

### How RegexParser detects it

Instead of guessing from the pattern string, RegexParser:

1. Parses the pattern into an **AST**.
2. Walks the tree with `ReDoSProfileNodeVisitor`:

   * Tracks **unbounded quantifiers** (`*`, `+`, `{m,}`).
   * Detects **nested** unbounded quantifiers (star‑height).
   * Looks at **alternations** to see if branches share characters.
   * Follows **backreferences** and subroutines.
   * Takes into account **atomic groups**, **possessive quantifiers** and **PCRE control verbs** (which can “shield” against backtracking).
3. Aggregates the findings into a `ReDoSAnalysis`:

   * Overall `severity` (`SAFE`, `LOW`, `MEDIUM`, `HIGH`, `CRITICAL`, `UNKNOWN`).
   * A list of `vulnerabilities` with:

     * message,
     * severity,
     * position in pattern.

This is static analysis — it doesn’t execute the regex — so it’s safe to run in CI.

### Severity levels

From lowest to highest:

* `SAFE` — no dangerous constructs detected.
* `LOW` — theoretical issues, but unlikely to be exploited.
* `UNKNOWN` — analysis was inconclusive due to complex constructs.
* `MEDIUM` — potentially problematic in edge cases.
* `HIGH` — clear ReDoS risk; avoid on untrusted input.
* `CRITICAL` — classic catastrophic patterns (nested `+`/`*` etc.).

`analyzeReDoS()` returns a `ReDoSAnalysis` with the severity, score, vulnerable substring (if any), and recommendations. `isSafe()` simply calls `analyzeReDoS()` and returns `true` only for severities considered safe/low (or below the optional threshold you pass in).

You choose what to tolerate:

```php
if (!$regex->isSafe($pattern, ReDoSSeverity::HIGH)) {
    // block, warn, or open a ticket
}
```

---

## Framework & Tooling Integration

### Symfony

* Symfony bridge provides:

  * A **console command** to scan your app’s config for dangerous regexes (`regex-parser:check`).
  * File-based commands for constant `preg_*` patterns (`regex:lint`, `regex:analyze-redos`, `regex:optimize`).
  * A **cache warmer** to pre‑parse and pre‑analyze patterns on deploy.
  * Easy service wiring for `Regex` in your DI container.

Example (pseudo‑code):

```yaml
services:
  RegexParser\Regex:
    factory: ['RegexParser\Regex', 'create']
    arguments:
      - { cache: '%kernel.cache_dir%/regex', max_pattern_length: 100000, max_lookbehind_length: 255 }
```

### PHPStan

* PHPStan extension hooks into string arguments of functions like `preg_match`, `preg_replace`, Symfony validators, etc.
* It can:

  * Validate regex syntax at analysis time.
  * Optionally report ReDoS risks as PHPStan errors or warnings.

 Configuration is done via the provided `extension.neon`, with options such as:

```neon
parameters:
    regexParser:
        ignoreParseErrors: true
        reportRedos: true
        redosThreshold: 'high'
        suggestOptimizations: false
        optimizationConfig:
            digits: true
            word: true
            strictRanges: true
```

* Options mirror the PHPStan bridge:

  * `ignoreParseErrors` — skip likely partial regex strings (default: `true`).
  * `reportRedos` — emit ReDoS issues (default: `true`).
  * `redosThreshold` — minimum severity to report (`low`, `medium`, `high`, `critical`; default: `high`).
  * `suggestOptimizations` — surface shorter equivalent patterns when found (default: `false`).
  * `optimizationConfig.digits` — enable `[0-9]` → `\d` optimization suggestions (default: `true`).
  * `optimizationConfig.word` — enable `[a-zA-Z0-9_]` → `\w` optimization suggestions (default: `true`).
  * `optimizationConfig.strictRanges` — prevent merging characters from different categories (digits, letters, symbols) into single ranges for better readability (default: `true`).

### Psalm

* Psalm plugin uses the same RegexParser validation and ReDoS checks for `preg_*` calls (including `preg_replace_callback_array` keys).
* Register the plugin in `psalm.xml`:

```xml
<psalm>
  <plugins>
    <pluginClass class="RegexParser\Bridge\Psalm\Plugin">
      <ignoreParseErrors>true</ignoreParseErrors>
      <reportRedos>true</reportRedos>
      <redosThreshold>high</redosThreshold>
      <suggestOptimizations>false</suggestOptimizations>
    </pluginClass>
  </plugins>
</psalm>
```

* Options mirror the PHPStan bridge:

  * `ignoreParseErrors` — skip likely partial regex strings (default: `true`).
  * `reportRedos` — emit ReDoS issues (default: `true`).
  * `redosThreshold` — minimum severity to report (`low`, `medium`, `high`, `critical`; default: `high`).
  * `suggestOptimizations` — surface shorter equivalent patterns when found (default: `false`).

### Rector

* Rector rules can use RegexParser to:

  * Replace dangerous patterns with safer equivalents.
  * Normalize regex style across a codebase.
  * Add inline comments explaining complex patterns.

---

## Performance & Caching

RegexParser is designed for **high‑scale applications**:

* Lexer uses a single PCRE state machine with offsets, not repeated substrings.
* Parser and Lexer instances are **reused** across calls and properly reset.
* Optional cache (filesystem or PSR‑compatible) stores parsed ASTs and ReDoS analyses.

Example:

```php
use RegexParser\Regex;

$regex = Regex::create([
    'cache' => '/path/to/cache/dir',         // or a PSR cache instance
    'max_pattern_length' => 100_000,
    'max_lookbehind_length' => 255,
    'redos_ignored_patterns' => [
        '/^([0-9]{4}-[0-9]{2}-[0-9]{2})$/', // known safe patterns
    ],
]);
```

For Symfony, a cache warmer can parse and analyze all known patterns at deploy time so runtime costs are minimal.

---

## API Overview

### `Regex`

```php
final readonly class Regex
{
    public static function create(array $options = []): self;

    public function parse(string $regex): Node\RegexNode;
    public function parsePattern(string $pattern, string $delimiter = '/', string $flags = ''): Node\RegexNode;

    public function parseTolerant(string $regex): TolerantParseResult;

    public function validate(string $regex): ValidationResult;
    public function isValid(string $regex): bool;
    public function assertValid(string $regex): void;

    public function dump(string $regex): string;

    public function explain(string $regex, string $format = 'text'): string;

    public function htmlExplain(string $regex): string;

    public function highlight(string $regex, string $format = 'auto'): string;
    public function highlightCli(string $regex): string;
    public function highlightHtml(string $regex): string;

    public function optimize(string $regex): OptimizationResult;
    public function modernize(string $regex): string;

    public function generate(string $regex): string;
    public function generateTestCases(string $regex): TestCaseGenerationResult;

    public function visualize(string $regex): VisualizationResult;

    public function getLengthRange(string $regex): array;

    public function extractLiterals(string $regex): LiteralExtractionResult;

    public function analyzeReDoS(string $regex, ?ReDoS\ReDoSSeverity $threshold = null): ReDoS\ReDoSAnalysis;

    public function isSafe(string $regex, ?ReDoS\ReDoSSeverity $threshold = null): bool;

    public function getLexer(): Lexer;
    public function getParser(): Parser;
}
```

Return types like `ValidationResult`, `OptimizationResult`, `LiteralExtractionResult`, `TestCaseGenerationResult`, `VisualizationResult`, and `ReDoS\ReDoSAnalysis` are small, well‑typed value objects.

---

## Exceptions

- `Regex::create()` throws `InvalidRegexOptionException` for unknown/invalid options.
- `parse()` / `parsePattern()` can throw `LexerException`, `SyntaxErrorException` (syntax/structure), `RecursionLimitException` (too deep), and `ResourceLimitException` (pattern too long).
- `parseTolerant()` wraps those errors into `TolerantParseResult` instead of throwing.
- `validate()` converts parser/lexer errors into a `ValidationResult` (no exception on invalid input).
- `analyzeReDoS()` / `isSafe()` share the same parsing exceptions as `parse()`; `isSafe()` is a boolean wrapper around `analyzeReDoS()`.

Generic runtime errors (e.g., wrong argument types) are not part of the stable API surface.

---

## Versioning & BC Policy

RegexParser follows **Semantic Versioning**:

* **Stable for 1.x** (API surface we commit to keep compatible):
  * Public methods and signatures on `Regex`.
  * Value objects: `ValidationResult`, `TolerantParseResult`, `OptimizationResult`, `LiteralExtractionResult`, `LiteralSet`, `TestCaseGenerationResult`, `VisualizationResult`, `ReDoS\ReDoSAnalysis`.
  * Main exception interfaces/classes: `RegexParserExceptionInterface`, parser/lexer exceptions, `InvalidRegexOptionException`.
  * Supported option keys for `Regex::create()` / `RegexOptions`.

* **Best-effort, may evolve within 1.x**:
  * AST node classes and `NodeVisitorInterface` (new node types/visit methods can be added).
  * Built-in visitors and analysis heuristics.

If you maintain custom visitors, plan to adjust them when new nodes appear. Breaking changes beyond this policy land in **2.0.0**.

---

## Known Limitations

While this library supports a comprehensive set of PCRE2 features, some highly specific or experimental features may not be fully supported yet. For example:

- Certain Perl-specific verbs not yet standardized in PCRE2.
- Advanced Unicode features beyond basic properties and escapes.
- Experimental or platform-specific extensions.

If you encounter an unsupported feature, please [open an issue](https://github.com/yoeunes/regex-parser/issues) with a test case.

---

## Support the Project

If RegexParser saves you time, you can help keep it moving:

- Star the repository on GitHub
- Share it with your team or community
- Report issues or suggest features
- Contribute code or documentation
- Sponsor the work or hire me for consulting 🤝

---

## Contributing

Contributions are welcome! Areas where help is especially useful:

* New optimizations for the optimizer visitor.
* Additional ReDoS heuristics and exploit‑string generation.
* IDE integrations (PHPStorm plugin, etc.).
* More bridges (Laravel, Laminas, …).

Please run the full test suite before submitting a PR.

---

## License

This library is released under the [MIT License](LICENSE).

---

#  Further Reading

- [PCRE Specification](https://www.pcre.org/current/doc/html/pcre2syntax.html)
- [ReDoS Explained](https://owasp.org/www-community/attacks/Regular_expression_Denial_of_Service_-_ReDoS)

---

<p align="center"> <b>Made with ❤️ by <a href="https://www.linkedin.com/in/younes--ennaji/">Younes ENNAJI</a> </b> </p>
