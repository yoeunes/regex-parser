# RegexParser

<p align="center">
    <a href="https://packagist.org/packages/yoeunes/regex-parser"><img src="https://poser.pugx.org/yoeunes/regex-parser/v/stable" alt="Latest Stable Version"></a>
    <a href="https://packagist.org/packages/yoeunes/regex-parser"><img src="https://poser.pugx.org/yoeunes/regex-parser/v/unstable" alt="Latest Unstable Version"></a>
    <a href="https://packagist.org/packages/yoeunes/regex-parser"><img src="https://poser.pugx.org/yoeunes/regex-parser/downloads" alt="Total Downloads"></a>
    <a href="https://packagist.org/packages/yoeunes/regex-parser"><img src="https://poser.pugx.org/yoeunes/regex-parser/license" alt="License"></a>
</p>

RegexParser is a robust, extensible PCRE regex parser for PHP. It transforms complex regex strings into a traversable **Abstract Syntax Tree (AST)**, unlocking powerful capabilities for static analysis, validation, and complex string manipulation.

Instead of treating regexes as magical, un-debuggable black boxes, this library exposes their structure, allowing you to build tools that understand them.

## 🎯 Key Features

* **Full PCRE Parsing:** Accurately parses the vast majority of PCRE syntax, including groups (capturing, non-capturing, named), lookarounds, subroutines, conditionals, quantifiers (greedy, lazy, possessive), Unicode properties, and more.
* **Advanced Validation:** Goes beyond simple syntax checks. It semantically validates your patterns to catch costly errors *before* they run:
    * Detects **Catastrophic Backtracking** (ReDoS) vulnerabilities (e.g., `(a+)*`).
    * Finds invalid backreferences (e.g., `\2` when only one group exists).
    * Finds invalid constructs (e.g., variable-length quantifiers in lookbehinds).
* **Extensible with Visitors:** Built on the Visitor design pattern. The AST is just data; you can write simple visitor classes to perform any analysis you need.
* **Toolkit Included:** Ships with powerful visitors out-of-the-box:
    * `CompilerNodeVisitor`: Recompiles an AST back into a valid regex string.
    * `ValidatorNodeVisitor`: Performs the semantic validation.
    * `ExplainVisitor`: Creates a human-readable explanation of what a pattern does.
    * `SampleGeneratorVisitor`: Generates a random sample string that matches the pattern.
* **Modern & Robust:** Built with modern PHP, strictly typed, and heavily tested.

## 🔧 Installation

Install the library via Composer:

```bash
composer require yoeunes/regex-parser
```

## 🚀 Basic Usage

The `Regex` class provides a simple static façade for the most common operations.

### 1\. Parsing a Regex

Parse a regex string to get the root `RegexNode` of its AST.

```php
use RegexParser\Regex;
use RegexParser\Exception\ParserException;

try {
    $ast = Regex::parse('/^Hello (?<name>\w+)!$/i');
    
    // $ast is now a RegexParser\Node\RegexNode object
    echo $ast->flags; // "i"
    
} catch (ParserException $e) {
    echo 'Error parsing regex: ' . $e->getMessage();
}
```

### 2\. Validating a Regex

Check a regex for syntax errors, semantic errors, and ReDoS vulnerabilities.

```php
use RegexParser\Regex;

$result = Regex::validate('/(a+)*b/');

if (!$result->isValid) {
    echo $result->error;
    // Output: Potential catastrophic backtracking: nested quantifiers detected.
}

$result = Regex::validate('/(?<!a*b)/');

if (!$result->isValid) {
    echo $result->error;
    // Output: Variable-length quantifiers (*) are not allowed in lookbehinds.
}
```

### 3\. Explaining a Regex

Generate a human-readable explanation of a complex pattern.

```php
use RegexParser\Regex;

$explanation = Regex::explain('/(foo|bar){1,2}?/s');
echo $explanation;
```

**Output:**

```
Regex matches (with flags: s):
  Start Quantified Group (between 1 and 2 times (as few as possible)):
    Start Capturing Group:
      EITHER:
          Sequence:
            Literal('f')
            Literal('o')
            Literal('o')
        OR:
          Sequence:
            Literal('b')
            Literal('a')
            Literal('r')
    End Group
  End Quantified Group
```

### 4\. Generating Sample Data

Generate a random string that will successfully match a pattern.

```php
use RegexParser\Regex;

$sample = Regex::generate('/[a-f0-9]{4}-\[a-f0-9]{4}/');
echo $sample;

// Possible Output: c4e1-[9b2a]
```

## 💡 Advanced Usage: The Power of the AST

