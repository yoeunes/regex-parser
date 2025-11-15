# RegexParser

[](https://packagist.org/packages/younes-ennaji/regex-parser)
[](https://www.google.com/search?q=LICENSE)
[](https://github.com/yoeunes/regex-parser/actions)
[](https://codecov.io/gh/yoeunes/regex-parser)
[](https://www.google.com/search?q=phpstan.dist.neon)

**RegexParser** is a lightweight, robust, and security-focused regular expression parser (PCRE subset) written in modern PHP.

It does *not* rely on `preg_*` functions for analysis. Instead, it uses a classic compiler architecture (Lexer, Parser) to transform a regex string into an **Abstract Syntax Tree (AST)**.

This project was inspired by tools like `nikic/php-parser` and the Symfony component architecture. It is designed to be extensible, UTF-8 safe, and to prevent common security vulnerabilities.

### Key Features

  * **AST Parsing:** Transforms strings like `/^foo(bar|baz)*$/i` into an object tree (Nodes).
  * **Visitor Architecture:** Allows analyzing, compiling, or validating the AST using "Visitors" (e.g., `CompilerVisitor`, `ValidatorVisitor`).
  * **Security First:** The included `ValidatorVisitor` detects **Catastrophic Backtracking** (ReDoS) patterns (e.g., `(a+)*`) before execution.
  * **100% UTF-8 Safe:** The Lexer natively handles multibyte characters.
  * **Modern PHP 8.4+:** Uses `Enum`, `readonly properties`, and strict typing (PHPStan `level max`).
  * **Current PCRE Support (v1.0):**
      * Groups `(...)`
      * Alternations `|`
      * Quantifiers `*`, `+`, `?`, and `{n,m}`
      * Wildcard character `.`
      * Anchors `^` and `$`
      * Short Escapes `\d`, `\s`, `\w` (and their negations `\D`, `\S`, `\W`)
      * Flags (e.g., `/.../imsU`)

-----

### Installation

```bash
composer require yoeunes/regex-parser
```

-----

### Usage

#### 1\. Basic Parsing

Parse a regex string to get the AST (here, a root `RegexNode`).

```php
use RegexParser\Lexer\Lexer;
use RegexParser\Parser\Parser;

$regex = '/^foo(bar|baz)+$/i';

$lexer = new Lexer($regex);
$parser = new Parser($lexer);

$ast = $parser->parse($regex);

// $ast is a RegexNode containing:
// $ast->pattern (the AST's NodeInterface)
// $ast->flags   (the string "i")
```

#### 2\. Validation (Security)

Use the `ValidatorVisitor` to check the semantic validity *and* security (ReDoS) of your regex.

```php
use RegexParser\Visitor\ValidatorVisitor;
use RegexParser\Exception\ParserException;

$validator = new ValidatorVisitor();

try {
    // This pattern is valid
    $astValid = (new Parser(new Lexer('/(a*b*)+c/')))->parse('/(a*b*)+c/');
    $astValid->accept($validator); // Throws nothing

    // This pattern is dangerous
    $astInvalid = (new Parser(new Lexer('/(a+)*b/')))->parse('/(a+)*b/');
    $astInvalid->accept($validator); // Will throw an exception

} catch (ParserException $e) {
    echo $e->getMessage();
    // Output: Potential catastrophic backtracking: nested quantifiers detected.
}
```

#### 3\. Recompilation

Use the `CompilerVisitor` to regenerate the regex string from the AST.

```php
use RegexParser\Visitor\CompilerVisitor;

$regex = '/^.\d(foo|bar)*$/ims';
$ast = (new Parser(new Lexer($regex)))->parse($regex);

$compiler = new CompilerVisitor();
$recompiled = $ast->accept($compiler);

// $recompiled is now "/^.\d(foo|bar)*$/ims"
```

-----

### Architecture Overview (AST)

The AST is composed of nodes implementing `NodeInterface`. The root `RegexNode` contains the pattern and the flags.

The pattern itself is a tree of nodes:

  * **`SequenceNode`**: A sequence of nodes (e.g., `abc`).
  * **`AlternationNode`**: A choice between multiple nodes (e.g., `a|b`).
  * **`GroupNode`**: A capturing group `(...)`.
  * **`QuantifierNode`**: Applies a quantifier to another node (e.g., `a*`).
  * **`LiteralNode`**: A literal character (e.g., `a`, or `*` if escaped `\*`).
  * **`CharTypeNode`**: A character type (e.g., `\d`, `\W`).
  * **`DotNode`**: The wildcard `.`.
  * **`AnchorNode`**: An anchor `^` or `$`.

### Extending the Parser (Advanced Guide)

The library's power lies in the Visitor pattern. You can easily create your own analyzer.

For example, let's create a visitor that calculates an approximate "complexity score" for a regex.

**1. Create your Visitor:**

```php
use RegexParser\Visitor\VisitorInterface;
use RegexParser\Ast\NodeInterface;
// ... (import all node types)

/**
 * @implements VisitorInterface<int>
 */
class ComplexityVisitor implements VisitorInterface
{
    // Simple nodes are worth 1
    public function visitLiteral(LiteralNode $node): int { return 1; }
    public function visitCharType(CharTypeNode $node): int { return 1; }
    public function visitDot(DotNode $node): int { return 1; }
    public function visitAnchor(AnchorNode $node): int { return 1; }

    // Structural nodes add up
    public function visitRegex(RegexNode $node): int
    {
        return $node->pattern->accept($this);
    }
    
    public function visitSequence(SequenceNode $node): int
    {
        return array_sum(array_map(fn(NodeInterface $n) => $n->accept($this), $node->children));
    }
    
    public function visitGroup(GroupNode $node): int
    {
        return 1 + $node->child->accept($this); // The group adds 1 point
    }

    // Complex nodes add more complexity
    public function visitAlternation(AlternationNode $node): int
    {
        // Complexity is the SUM of the alternatives
        return 2 + array_sum(array_map(fn(NodeInterface $n) => $n->accept($this), $node->alternatives));
    }

    public function visitQuantifier(QuantifierNode $node): int
    {
        // A quantifier MULTIPLIES the complexity of its child
        return 5 * (1 + $node->node->accept($this));
    }
}
```

**2. Use it:**

```php
$ast = (new Parser(new Lexer('/(a|b\d)+/')))->parse('/(a|b\d)+/');
$complexity = $ast->accept(new ComplexityVisitor());

// $complexity will be (approximately) 47
// (a|b\d) => 2 (alt) + 1 (a) + (1 (b) + 1 (\d)) = 5
// (...)   => 1 (group) + 5 = 6
// (...)+  => 5 * (1 + 6) = 35
// (The actual calculation may vary based on the exact AST structure)
```

-----

### Roadmap (v1.1 and Beyond)

This library is functional but incomplete. The next major steps are:

  * [ ] **Full Character Class Parsing** (e.g., `[a-z0-9_]`, `[^abc]`, `[\d\s]`).
  * [ ] **Lazy and Possessive Quantifiers** (e.g., `*?`, `*+`).
  * [ ] **Lookarounds and Special Groups** (e.g., `(?=...)`, `(?!...)`, `(?:...)`, `(?<name>...)`).
  * [ ] **Custom Delimiter Handling** (e.g., `#...#`, `~...~`).

### Contribution

Pull Requests are welcome\! Please ensure that tests (`phpunit`) and static analysis (`phpstan`) pass.

```bash
composer test
composer analyse
```

### License

This project is licensed under the MIT License. See the `LICENSE` file for details.
