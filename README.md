<p align="center">
    <img src="art/banner.svg?v=1" alt="RegexParser" width="100%">
</p>

<p align="center">
    <strong>Treat Regular Expressions as Code.</strong>
</p>

<p align="center">
    <a href="https://www.linkedin.com/in/younes--ennaji"><img src="https://img.shields.io/badge/author-@yoeunes-blue.svg" alt="Author Badge"></a>
    <a href="https://github.com/yoeunes/regex-parser/releases"><img src="https://img.shields.io/github/tag/yoeunes/regex-parser.svg" alt="GitHub Release Badge"></a>
    <a href="https://github.com/yoeunes/regex-parser/blob/main/LICENSE"><img src="https://img.shields.io/badge/license-MIT-brightgreen.svg" alt="License Badge"></a>
    <a href="https://packagist.org/packages/yoeunes/regex-parser"><img src="https://img.shields.io/packagist/dt/yoeunes/regex-parser.svg" alt="Packagist Downloads Badge"></a>
    <a href="https://github.com/yoeunes/regex-parser"><img src="https://img.shields.io/github/stars/yoeunes/regex-parser.svg" alt="GitHub Stars Badge"></a>
    <a href="https://packagist.org/packages/yoeunes/regex-parser"><img src="https://img.shields.io/packagist/php-v/yoeunes/regex-parser.svg" alt="Supported PHP Version Badge"></a>
</p>

---

# RegexParser

RegexParser is a PHP 8.2+ library that parses PCRE-style regex strings into a typed AST. We use that AST to validate, explain, transform, and audit patterns for performance and ReDoS risk.

> We are not just using regex. We are learning how a regex engine thinks, one node at a time.

## How It Works (In One Picture)

We start with a string, break it into tokens, build a tree, then walk the tree with visitors.

```
Pattern string
  "/^hello$/i"
       |
       v
+--------------+
|   Lexer      |  Break the sentence into words
|  TokenStream |
+--------------+
       |
       v
+--------------+
|   Parser     |  Build the grammar tree
|  RegexNode   |
+--------------+
       |
       v
+--------------+
|   Visitors   |  Walk the tree and produce results
+--------------+
       |
       v
Validation, explanation, ReDoS analysis, highlights, optimizations
```

> AST = the DNA of your pattern. Once you have the DNA, you can analyze and transform safely.

## Quick Start

### Install

```bash
composer require yoeunes/regex-parser
```

### Use the API

We always start by creating a configured `Regex` instance.

```php
use RegexParser\Regex;

$regex = Regex::create([
    'cache' => '/var/cache/regex-parser',
    'runtime_pcre_validation' => true,
]);

$ast = $regex->parse('/^hello world$/i');
$result = $regex->validate('/(?<=test)foo/');
$analysis = $regex->redos('/(a+)+$/');
$explanation = $regex->explain('/\d{4}-\d{2}-\d{2}/');
```

> We always use `Regex::create()` so options are validated and consistent across tools.

### Use the CLI

```bash
vendor/bin/regex analyze '/(a+)+$/'
vendor/bin/regex explain '/\d{4}-\d{2}-\d{2}/'
vendor/bin/regex diagram '/^[a-z]+@[a-z]+\.[a-z]+$/i'
```

Example output from `diagram`:

```
RegexNode
+-- SequenceNode
    |-- AnchorNode("^")
    |-- QuantifierNode("+")
    |   +-- CharClassNode("[a-z]")
    |-- LiteralNode("@")
    |-- QuantifierNode("+")
    |   +-- CharClassNode("[a-z]")
    |-- LiteralNode(".")
    |-- QuantifierNode("+")
    |   +-- CharClassNode("[a-z]")
    +-- AnchorNode("$")
```

## The Visitor Pattern (Why It Matters)

Think of the AST as a museum and the visitor as a tour guide. The guide walks every room in a fixed order and tells you what they see. Different guides give you different reports.

```
RegexNode.accept(visitor)
        |
        v
visitor.visitRegex(RegexNode)
        |
        v
child.accept(visitor)  (SequenceNode, GroupNode, ...)
```

Visitors you will meet:
- `ValidatorNodeVisitor` checks semantic correctness.
- `LinterNodeVisitor` finds quality issues.
- `ExplainNodeVisitor` writes human explanations.
- `ReDoSProfileNodeVisitor` powers `Regex::redos()`.

## ReDoS, Visualized

ReDoS is catastrophic backtracking. The engine tries many paths when the pattern is ambiguous.

```
Pattern: /(a+)+$/
Input:   aaaaaaaaaaaaa!

Engine tries:
  (a+)+ => [aaaaaaaaaaaa] then fails at !
             ^ backtrack point
  (a+)+ => [aaaaaaaaaaa][a] then fails at !
                 ^ backtrack point
  (a+)+ => [aaaaaaaaaa][aa] then fails at !
                     ... exponential paths
```

> Use `Regex::redos()` to detect this before it hits production.

## What You Can Build

RegexParser is designed for tooling and frameworks. You get a stable AST and a rich visitor ecosystem.

```php
use RegexParser\Regex;
use RegexParser\NodeVisitor\LinterNodeVisitor;

$ast = Regex::create()->parse('/foo|bar/');
$linter = new LinterNodeVisitor();
$ast->accept($linter);

foreach ($linter->getIssues() as $issue) {
    reportIssue($issue->getMessage(), $issue->getSeverity());
}
```

> We keep nodes immutable. Visitors do the work. This keeps your tooling predictable.

## Documentation Map

Start with the learning path and move into internals when you are ready.

- Learn regex and the AST: `docs/tutorial/README.md`
- Get productive fast: `docs/QUICK_START.md`
- Understand the engine: `docs/ARCHITECTURE.md`
- Explore nodes and visitors: `docs/nodes/README.md`, `docs/visitors/README.md`
- Use the API: `docs/reference/api.md`

## Contributing

We welcome contributions. See `CONTRIBUTING.md` and `docs/MAINTAINERS_GUIDE.md` for the maintainer workflow.

```bash
composer install
composer phpunit
composer phpcs
composer phpstan
```

## License

Released under the MIT License. See `LICENSE`.
