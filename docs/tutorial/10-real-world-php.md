# 10. Real-World PHP Patterns

We finish with patterns you will see in PHP codebases and how RegexParser can help you validate and explain them.

## Email (Basic)

```php
use RegexParser\Regex;

$regex = Regex::create();
$regex->validate('/^[^\s@]+@[^\s@]+\.[^\s@]+$/');
```

## ISO Date

```php
$regex->validate('/^\d{4}-\d{2}-\d{2}$/');
```

## Simple Slug

```php
$regex->validate('/^[a-z0-9-]+$/');
```

## Use Explain to Document

```php
$explain = $regex->explain('/^\d{4}-\d{2}-\d{2}$/');
```

---

Previous: `09-testing-debugging.md` | Next: `../guides/regex-in-php.md`
