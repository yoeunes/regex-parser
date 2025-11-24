# Extending RegexParser - Complete Guide

This guide shows you how to add new PCRE features to the RegexParser library.

---

## Table of Contents

1. [Overview](#overview)
2. [Step-by-Step: Adding a New Feature](#step-by-step-adding-a-new-feature)
3. [Example: Adding Callout Support](#example-adding-callout-support)
4. [Testing Your Changes](#testing-your-changes)
5. [Updating Documentation](#updating-documentation)
6. [Best Practices](#best-practices)

---

## Overview

RegexParser uses the **Visitor Pattern** to separate AST structure from operations:

```
User Input (Pattern)
      ↓
   Parser → AST (Nodes)
      ↓
   Visitors → Operations
   - Compiler
   - Validator
   - Explainer
   - Sample Generator
```

**To add a new PCRE feature**:
1. Create a new Node class
2. Update Parser to recognize the feature
3. Update all Visitors to handle the new Node
4. Add tests
5. Update documentation

---

## Step-by-Step: Adding a New Feature

### Step 1: Create the Node Class

**Location**: `src/Node/YourFeatureNode.php`

```php
<?php

declare(strict_types=1);

namespace RegexParser\Node;

use RegexParser\NodeVisitor\NodeVisitorInterface;

/**
 * Represents [YOUR PCRE FEATURE].
 * 
 * Example: (?C) or (?C99)
 */
class CalloutNode extends AbstractNode
{
    /**
     * @param int|null $number Callout number (null for (?C))
     * @param int      $startPos 0-based start offset
     * @param int      $endPos 0-based end offset (exclusive)
     */
    public function __construct(
        public readonly ?int $number,
        int $startPos,
        int $endPos,
    ) {
        parent::__construct($startPos, $endPos);
    }

    /**
     * Accept a visitor to perform operations on this node.
     *
     * @template T
     * @param NodeVisitorInterface<T> $visitor
     * @return T
     */
    public function accept(NodeVisitorInterface $visitor)
    {
        return $visitor->visitCallout($this);
    }
}
```

**Key Points**:
- Extend `AbstractNode` (provides `startPos`, `endPos`)
- Use `readonly` properties (immutability)
- Implement `accept()` method
- Use strict types: `declare(strict_types=1)`
- Add PHPDoc comments

---

### Step 2: Update the Parser

**Location**: `src/Parser.php` (or appropriate parsing class)

```php
// In parseAtom() or appropriate parsing method:

if ($this->match('(?C')) {
    $startPos = $this->pos - 3;
    
    // Check for numbered callout (?C99)
    if ($this->match('\d+')) {
        $number = (int) $this->lastMatch;
        $this->expect(')');
        return new CalloutNode($number, $startPos, $this->pos);
    }
    
    // Simple callout (?C)
    $this->expect(')');
    return new CalloutNode(null, $startPos, $this->pos);
}
```

**Parsing Tips**:
- Track positions accurately (`startPos`, `endPos`)
- Throw `ParserException` for invalid syntax
- Handle all syntax variations
- Test edge cases

---

### Step 3: Update All Visitors

You **MUST** update **ALL** visitors. Missing even one will cause runtime errors.

#### 3.1 Update NodeVisitorInterface

**Location**: `src/NodeVisitor/NodeVisitorInterface.php`

```php
interface NodeVisitorInterface
{
    // ... existing methods ...
    
    /**
     * @return T
     */
    public function visitCallout(CalloutNode $node);
}
```

#### 3.2 Update CompilerNodeVisitor

**Location**: `src/NodeVisitor/CompilerNodeVisitor.php`

```php
public function visitCallout(CalloutNode $node): string
{
    if ($node->number !== null) {
        return "(?C{$node->number})";
    }
    
    return '(?C)';
}
```

**Purpose**: Regenerate PCRE pattern from AST

#### 3.3 Update ValidatorNodeVisitor

**Location**: `src/NodeVisitor/ValidatorNodeVisitor.php`

```php
public function visitCallout(CalloutNode $node): void
{
    // Validate callout number range if needed
    if ($node->number !== null && ($node->number < 0 || $node->number > 255)) {
        throw new ParserException("Callout number must be 0-255");
    }
    
    // No further validation needed for callouts
}
```

**Purpose**: Semantic validation

#### 3.4 Update ExplainVisitor

**Location**: `src/NodeVisitor/ExplainVisitor.php`

```php
public function visitCallout(CalloutNode $node): string
{
    if ($node->number !== null) {
        return "Callout #{$node->number} (debugging hook)";
    }
    
    return "Callout (debugging hook)";
}
```

**Purpose**: Human-readable explanation

#### 3.5 Update SampleGeneratorVisitor

**Location**: `src/NodeVisitor/SampleGeneratorVisitor.php`

```php
public function visitCallout(CalloutNode $node): string
{
    // Callouts don't match anything - return empty string
    return '';
}
```

**Purpose**: Generate sample strings

---

### Step 4: Update NodeRegistry

**Location**: `src/Node/NodeRegistry.php`

```php
public static function getAllNodes(): array
{
    return [
        // ... existing nodes ...
        
        'callout' => [
            'class' => CalloutNode::class,
            'pcre_feature' => 'Callouts',
            'description' => 'Represents callout debugging hooks',
            'examples' => ['(?C)', '(?C99)', '(?C0)'],
            'parent' => AbstractNode::class,
            'children' => [],
        ],
    ];
}
```

---

### Step 5: Add Tests

#### 5.1 Unit Test

**Location**: `tests/Unit/Node/CalloutNodeTest.php`

```php
<?php

declare(strict_types=1);

namespace RegexParser\Tests\Unit\Node;

use PHPUnit\Framework\TestCase;
use RegexParser\Node\CalloutNode;
use RegexParser\NodeVisitor\CompilerNodeVisitor;

class CalloutNodeTest extends TestCase
{
    public function testCalloutWithoutNumber(): void
    {
        $node = new CalloutNode(null, 0, 4);
        
        $this->assertNull($node->number);
        $this->assertEquals(0, $node->startPos);
        $this->assertEquals(4, $node->endPos);
    }

    public function testCalloutWithNumber(): void
    {
        $node = new CalloutNode(42, 0, 6);
        
        $this->assertEquals(42, $node->number);
    }

    public function testAcceptVisitor(): void
    {
        $node = new CalloutNode(99, 0, 6);
        $visitor = new CompilerNodeVisitor();
        
        $result = $node->accept($visitor);
        
        $this->assertEquals('(?C99)', $result);
    }
}
```

#### 5.2 Integration Test

**Location**: `tests/Integration/CalloutTest.php`

```php
<?php

declare(strict_types=1);

namespace RegexParser\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RegexParser\Parser;
use RegexParser\Node\CalloutNode;
use RegexParser\NodeVisitor\CompilerNodeVisitor;

class CalloutTest extends TestCase
{
    public function testParseSimpleCallout(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('/(?C)test/');
        
        $this->assertInstanceOf(CalloutNode::class, /* find in AST */);
    }

    public function testParseNumberedCallout(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('/(?C42)test/');
        
        // Assert CalloutNode with number=42 exists in AST
    }

    public function testRoundTripCompilation(): void
    {
        $parser = new Parser();
        $pattern = '/test(?C99)end/';
        $ast = $parser->parse($pattern);
        
        $compiler = new CompilerNodeVisitor();
        $compiled = $ast->accept($compiler);
        
        $this->assertEquals($pattern, $compiled);
    }

    /**
     * @dataProvider calloutPatterns
     */
    public function testCalloutPatterns(string $pattern): void
    {
        $parser = new Parser();
        $ast = $parser->parse($pattern);
        
        $compiler = new CompilerNodeVisitor();
        $compiled = $ast->accept($compiler);
        
        $this->assertEquals($pattern, $compiled);
    }

    public static function calloutPatterns(): array
    {
        return [
            ['/(? C)/'],
            ['/(?C0)/'],
            ['/(?C255)/'],
            ['/a(?C)b/'],
            ['/(?C1)test(?C2)/'],
        ];
    }
}
```

#### 5.3 Add to Feature Completeness Test

**Location**: `tests/Integration/PcreFeatureCompletenessTest.php`

```php
public function testCallouts(): void
{
    $patterns = [
        '/(?C)/',
        '/(?C0)/',
        '/(?C99)/',
        '/test(?C)end/',
        '/a(?C1)b(?C2)c/',
    ];

    foreach ($patterns as $pattern) {
        try {
            $ast = $this->parser->parse($pattern);
            $this->assertNotNull($ast, "Callout pattern should parse: {$pattern}");
        } catch (ParserException $e) {
            $this->fail("Callout pattern failed: {$pattern}. Error: {$e->getMessage()}");
        }
    }
}
```

---

### Step 6: Update Documentation

#### 6.1 Update PCRE Features Matrix

**Location**: `PCRE_FEATURES_MATRIX.md`

Add new section:

```markdown
## 11. Callouts ✅ FULL SUPPORT

**Syntax**: `(?C)`, `(?C99)`  
**Purpose**: Debugging hooks for regex engine  
**Node**: `CalloutNode`

### Tested Patterns (5/5 ✅)

| Pattern | Description | Status |
|---------|-------------|--------|
| `/(?C)/` | Simple callout | ✅ |
| `/(?C0)/` | Numbered callout 0 | ✅ |
| `/(?C99)/` | Numbered callout 99 | ✅ |
| `/test(?C)end/` | Callout in pattern | ✅ |
| `/(?C1)a(?C2)b/` | Multiple callouts | ✅ |

**Implementation**: Complete AST representation and visitor support.
```

#### 6.2 Update Node README

**Location**: `src/Node/README.md`

Add to Quick Reference table and create section:

```markdown
### CalloutNode
Represents regex engine callouts for debugging.

**Types**: Simple `(?C)`, Numbered `(?C99)`

\```php
// Pattern: (?C42)
new CalloutNode(number: 42, ...)
\```
```

#### 6.3 Update Nodes Audit Report

**Location**: `NODES_AUDIT_REPORT.md`

Add entry to inventory.

---

## Example: Adding Callout Support

See the sections above for a complete worked example of adding `(?C)` and `(?C99)` callout support.

**Files Modified**:
1. `src/Node/CalloutNode.php` - NEW
2. `src/Parser.php` - Add parsing logic
3. `src/NodeVisitor/NodeVisitorInterface.php` - Add method signature
4. `src/NodeVisitor/CompilerNodeVisitor.php` - Add visitCallout()
5. `src/NodeVisitor/ValidatorNodeVisitor.php` - Add visitCallout()
6. `src/NodeVisitor/ExplainVisitor.php` - Add visitCallout()
7. `src/NodeVisitor/SampleGeneratorVisitor.php` - Add visitCallout()
8. `src/Node/NodeRegistry.php` - Add metadata
9. `tests/Unit/Node/CalloutNodeTest.php` - NEW
10. `tests/Integration/CalloutTest.php` - NEW
11. `tests/Integration/PcreFeatureCompletenessTest.php` - Add test method
12. `PCRE_FEATURES_MATRIX.md` - Document support
13. `src/Node/README.md` - Add documentation
14. `NODES_AUDIT_REPORT.md` - Add to inventory

---

## Testing Your Changes

### Run Unit Tests
```bash
./vendor/bin/phpunit tests/Unit/
```

### Run Integration Tests
```bash
./vendor/bin/phpunit tests/Integration/
```

### Run Feature Completeness
```bash
./vendor/bin/phpunit tests/Integration/PcreFeatureCompletenessTest.php
```

### Run All Tests
```bash
./vendor/bin/phpunit
```

### Static Analysis
```bash
./vendor/bin/phpstan analyze
```

### Code Style
```bash
./vendor/bin/php-cs-fixer fix
```

---

## Best Practices

### 1. Immutability
✅ Use `readonly` properties  
❌ Don't use mutable state

```php
// GOOD
public readonly int $value;

// BAD
public int $value;
```

### 2. Type Safety
✅ Use strict types  
✅ Type all parameters and returns  
❌ Avoid `mixed`

```php
// GOOD
public function visitCallout(CalloutNode $node): string

// BAD
public function visitCallout($node)
```

### 3. Error Handling
✅ Throw specific exceptions  
✅ Include position information  
❌ Don't silently fail

```php
// GOOD
throw new ParserException("Invalid callout number at position {$this->pos}");

// BAD
return null; // Silent failure
```

### 4. Documentation
✅ PHPDoc for all public methods  
✅ Examples in comments  
✅ Update all docs

### 5. Testing
✅ Test happy path  
✅ Test edge cases  
✅ Test error conditions  
✅ Test round-trip compilation

---

## Common Pitfalls

### 1. Forgetting to Update a Visitor

**Error**: `Call to undefined method visitYourFeature()`

**Fix**: Update ALL visitors (Compiler, Validator, Explain, Sample Generator)

### 2. Not Updating NodeVisitorInterface

**Error**: Class doesn't implement interface method

**Fix**: Add method signature to `NodeVisitorInterface`

### 3. Parser Position Tracking

**Error**: Incorrect `startPos`/`endPos` values

**Fix**: Carefully track `$this->pos` before and after parsing

### 4. Incomplete Tests

**Error**: Feature breaks in edge cases

**Fix**: Test all syntax variations, not just happy path

---

## Getting Help

- **Review existing Nodes**: Look at similar features
- **Check tests**: See how other features are tested
- **Ask questions**: Open GitHub issue or discussion
- **Read PCRE docs**: Understand the feature specification

---

## Checklist for New Features

- [ ] Create Node class in `src/Node/`
- [ ] Add parsing logic to Parser
- [ ] Update `NodeVisitorInterface`
- [ ] Update `CompilerNodeVisitor`
- [ ] Update `ValidatorNodeVisitor`
- [ ] Update `ExplainVisitor`
- [ ] Update `SampleGeneratorVisitor`
- [ ] Update `NodeRegistry`
- [ ] Create unit tests
- [ ] Create integration tests
- [ ] Add to feature completeness test
- [ ] Update `PCRE_FEATURES_MATRIX.md`
- [ ] Update `src/Node/README.md`
- [ ] Update `NODES_AUDIT_REPORT.md`
- [ ] Run all tests
- [ ] Run static analysis
- [ ] Fix code style

---

**Ready to extend the library?** Start with a simple feature and follow this guide step-by-step!
