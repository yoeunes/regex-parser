# Testing and Debugging with RegexParser

RegexParser is useful even if you are not building tooling. It can explain
patterns, validate them, and generate test strings.

## Explain and highlight

```php
use RegexParser\Regex;

$regex = Regex::create();

echo $regex->explain('/^(?<user>\w+)@(?<host>\w+)$/');
```

CLI:

```bash
bin/regex explain '/^(?<user>\w+)@(?<host>\w+)$/'
bin/regex highlight '/^\d{4}-\d{2}-\d{2}$/' --format=html
```

## Validate early

```php
$result = $regex->validate('/(?<=a+)b/');
if (!$result->isValid()) {
    echo $result->getErrorMessage();
}
```

## Generate sample inputs

```php
$sample = $regex->generate('/[a-z]{3}\d{2}/');
```

## Visualize the AST

```php
use RegexParser\NodeVisitor\DumperNodeVisitor;

$ast = $regex->parse('/foo|bar/');
print_r($ast->accept(new DumperNodeVisitor()));
```

---

Previous: [Performance and ReDoS](08-performance-redos.md) | Next: [Real-World Patterns in PHP](10-real-world-php.md)
