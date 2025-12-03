# Quick Start Guide - RegexParser

Get started with RegexParser in 5 minutes! This guide covers the 10 most common use cases.

---

## Installation

```bash
composer require yoeunes/regex-parser
```

---

## 1. Parse a Regex Pattern

Convert a regex string into an Abstract Syntax Tree (AST).

```php
use RegexParser\Parser;

$parser = new Parser();
$ast = $parser->parse('/\d{3}-\d{4}/');

// $ast is a RegexNode containing the full AST
var_dump($ast);
```

**Use Case**: Understanding pattern structure, static analysis, debugging

---

## 2. Validate a Pattern

Check if a pattern is syntactically and semantically valid.

```php
use RegexParser\Regex;

$regex = Regex::create();
$result = $regex->validate('/(?<year>\d{4})-(?<month>\d{2})/');

if ($result->isValid) {
    echo "Pattern is valid!";
} else {
    echo "Errors: " . implode(', ', $result->errors);
}
```

**Checks**:
- ✅ Syntax errors
- ✅ ReDoS vulnerabilities
- ✅ Invalid backreferences
- ✅ Variable-length lookbehinds
- ✅ Invalid Unicode properties

---

## 3. Compile Pattern Back to String

Regenerate a PCRE pattern from an AST.

```php
use RegexParser\Parser;
use RegexParser\NodeVisitor\CompilerNodeVisitor;

$parser = new Parser();
$ast = $parser->parse('/test/i');

$compiler = new CompilerNodeVisitor();
$pattern = $ast->accept($compiler);

echo $pattern; // "/test/i"
```

**Use Case**: Pattern transformation, optimization, normalization

---

## 4. Explain Pattern in Plain English

Generate human-readable explanations.

```php
use RegexParser\Regex;

$regex = Regex::create();
$explanation = $regex->explain('/(?<email>\w+@\w+\.\w+)/');

echo $explanation;
/*
Output:
"A named group 'email' containing:
  - One or more word characters
  - Literal '@'
  - One or more word characters
  - Literal '.'
  - One or more word characters"
*/
```

**Use Case**: Documentation, teaching, code reviews

---

## 5. Detect ReDoS Vulnerabilities

Identify Regular Expression Denial of Service risks.

```php
use RegexParser\Regex;

$regex = Regex::create();

// Dangerous pattern
$result = $regex->validate('/(a+)+b/');
echo "ReDoS Risk: " . $result->redosLevel; // "CRITICAL"

// Safe pattern
$result = $regex->validate('/a+b/');
echo "ReDoS Risk: " . $result->redosLevel; // "NONE"
```

**Detected Patterns**:
- ✅ Nested unbounded quantifiers `(a+)+`
- ✅ Overlapping alternations `(a|a)*`
- ✅ Catastrophic backtracking risks

---

## 6. Generate Sample Strings

Create strings that match your pattern (for testing).

```php
use RegexParser\Regex;

$regex = Regex::create();
$samples = $regex->generateSamples('/\d{3}-[A-Z]{2}/');

print_r($samples);
/*
Output:
[
    "123-AB",
    "456-XY",
    "789-PQ"
]
*/
```

**Use Case**: Test data generation, pattern validation

---

## 7. Extract Literal Strings

Find fixed strings in patterns (for optimization).

```php
use RegexParser\Regex;

$regex = Regex::create();
$literals = $regex->extractLiterals('/prefix-\d+-suffix/');

print_r($literals);
/*
Output:
[
    "prefix-",
    "-suffix"
]
*/
```

**Use Case**: Search optimization, string matching preprocessing

---

## 8. Check Feature Support

Verify if a pattern uses specific PCRE features.

```php
use RegexParser\Parser;
use RegexParser\Node\GroupType;

$parser = new Parser();
$ast = $parser->parse('/(?=test)foo/');

// Check for lookahead
$hasLookahead = containsGroupType($ast, GroupType::T_GROUP_LOOKAHEAD_POSITIVE);
echo $hasLookahead ? "Has lookahead" : "No lookahead";

function containsGroupType($node, GroupType $type): bool
{
    if ($node instanceof \RegexParser\Node\GroupNode && $node->type === $type) {
        return true;
    }
    
    foreach (get_object_vars($node) as $prop) {
        if ($prop instanceof \RegexParser\Node\NodeInterface) {
            if (containsGroupType($prop, $type)) {
                return true;
            }
        }
    }
    
    return false;
}
```

