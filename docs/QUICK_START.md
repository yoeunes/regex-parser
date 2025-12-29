# Quick Start Guide - RegexParser

**Get started with RegexParser in 5 minutes!** Whether you're new to regex or an experienced developer, this guide will help you get productive quickly.

---

## What is RegexParser?

RegexParser is a tool that helps you **understand** and **validate** regular expressions. It:

- 🔍 **Parses** regex patterns into a readable structure
- 📖 **Explains** patterns in plain English
- ⚠️ **Finds** errors and security issues
- 🎨 **Visualizes** complex patterns

### Why Use It?

```
Without RegexParser:
  - Stare at /^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/i
  - Wonder what it does
  - Hope it doesn't have bugs

With RegexParser:
  - See: "Start of string, one or more letters/digits/./_/%/+/-, @, domain, dot, 2+ letters, end"
  - Validate it automatically
  - Catch ReDoS vulnerabilities before production
```

---

## Installation

```bash
composer require yoeunes/regex-parser
```

That's it! No other dependencies.

---

## Try the CLI First (Fastest Way to See Value)

The CLI is the fastest way to understand what RegexParser can do:

```bash
# 1. Explain a pattern in plain English
bin/regex explain '/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/i'

# 2. Visualize the structure
bin/regex diagram '/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/i'

# 3. Check for security issues
bin/regex analyze '/(a+)+$/'

# 4. Colorize the pattern
bin/regex highlight '/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/i'

# 5. Lint your entire codebase
bin/regex lint src/
```

---

## 10 Common Use Cases

### 1. Parse a Regex Pattern

Convert a regex string into an Abstract Syntax Tree (AST).

```php
use RegexParser\Regex;

$regex = Regex::create();
$ast = $regex->parse('/\d{3}-\d{4}/');

// $ast is a RegexNode containing the full AST structure
echo $ast->pattern;  // SequenceNode with pattern parts
```

**Use When:** You need to understand or analyze pattern structure.

---

### 2. Validate a Pattern

Check if a pattern is syntactically and semantically valid.

```php
use RegexParser\Regex;

$regex = Regex::create();
$result = $regex->validate('/(?<year>\d{4})-(?<month>\d{2})/');

if ($result->isValid()) {
    echo "Pattern is valid!\n";
    echo "Complexity Score: " . $result->getComplexityScore() . "\n";
} else {
    echo "Error: " . $result->getErrorMessage() . "\n";
    echo "Hint: " . $result->getHint() . "\n";
}
```

**Checks Performed:**
- ✅ Syntax errors
- ✅ ReDoS vulnerabilities
- ✅ Invalid backreferences
- ✅ Variable-length lookbehinds
- ✅ Invalid Unicode properties

---

### 3. Explain Pattern in Plain English

Generate human-readable explanations.

```php
use RegexParser\Regex;

$regex = Regex::create();

$explanation = $regex->explain('/(?<email>\w+@\w+\.\w+)/');

echo $explanation;
/*
Output:
A named group 'email' containing:
  - One or more word characters
  - Literal '@'
  - One or more word characters
  - Literal '.'
  - One or more word characters
*/
```

**Use When:** Documenting patterns, code reviews, teaching.

---

### 4. Compile Pattern Back to String

Regenerate a PCRE pattern from an AST.

```php
use RegexParser\Regex;
use RegexParser\NodeVisitor\CompilerNodeVisitor;

$regex = Regex::create();
$ast = $regex->parse('/test/i');

$compiler = new CompilerNodeVisitor();
$pattern = $ast->accept($compiler);

echo $pattern;  // "/test/i"
```

**Use When:** Pattern transformation, optimization, normalization.

---

### 5. Detect ReDoS Vulnerabilities

Identify Regular Expression Denial of Service risks.

```php
use RegexParser\Regex;
use RegexParser\ReDoS\ReDoSSeverity;

$regex = Regex::create();

// Dangerous pattern
$analysis = $regex->redos('/(a+)+b/');
echo "ReDoS Severity: " . $analysis->severity->value;  // "critical"

// Safe pattern
$analysis = $regex->redos('/a+b/');
echo "ReDoS Severity: " . $analysis->severity->value;  // "safe"

// Check against threshold
$analysis = $regex->redos('/(a+)+b/', ReDoSSeverity::HIGH);
if ($analysis->exceedsThreshold(ReDoSSeverity::HIGH)) {
    echo "Pattern exceeds safety threshold!\n";
}
```

**Detected Patterns:**
- ✅ Nested unbounded quantifiers `(a+)+`
- ✅ Overlapping alternations `(a|a)*`
- ✅ Catastrophic backtracking risks

---

### 6. Generate Sample Strings

Create strings that match your pattern (for testing).

```php
use RegexParser\Regex;

$regex = Regex::create();

$sample = $regex->generate('/\d{3}-[A-Z]{2}/');
echo $sample;  // Example: "123-AB"
```

**Use When:** Creating test data, validating patterns.

---

### 7. Extract Literal Strings

Find fixed strings in patterns (for optimization).

```php
use RegexParser\Regex;

$regex = Regex::create();

$literals = $regex->literals('/prefix-\d+-suffix/');

print_r($literals->literals);
/*
Output:
[
    "prefix-",
    "-suffix"
]
*/

echo $literals->literalSet->getLongestPrefix();  // "prefix-"
echo $literals->literalSet->getLongestSuffix();  // "-suffix"
```

**Use When:** Search optimization, string matching preprocessing.

---

### 8. Syntax Highlighting

Colorize patterns for display.