The true power of this library comes from traversing the AST to build your own tools. You can create a custom `NodeVisitorInterface` to analyze, manipulate, or extract information.

For example, you can use the built-in `DumperNodeVisitor` to see the AST structure.

```php
use RegexParser\Regex;
use RegexParser\NodeVisitor\DumperNodeVisitor;

$ast = Regex::parse('/^(?<id>\d+)/');

$dumper = new DumperNodeVisitor();
echo $ast->accept($dumper);
```

**Output (The Abstract Syntax Tree):**

```
Regex(delimiter: /, flags: )
  Sequence:
    Anchor(^)
    Group(type: named name: id flags: )
      Sequence:
        Quantifier(quant: +, type: greedy)
          CharType('\d')
```

## 🆕 Advanced Features

### 🔍 Literal Extraction for Pre-Match Optimization

Extract fixed strings that **must** appear in any match for fast-path optimization:

```php
use RegexParser\Regex;

$regex = Regex::create();

// Example 1: Simple prefix extraction
$literals = $regex->extractLiterals('/user_(\d+)@example\.com/');
$prefix = $literals->getLongestPrefix(); // "user_"
$suffix = $literals->getLongestSuffix(); // "@example.com"

// Fast-path check before running expensive regex
$subject = 'admin_123@test.com';
if (!str_contains($subject, $prefix)) {
    return false; // Skip regex entirely! ⚡
}
$result = preg_match($pattern, $subject);

// Example 2: Case-insensitive expansion
$literals = $regex->extractLiterals('/hello/i');
// → prefixes: ['hello', 'Hello', 'HELLO', 'HeLLo', ...]

// Example 3: Alternation
$literals = $regex->extractLiterals('/(foo|bar)baz/');
// → prefixes: ['foo', 'bar']
// → suffixes: ['baz'] (always present)
````

**Use Cases:**

  - 🚀 **10x faster** string matching when combined with `strpos()`
  - 📊 Database query optimization (check prefix before LIKE)
  - 🔍 Log parsing and filtering
  - 🎯 URL routing and validation

**API:**

```php
$literals->prefixes;            // array<string>
$literals->suffixes;            // array<string>
$literals->complete;            // bool (all matches contain a literal)
$literals->getLongestPrefix();  // ?string
$literals->getLongestSuffix();  // ?string
$literals->isVoid();            // bool (no extractable literals)
```

-----

### 🛡️ ReDoS Vulnerability Analysis

Detect **Regular Expression Denial of Service** vulnerabilities with detailed severity scoring:

```php
use RegexParser\Regex;
use RegexParser\ReDoSSeverity;

$regex = Regex::create();
$analysis = $regex->analyzeReDoS('/(a+)+b/');

echo "Severity: {$analysis->severity->value}"; // "critical"
echo "Score: {$analysis->score}";              // 10 (0-10 scale)
echo "Safe: " . ($analysis->isSafe() ? 'Yes' : 'NO!'); // NO!

foreach ($analysis->recommendations as $recommendation) {
    echo "⚠️  $recommendation\n";
}
// Output: "Nested unbounded quantifiers detected. This allows exponential backtracking."
```

**Severity Levels:**

| Level | Description | Example | Time Complexity |
|-------|-------------|---------|-----------------|
| **SAFE** | No ReDoS risk | `/^abc$/` | O(n) |
| **LOW** | Nested bounded quantifiers | `/(a{1,5}){1,5}/` | O(n²) with low constant |
| **MEDIUM** | Single unbounded quantifier | `/a+/` | O(n²) |
| **HIGH** | Nested unbounded quantifiers | `/(a+)+/` | O(2ⁿ) |
| **CRITICAL** | Definite catastrophic backtracking | `/(a*)*b/` or `/(a\|a)*/` | O(2ⁿ) worst case |

**Mitigation Detection:**

```php
// Atomic groups prevent backtracking
$analysis = $regex->analyzeReDoS('/(?>a+)+/');
// → Severity: SAFE

// Possessive quantifiers are safe
$analysis = $regex->analyzeReDoS('/a++b/');
// → Severity: SAFE
```

**Use Cases:**

  - 🔒 Security auditing of user-submitted patterns
  - 🚨 CI/CD pipeline validation
  - 📊 Code quality metrics
  - 🎓 Teaching safe regex practices

-----

### 🏗️ Fluent RegexBuilder API

Build complex regex patterns programmatically with a **type-safe**, **readable** API:

```php
use RegexParser\Regex;
use RegexParser\Builder\CharClass;