**Use Case**: Feature detection, compatibility checking

---

## 9. Build Custom Analyzer

Create your own AST visitor for custom analysis.

```php
use RegexParser\Parser;
use RegexParser\NodeVisitor\NodeVisitorInterface;
use RegexParser\Node\*;

class QuantifierCounter implements NodeVisitorInterface
{
    private int $count = 0;

    public function getCount(): int
    {
        return $this->count;
    }

    public function visitQuantifier(Node\QuantifierNode $node): void
    {
        $this->count++;
        $node->node->accept($this);
    }

    // Implement other visit methods...
    public function visitLiteral(Node\LiteralNode $node): void {}
    public function visitSequence(Node\SequenceNode $node): void
    {
        foreach ($node->children as $child) {
            $child->accept($this);
        }
    }
    // ... (implement all required methods)
}

$parser = new Parser();
$ast = $parser->parse('/a+b*c?/');

$counter = new QuantifierCounter();
$ast->accept($counter);

echo "Quantifiers: " . $counter->getCount(); // "3"
```

**Use Case**: Custom metrics, pattern analysis, code quality tools

---

## 10. Normalize/Optimize Patterns

Transform patterns for consistency or performance.

```php
use RegexParser\Parser;
use RegexParser\NodeVisitor\CompilerNodeVisitor;

class OptimizerVisitor extends CompilerNodeVisitor
{
    // Override methods to transform AST nodes
    public function visitQuantifier(Node\QuantifierNode $node): string
    {
        // Example: Convert {1} to no quantifier
        if ($node->quantifier === '{1}') {
            return $node->node->accept($this);
        }
        
        return parent::visitQuantifier($node);
    }
}

$parser = new Parser();
$ast = $parser->parse('/a{1}b{1}/');

$optimizer = new OptimizerVisitor();
$optimized = $ast->accept($optimizer);

echo $optimized; // "/ab/" (quantifiers removed)
```

**Use Case**: Pattern optimization, standardization, refactoring

---

## Common Patterns

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
$parser = new Parser();
$ast = $parser->parse('/(?<first>\w+)\s+(?<last>\w+)/');

// AST contains named group information
// Use CompilerNodeVisitor to regenerate pattern
// Or custom visitor to extract group names
```

### Conditional Patterns

```php
$pattern = '/(a)(?(1)b|c)/'; // If group 1 matches, then 'b', else 'c'
$result = $regex->validate($pattern);
```

### Recursion

```php
$pattern = '/\((?:[^()]|(?R))*\)/'; // Match balanced parentheses
$result = $regex->validate($pattern);
```

### Atomic Groups

```php
$pattern = '/(?>a+)b/'; // Atomic group - no backtracking
$result = $regex->validate($pattern);
```

### Possessive Quantifiers

```php
$pattern = '/a++b/'; // Possessive + - no backtracking
$result = $regex->validate($pattern);
```

---

## Error Handling

```php
use RegexParser\Parser;
use RegexParser\Exception\ParserException;

$parser = new Parser();

try {
    $ast = $parser->parse('/invalid[/'); // Unclosed character class
} catch (ParserException $e) {
    echo "Parse error: " . $e->getMessage();
    echo " at position: " . $e->getPosition(); // If available
}
```

---

## Performance Tips

1. **Parse Once, Reuse AST**: Don't re-parse the same pattern
2. **Validate Early**: Check patterns before deployment
3. **Cache Compiled Patterns**: Store validated patterns
4. **Use Static Facade**: `Regex::create()` caches instances
5. **Avoid Recursive Patterns**: They can be slow to parse

---

## Next Steps

- **Read API Documentation**: `src/Regex/RegexAPI.md`
- **Extend the Library**: `docs/EXTENDING_GUIDE.md`
- **See All Features**: `PCRE_FEATURES_MATRIX.md`
- **Understand Nodes**: `src/Node/README.md`
- **Run Tests**: `./vendor/bin/phpunit`

---

## Getting Help

- **Issues**: https://github.com/yoeunes/regex-parser/issues
- **Documentation**: Repository README.md
- **Examples**: `tests/Integration/` directory

---

**Ready to parse some regex patterns?** 🚀
