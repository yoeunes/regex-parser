# RegexParser

**A full PCRE regex parser, analyzer & optimizer for PHP.**

- ✅ Parses **PCRE** patterns (subroutines, conditionals, recursion, verbs…)
- ✅ Builds a rich **AST** that you can analyze and transform
- ✅ Detects **ReDoS** (Regular Expression Denial of Service) risks
- ✅ Generates **human explanations**, **HTML docs**, and **sample strings**
- ✅ Integrates with **Symfony**, **PHPStan**, and **Rector**
- ✅ 100% test coverage (unit, integration, benchmarks)

> Think of it as `nikic/php-parser` — but for regexes.

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
- [ReDoS Analysis](#redos-analysis)
  - [What is ReDoS?](#what-is-redos)
  - [How RegexParser detects it](#how-regexparser-detects-it)
  - [Severity levels](#severity-levels)
- [Framework & Tooling Integration](#framework--tooling-integration)
  - [Symfony](#symfony)
  - [PHPStan](#phpstan)
  - [Rector](#rector)
- [Performance & Caching](#performance--caching)
- [API Overview](#api-overview)
- [Versioning & BC Policy](#versioning--bc-policy)
- [Contributing](#contributing)
- [License](#license)

---

## Installation

```bash
composer require yoeunes/regex-parser
````

Requires **PHP 8.4+**.

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
    echo "Invalid regex:\n";
    foreach ($result->errors as $error) {
        echo '- '.$error->getMessage()."\n";
    }
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

echo "Severity: ".$analysis->severity->name.PHP_EOL;

foreach ($analysis->vulnerabilities as $vuln) {
    echo sprintf(
        "- [%s] %s (at position %d)\n",
        $vuln->severity->name,
        $vuln->message,
        $vuln->position
    );
}

// Quick boolean check (for CI, input validation, etc.)
if (!$regex->isSafe($pattern, ReDoSSeverity::HIGH)) {
    throw new \RuntimeException('Regex is not safe enough for untrusted input.');
}
```

Under the hood it inspects quantifiers, nested groups, backreferences and character sets using a real AST, not just regex‑on‑regex strings.

---

## Advanced Usage

### Parsing bare patterns vs PCRE strings

Most high‑level methods (`parse`, `validate`, `analyzeReDoS`) expect a **full PCRE string**:

```php
$ast = $regex->parse('/pattern/ims');
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

* `startPos` / `endPos`: byte offsets in the original pattern
* Node‑specific properties (e.g. `QuantifierNode::$min`, `$max`, `$type`)

---

### Writing a custom AST visitor

For experts: the “right” way to analyse patterns is to implement your own visitor.

```php
namespace App\Regex;

use RegexParser\Node\LiteralNode;
use RegexParser\Node\NodeInterface;
use RegexParser\NodeVisitor\NodeVisitorInterface;

/**
 * @implements NodeVisitorInterface<int>
 */
final class LiteralCountVisitor implements NodeVisitorInterface
{
    public function visitRegexNode(\RegexParser\Node\RegexNode $node): int
    {
        return $node->pattern->accept($this);
    }

    public function visitLiteralNode(LiteralNode $node): int
    {
        return 1;
    }

    // Aggregate over sequences and groups:
    public function visitSequenceNode(\RegexParser\Node\SequenceNode $node): int
    {
        $sum = 0;
        foreach ($node->children as $child) {
            $sum += $child->accept($this);
        }

        return $sum;
    }

    // For nodes you don't care about, just recurse or return 0
    public function visitQuantifierNode(\RegexParser\Node\QuantifierNode $node): int
    {
        return $node->node->accept($this);
    }

    // ... implement the remaining methods (or extend an AbstractVisitor)
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

  * A **console command** to scan your app’s config for dangerous regexes.
  * A **cache warmer** to pre‑parse and pre‑analyze patterns on deploy.
  * Easy service wiring for `Regex` in your DI container.

Example (pseudo‑code):

```yaml
services:
  RegexParser\Regex:
    factory: ['RegexParser\Regex', 'create']
    arguments:
      - { cache: '%kernel.cache_dir%/regex', max_pattern_length: 100000 }
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
```

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

    public function parseTolerant(string $regex): TolerantParseResult;

    public function validate(string $regex): ValidationResult;

    public function dump(string $regex): string;

    public function explain(string $regex): string;

    public function htmlExplain(string $regex): string;

    public function extractLiterals(string $regex): LiteralSet;

    public function analyzeReDoS(string $regex): ReDoS\ReDoSAnalysis;

    public function isSafe(string $regex, ReDoS\ReDoSSeverity $threshold): bool;

    public function getLexer(): Lexer;
    public function getParser(): Parser;
}
```

Return types like `ValidationResult`, `LiteralSet`, `ReDoSAnalysis` are small, well‑typed value objects.

---

## Versioning & BC Policy

RegexParser follows **Semantic Versioning**:

* **1.0.0** — Initial stable release.
* **1.x** — No breaking changes to:

  * public methods of `Regex`,
  * AST node constructors & properties,
  * `NodeVisitorInterface`,
  * ReDoS public API.

We reserve the right to:

* Add new methods (with sensible defaults).
* Add new node types in minor versions (without changing existing ones).
* Improve analysis heuristics and error messages.

Breaking changes will be released as **2.0**.

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

<p align="center">
   <a href="https://github.com/php-flasher/php-flasher/stargazers">
      ⭐ Star if you found this useful ⭐
   </a>
</p>