// Example 1: Email validation
$emailPattern = Regex::builder()
    ->startOfLine()
    ->capture(function($b) {
        $b->charClass(CharClass::word()->union(CharClass::literal('.', '-')))
          ->oneOrMore();
    }, 'local')
    ->literal('@')
    ->capture(function($b) {
        $b->charClass(CharClass::word()->union(CharClass::literal('.')))
          ->oneOrMore();
    }, 'domain')
    ->endOfLine()
    ->build();
// → "/^(?<local>[\w.-]+)@(?<domain>[\w.]+)$/"

// Example 2: URL with optional port
$urlPattern = Regex::builder()
    ->literal('http')
    ->literal('s')->optional()
    ->literal('://')
    ->capture(fn($b) => $b->word()->oneOrMore(), 'domain')
    ->group(function($b) {
        $b->literal(':')->digit()->between(1, 5);
    })->optional()
    ->literal('/')
    ->any()->zeroOrMore()
    ->caseInsensitive()
    ->build();

// Example 3: Date YYYY-MM-DD
$datePattern = Regex::builder()
    ->capture(fn($b) => $b->digit()->exactly(4), 'year')
    ->literal('-')
    ->capture(fn($b) => $b->digit()->exactly(2), 'month')
    ->literal('-')
    ->capture(fn($b) => $b->digit()->exactly(2), 'day')
    ->build();
// → "/(?<year>\d{4})-(?<month>\d{2})-(?<day>\d{2})/"
```

**Available Methods:**

**Literals & Basics:**

  - `literal(string)` - Escaped literal string
  - `raw(string)` - Unescaped string (use with caution)
  - `anyChar()` / `any()` - Match any character (.)

**Character Types:**

  - `digit()` / `notDigit()` - \\d / \\D
  - `word()` / `notWord()` - \\w / \\W
  - `whitespace()` / `notWhitespace()` - \\s / \\S

**Character Classes:**

  - `charClass(CharClass)` - Custom character class
  - `CharClass::digit()` - [0-9]
  - `CharClass::range('a', 'z')` - [a-z]
  - `CharClass::literal('a', 'b')` - [ab]
  - `CharClass::posix('alpha')` - [:alpha:]
  - `->union(CharClass)` - Combine classes
  - `->negate()` - Negate class

**Anchors:**

  - `startOfLine()` - ^
  - `endOfLine()` - $
  - `wordBoundary()` - \\b

**Groups:**

  - `capture(callable, ?string)` - Capturing group
  - `group(callable, bool)` - Non-capturing or capturing
  - `namedGroup(string, callable)` - Named capture
  - `atomic(callable)` - Atomic group (?\>...)
  - `lookahead(callable)` - Positive lookahead (?=...)
  - `negativeLookahead(callable)` - (?\!...)
  - `lookbehind(callable)` - (?\<=...)
  - `negativeLookbehind(callable)` - (?\<\!...)

**Quantifiers:**

  - `optional(bool)` - ? or ?? (lazy)
  - `zeroOrMore(bool)` - \* or \*? (lazy)
  - `oneOrMore(bool)` - + or +? (lazy)
  - `exactly(int)` - {n}
  - `atLeast(int, bool)` - {n,} or {n,}? (lazy)
  - `between(int, int, bool)` - {n,m} or {n,m}? (lazy)

**Alternation:**

  - `or()` - Start new branch (a|b)

**Flags:**

  - `caseInsensitive()` - i flag
  - `multiline()` - m flag
  - `dotAll()` - s flag
  - `unicode()` - u flag
  - `withFlags(string)` - Custom flags
  - `withDelimiter(string)` - Custom delimiter

**Build:**

  - `build()` - Returns regex string
  - `compile()` - Alias for build()

-----

## Performance Benchmarks

Literal extraction provides significant performance improvements for patterns with fixed prefixes/suffixes:

| Pattern | Subject | Without Optimization | With Optimization | Speedup |
|---------|---------|---------------------|-------------------|---------|
| `/user_\d+/` | "admin\_123" | 1.2μs | 0.1μs | **12x faster** |
| `/error: .*/` | "info: msg" | 2.5μs | 0.2μs | **12.5x faster** |
| `/\d{3}-\d{2}-\d{4}/` | "abc-def-ghij" | 3.1μs | 0.15μs | **20x faster** |

*Benchmarks run on PHP 8.4 with OPcache enabled*

See `examples/benchmark_literal_extraction.php` for full benchmark code.

## 🤝 Contributing

Contributions are welcome\! Please feel free to submit a Pull Request or create an Issue for bugs, feature requests, or improvements.

### Running Tests

```bash
# Run the full test suite
./vendor/bin/phpunit
```

## 📜 License

This project is licensed under the **MIT License**. See the [LICENSE](https://www.google.com/search?q=LICENSE) file for details.
