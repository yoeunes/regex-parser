# 🚀 Quick Start Guide

Welcome to RegexParser! This guide will get you from installation to your first regex analysis in minutes. Whether you're new to regex or an experienced developer, these steps will show you immediate value.

👉 **New to regex?** No problem! This guide assumes no prior knowledge.

👉 **Experienced with regex?** Skip to the [Advanced Features](#advanced-features) section.

---

## 🎯 What You'll Achieve

In this guide, you'll learn how to:

1. ✅ Install RegexParser
2. ✅ Use the CLI for quick analysis
3. ✅ Parse and validate patterns in PHP
4. ✅ Explain patterns in plain English
5. ✅ Check for security vulnerabilities
6. ✅ Build custom analysis tools

---

## 📦 Installation

```bash
composer require yoeunes/regex-parser
```

That's it! No other dependencies needed.

**Want to try without installing?** Try the interactive playground at [regex101.com](https://regex101.com) (PCRE2 mode).

---

## 🔧 How RegexParser Works (Simple Explanation)

```
/^hello$/i
  |
  v
Lexer  -> TokenStream (breaks pattern into pieces)
Parser -> RegexNode (AST) (builds a tree structure)
          |
          v
       Visitors -> validation, explanation, analysis, transforms
```

**Don't worry if this seems complex!** You don't need to understand the internals to use RegexParser effectively.

🔍 **Want to learn more?** See [What is an AST?](../concepts/ast.md)

---

## 💻 CLI Quick Start (Fastest Way to See Results)

The CLI gives you immediate feedback. Try these commands:

```bash
# 1. Explain a pattern in plain English
bin/regex explain '/\d{4}-\d{2}-\d{2}/'

# 2. Visualize the pattern structure
bin/regex diagram '/\d{4}-\d{2}-\d{2}/'

# 3. Check for security issues (ReDoS)
bin/regex analyze '/(a+)+$/'

# 4. Colorize the pattern for better readability
bin/regex highlight '/\d{4}-\d{2}-\d{2}/'

# 5. Lint your entire codebase
bin/regex lint src/
```

**Example Output:**
```
$ bin/regex explain '/\d{4}-\d{2}-\d{2}/'
Match exactly 4 digits, then hyphen, then exactly 2 digits, 
then hyphen, then exactly 2 digits.
```

---

## 📚 PHP API: 5 Essential Operations

### 1️⃣ Parse a Pattern (Turn regex into structured data)

```php
use RegexParser\Regex;

$regex = Regex::create();
$ast = $regex->parse('/\d{3}-\d{4}/');

// Now you have a structured AST (Abstract Syntax Tree)
// You can analyze, transform, or validate it
```

**Use when:** You need to understand or analyze pattern structure.

🔍 **Learn more:** [What is an AST?](../concepts/ast.md)

---

### 2️⃣ Validate a Pattern (Check for errors)

```php
use RegexParser\Regex;

$regex = Regex::create();
$result = $regex->validate('/(?<year>\d{4})-(?<month>\d{2})/');

if ($result->isValid()) {
    echo "✅ Pattern is valid!\n";
    echo "Complexity Score: " . $result->getComplexityScore() . "\n";
} else {
    echo "❌ Error: " . $result->getErrorMessage() . "\n";
    echo "💡 Hint: " . $result->getHint() . "\n";
}
```

**Checks performed:**
- ✅ Syntax errors (missing brackets, invalid escapes)
- ✅ ReDoS vulnerabilities (security risks)
- ✅ Invalid backreferences
- ✅ Variable-length lookbehinds
- ✅ Invalid Unicode properties

---

### 3️⃣ Explain a Pattern (Get plain English description)

```php
use RegexParser\Regex;

$regex = Regex::create();
$explanation = $regex->explain('/(?<email>\w+@\w+\.\w+)/');

echo $explanation;
```

**Example output:**
```
A named group 'email' containing:
  - One or more word characters
  - Literal '@'
  - One or more word characters
  - Literal '.'
  - One or more word characters
```

**Use when:** Documenting patterns, code reviews, teaching regex to others.

---

### 4️⃣ Check for ReDoS Vulnerabilities (Security check)

```php
use RegexParser\Regex;
use RegexParser\ReDoS\ReDoSSeverity;

$regex = Regex::create();

// Check a potentially dangerous pattern
$analysis = $regex->redos('/(a+)+b/');
echo "ReDoS Severity: " . $analysis->severity->value;  // "critical"

// Check a safe pattern
$analysis = $regex->redos('/a+b/');
echo "ReDoS Severity: " . $analysis->severity->value;  // "safe"
```

**What is ReDoS?** Regular Expression Denial of Service - where certain inputs can make your regex take forever to process.

🔍 **Learn more:** [ReDoS Deep Dive](../concepts/redos.md)

---

### 5️⃣ Highlight Patterns (Make regex readable)

```php
use RegexParser\Regex;
use RegexParser\NodeVisitor\ConsoleHighlighterVisitor;

$regex = Regex::create();
$ast = $regex->parse('/^[0-9]+(\w+)$/');

$consoleOutput = $ast->accept(new ConsoleHighlighterVisitor());
echo $consoleOutput;
```

Perfect for documentation and teaching!

---

## 🎯 10 Practical Use Cases

### 1. Parse and Understand Complex Patterns

```php
$regex = Regex::create();
$ast = $regex->parse('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/');

// Now you can analyze the email validation pattern
```

### 2. Validate User Input Patterns

```php
$regex = Regex::create();
$userPattern = $_POST['regex_pattern'];

$result = $regex->validate($userPattern);
if (!$result->isValid()) {
    die("Invalid pattern: " . $result->getErrorMessage());
}
```

### 3. Document Your Regex Patterns

```php
function documentPattern(string $pattern, string $description): void
{
    $regex = Regex::create();
    $explanation = $regex->explain($pattern);
    
    echo "### $description\n";
    echo "Pattern: $pattern\n";
    echo "Explanation: $explanation\n";
}

documentPattern('/\d{4}-\d{2}-\d{2}/', 'Date format');
```

### 4. Find Security Issues in Your Codebase

```bash
# Scan your entire project
bin/regex lint src/ --redos-only
```

### 5. Generate Test Data

```php
$regex = Regex::create();
$sample = $regex->generate('/\d{3}-[A-Z]{2}/');
echo $sample;  // Example: "123-AB"
```

### 6. Optimize Patterns

```php
$regex = Regex::create();
$literals = $regex->literals('/prefix-\d+-suffix/');

// Extract fixed parts for optimization
$prefix = $literals->literalSet->getLongestPrefix();
$suffix = $literals->literalSet->getLongestSuffix();
```

### 7. Build Custom Analysis Tools

```php
// Create a visitor to count quantifiers
class QuantifierCounter extends \RegexParser\NodeVisitor\AbstractNodeVisitor
{
    private int $count = 0;

    public function visitQuantifier(\RegexParser\Node\QuantifierNode $node): void
    {
        $this->count++;
        $node->node->accept($this);
    }

    public function getCount(): int { return $this->count; }
}

$regex = Regex::create();
$ast = $regex->parse('/a+b*c?/');
$counter = new QuantifierCounter();
$ast->accept($counter);
echo "Quantifiers: " . $counter->getCount(); // "3"
```

🔍 **Learn more:** [Understanding Visitors](../concepts/visitors.md)

---

## 📋 Common Pattern Examples

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

### Date (YYYY-MM-DD)

```php
$pattern = '/^(?<year>\d{4})-(?<month>0[1-9]|1[0-2])-(?<day>0[1-9]|[12][0-9]|3[01])$/';
$result = $regex->validate($pattern);
```

---

## ⚠️ Error Handling

```php
use RegexParser\Regex;
use RegexParser\Exception\ParserException;

$regex = Regex::create();

try {
    $ast = $regex->parse('/invalid[/');  // Unclosed character class
} catch (ParserException $e) {
    echo "❌ Parse error: " . $e->getMessage() . "\n";
    echo "📍 Position: " . $e->getPosition() . "\n";
    echo "📄 Snippet:\n" . $e->getSnippet() . "\n";
}
```

---

## ⚡ Performance Tips

1. **Parse Once, Reuse AST**: Don't re-parse the same pattern repeatedly
2. **Validate Early**: Check patterns during development, not in production
3. **Cache Results**: Store validated patterns and analysis results
4. **Reuse Regex Instance**: Create one `Regex` instance and reuse it
5. **Avoid Complex Patterns**: Simple patterns parse faster

---

## 🎓 Advanced Features

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
$pattern = '/\((?:[^()]|(?R))*\)/';
$result = $regex->validate($pattern);
```

### Atomic Groups (Performance)

```php
$pattern = '/(?>a+)b/';
$result = $regex->validate($pattern);
```

### Possessive Quantifiers (Performance)

```php
$pattern = '/a++b/';
$result = $regex->validate($pattern);
```

---

## 🚀 Next Steps

Now that you've seen what RegexParser can do, here's where to go next:

**For Beginners:**
- 🧑‍🎓 **[Learn Regex from Scratch](../tutorial/README.md)** - Complete step-by-step tutorial
- 📝 **[Regex in PHP Guide](../guides/regex-in-php.md)** - PHP-specific regex details

**For Users:**
- 🔧 **[CLI Guide](../guides/cli.md)** - Full command reference
- 🍳 **[Cookbook](../COOKBOOK.md)** - Ready-to-use patterns and recipes
- 🔒 **[ReDoS Guide](../REDOS_GUIDE.md)** - Security best practices

**For Developers:**
- 🏗️ **[Architecture](../ARCHITECTURE.md)** - Internal design
- 🌲 **[AST Reference](../nodes/README.md)** - Node types
- 👣 **[Visitors Guide](../visitors/README.md)** - Custom analysis
- 🔧 **[Extending Guide](../EXTENDING_GUIDE.md)** - Build your own tools

**Reference:**
- 📚 **[API Reference](../reference/api.md)** - Complete documentation
- 🩺 **[Diagnostics](../reference/diagnostics.md)** - Error types
- ❓ **[FAQ & Glossary](../reference/faq-glossary.md)** - Common questions

---

## 🆘 Getting Help

- **Issues & Bug Reports**: [GitHub Issues](https://github.com/yoeunes/regex-parser/issues)
- **Real-world Examples**: Check the `tests/Integration/` directory
- **Interactive Playground**: [regex101.com](https://regex101.com) (PCRE2 mode)
- **Community**: Open an issue on [GitHub](https://github.com/yoeunes/regex-parser/issues) for help

---

## 🎉 You're Ready!

You've learned the essentials of RegexParser. Now you can:

✅ Parse and validate regex patterns
✅ Explain patterns in plain English
✅ Check for security vulnerabilities
✅ Build custom analysis tools
✅ Improve your regex skills

**What will you build with RegexParser?** 🚀

---

📖 **Previous**: [Docs Home](README.md) | 🚀 **Next**: [Regex Tutorial](../tutorial/README.md)
