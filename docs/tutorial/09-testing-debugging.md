# 09. Testing and Debugging

RegexParser gives you tools to test patterns without running them against real input.

## Validate and Explain

```php
use RegexParser\Regex;

$regex = Regex::create();
$result = $regex->validate('/[unclosed/');
$explain = $regex->explain('/\d{4}-\d{2}-\d{2}/');
```

## Visualize the AST

```bash
bin/regex diagram '/^foo$/'
```

## Highlight Syntax

```bash
bin/regex highlight '/\d+/'
```

## Tolerant Parsing

If you are building tools, tolerant parsing lets you continue even with errors.

```php
$result = Regex::create()->parse('/[broken/', true);
```

---

Previous: `08-performance-redos.md` | Next: `10-real-world-php.md`