```php
use RegexParser\Regex;
use RegexParser\NodeVisitor\ConsoleHighlighterVisitor;
use RegexParser\NodeVisitor\HtmlHighlighterVisitor;

$regex = Regex::create();
$ast = $regex->parse('/^[0-9]+(\w+)$/');

// Console output
echo $ast->accept(new ConsoleHighlighterVisitor());

// HTML output for web
$html = $ast->accept(new HtmlHighlighterVisitor());
```

---

### 9. Build Custom Analyzer

Create your own AST visitor for custom analysis.

```php
use RegexParser\Node;
use RegexParser\NodeVisitor\AbstractNodeVisitor;
use RegexParser\Regex;

class QuantifierCounter extends AbstractNodeVisitor
{
    private int $count = 0;

    public function getCount(): int
    {
        return $this->count;
    }

    public function visitRegex(Node\RegexNode $node): void
    {
        $node->pattern->accept($this);
    }

    public function visitQuantifier(Node\QuantifierNode $node): void
    {
        $this->count++;
        $node->node->accept($this);
    }

    public function visitLiteral(Node\LiteralNode $node): void {}

    public function visitSequence(Node\SequenceNode $node): void
    {
        foreach ($node->children as $child) {
            $child->accept($this);
        }
    }
}

$regex = Regex::create();
$ast = $regex->parse('/a+b*c?/');

$counter = new QuantifierCounter();
$ast->accept($counter);

echo "Quantifiers: " . $counter->getCount();  // "3"
```

**Use When:** Custom metrics, pattern analysis, code quality tools.

---

### 10. Validate Before Production

Always validate patterns before using them in production.

```php
use RegexParser\Regex;
use RegexParser\Exception\ParserException;

$regex = Regex::create();

// Validate early
$result = $regex->validate($userPattern);

if (!$result->isValid()) {
    throw new InvalidArgumentException(
        "Invalid regex pattern: " . $result->getErrorMessage()
    );
}

// Check for ReDoS
$analysis = $regex->redos($userPattern);

if ($analysis->severity->value === 'critical') {
    throw new InvalidArgumentException(
        "Potentially dangerous regex pattern detected"
    );
}

// Now safe to use
preg_match($userPattern, $text, $matches);
```

---

## Common Patterns Reference

### Email Validation

```php
$pattern = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';
$result = $regex->validate($pattern);
```

### URL Matching

```php
$pattern = '/^https?:\/\/(www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b([-a-zA-Z0-9()@:%_\+.~#?&\/\/=]*)$/';
$result = $regex->validate($pattern);
```

### Phone Number (US)

```php
$pattern = '/^\+?1?\s*\(?([0-9]{3})\)?\s*-?\s*([0-9]{3})\s*-?\s*([0-9]{4})$/';
$result = $regex->validate($pattern);
```

### IPv4 Address

```php
$pattern = '/^((25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/';
$result = $regex->validate($pattern);
```

### Date (YYYY-MM-DD)

```php
$pattern = '/^(?<year>\d{4})-(?<month>0[1-9]|1[0-2])-(?<day>0[1-9]|[12][0-9]|3[01])$/';
$result = $regex->validate($pattern);
```

---

## Advanced Features

### Working with Named Groups

```php
$regex = Regex::create();
$ast = $regex->parse('/(?<first>\w+)\s+(?<last>\w+)/');

// AST contains named group information
// Use CompilerNodeVisitor to regenerate pattern
// Or custom visitor to extract group names
```

### Conditional Patterns

```php
$pattern = '/(a)(?(1)b|c)/';  // If group 1 matches, then 'b', else 'c'
$result = $regex->validate($pattern);
```

### Recursion

```php
$pattern = '/\((?:[^()]|(?R))*\)/';  // Match balanced parentheses
$result = $regex->validate($pattern);
```

### Atomic Groups (Performance)

```php
$pattern = '/(?>a+)b/';  // Atomic group - no backtracking
$result = $regex->validate($pattern);
```

### Possessive Quantifiers (Performance)

```php
$pattern = '/a++b/';  // Possessive + - no backtracking
$result = $regex->validate($pattern);
```

---

## Error Handling

```php
use RegexParser\Regex;
use RegexParser\Exception\ParserException;

$regex = Regex::create();

try {
    $ast = $regex->parse('/invalid[/');  // Unclosed character class
} catch (ParserException $e) {
    echo "Parse error: " . $e->getMessage() . "\n";
    echo "Position: " . $e->getPosition() . "\n";
    echo "Snippet:\n" . $e->getSnippet() . "\n";
}
```

---

## Performance Tips

1. **Parse Once, Reuse AST**: Don't re-parse the same pattern
2. **Validate Early**: Check patterns before deployment
3. **Cache Compiled Patterns**: Store validated patterns
4. **Reuse the Regex instance**: Avoid recreating the facade in hot loops
5. **Avoid Recursive Patterns**: They can be slow to parse

---

## Next Steps

- **[Learn Regex from Scratch](../tutorial/README.md)** - Complete tutorial
- **[CLI Guide](../guides/cli.md)** - Full command reference
- **[Regex in PHP](../guides/regex-in-php.md)** - PHP-specific details
- **[ReDoS Guide](../REDOS_GUIDE.md)** - Security best practices
- **[API Reference](../reference/api.md)** - Complete documentation

---

## Getting Help

- **Issues**: [GitHub Issues](https://github.com/yoeunes/regex-parser/issues)
- **Examples**: `tests/Integration/` directory
- **Discord**: [Join our community](#)

---

**Ready to parse some regex patterns?** 🚀

---

Previous: [Docs Home](README.md) | Next: [Reference](reference.md)
