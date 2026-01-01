# 👣 Understanding Visitors

The **Visitor Pattern** is a powerful design pattern that allows you to add new operations to objects without changing their structure. In RegexParser, visitors process the AST to perform various analyses and transformations.

## 🎯 Simple Explanation

Imagine you have an AST (tree structure) and you want to do different things with it:

- **Explain** the pattern in plain English
- **Validate** the pattern for errors
- **Highlight** the pattern with colors
- **Analyze** for ReDoS vulnerabilities

Instead of putting all this logic in the AST nodes themselves, we use **visitors** that "walk" the tree and perform specific tasks.

## 🔍 How Visitors Work

### Basic Visitor Structure

```php
class MyCustomVisitor extends AbstractNodeVisitor
{
    public function visitRegex(RegexNode $node): void
    {
        // Process the root regex node
        $node->pattern->accept($this);
    }

    public function visitLiteral(LiteralNode $node): void
    {
        // Process literal nodes
        echo "Found literal: " . $node->value;
    }

    public function visitQuantifier(QuantifierNode $node): void
    {
        // Process quantifier nodes
        echo "Found quantifier: " . $node->quantifier;
        $node->node->accept($this); // Continue traversal
    }
}
```

### Using a Visitor

```php
$regex = Regex::create();
$ast = $regex->parse('/hello\d+/');

$visitor = new MyCustomVisitor();
$ast->accept($visitor); // Start the traversal
```

## 🧩 Built-in Visitors

RegexParser includes several useful visitors:

### 1. CompilerNodeVisitor
```php
use RegexParser\NodeVisitor\CompilerNodeVisitor;

$compiler = new CompilerNodeVisitor();
$pattern = $ast->accept($compiler); // Regenerate the pattern
```

### 2. ExplanationVisitor
```php
use RegexParser\NodeVisitor\ExplanationVisitor;

$explainer = new ExplanationVisitor();
$explanation = $ast->accept($explainer); // Get plain English explanation
```

### 3. Highlighting Visitors
```php
use RegexParser\NodeVisitor\ConsoleHighlighterVisitor;
use RegexParser\NodeVisitor\HtmlHighlighterVisitor;

$consoleHighlighter = new ConsoleHighlighterVisitor();
$htmlHighlighter = new HtmlHighlighterVisitor();

$consoleOutput = $ast->accept($consoleHighlighter);
$htmlOutput = $ast->accept($htmlHighlighter);
```

## 💡 Creating Custom Visitors

### Step 1: Extend AbstractNodeVisitor

```php
use RegexParser\NodeVisitor\AbstractNodeVisitor;
use RegexParser\Node;

class QuantifierCounter extends AbstractNodeVisitor
{
    private int $count = 0;

    public function getCount(): int
    {
        return $this->count;
    }

    public function visitQuantifier(Node\QuantifierNode $node): void
    {
        $this->count++;
        $node->node->accept($this); // Continue traversal
    }

    // Implement other visit methods as needed
    public function visitLiteral(Node\LiteralNode $node): void {}
    public function visitRegex(Node\RegexNode $node): void
    {
        $node->pattern->accept($this);
    }
}
```

### Step 2: Use Your Visitor

```php
$regex = Regex::create();
$ast = $regex->parse('/a+b*c?/');

$counter = new QuantifierCounter();
$ast->accept($counter);

echo "Quantifiers found: " . $counter->getCount(); // "3"
```

## 🎨 Real-world Examples

### Example 1: Pattern Complexity Analyzer

```php
class ComplexityAnalyzer extends AbstractNodeVisitor
{
    private int $complexityScore = 0;

    public function visitQuantifier(Node\QuantifierNode $node): void
    {
        $this->complexityScore += 2; // Each quantifier adds complexity
        $node->node->accept($this);
    }

    public function visitGroup(Node\GroupNode $node): void
    {
        $this->complexityScore += 3; // Groups are more complex
        foreach ($node->children as $child) {
            $child->accept($this);
        }
    }

    public function getComplexityScore(): int
    {
        return $this->complexityScore;
    }
}
```

### Example 2: Pattern Optimizer

```php
class PatternOptimizer extends AbstractNodeVisitor
{
    private array $optimizations = [];

    public function visitCharacterClass(Node\CharacterClassNode $node): void
    {
        // Check for common optimizations
        if ($node->getCharacters() === '0-9') {
            $this->optimizations[] = 'Replace [0-9] with \\d';
        }
    }

    public function getOptimizations(): array
    {
        return $this->optimizations;
    }
}
```

## 🔗 Related Concepts

- **[What is an AST?](ast.md)** - The structure visitors process
- **[Architecture](../ARCHITECTURE.md)** - How visitors fit into the system
- **[Visitors Reference](../visitors/README.md)** - Complete list of built-in visitors

## 📚 Further Reading

- [Visitor Pattern (Wikipedia)](https://en.wikipedia.org/wiki/Visitor_pattern) - Design pattern explanation
- [RegexParser Architecture](../ARCHITECTURE.md) - Technical implementation details
- [Extending Guide](../EXTENDING_GUIDE.md) - Building custom tools

---

📖 **Previous**: [What is an AST?](ast.md) | 🚀 **Next**: [ReDoS Deep Dive](redos.md)