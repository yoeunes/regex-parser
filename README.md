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
````

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

## 🤝 Contributing

Contributions are welcome\! Please feel free to submit a Pull Request or create an Issue for bugs, feature requests, or improvements.

### Running Tests

```bash
# Run the full test suite
./vendor/bin/phpunit
```

## 📜 License

This project is licensed under the **MIT License**. See the [LICENSE](LICENSE) file for details.
