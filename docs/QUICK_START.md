# Quick Start

This is the fastest way to become productive with RegexParser. We focus on the core flows you will use most.

> We always start with `Regex::create()` so options are validated and consistent.

## Install

```bash
composer require yoeunes/regex-parser
```

## CLI First (Fast Feedback)

```bash
bin/regex explain '/^user-\d+$/'
bin/regex diagram '/^user-\d+$/'
bin/regex analyze '/(a+)+$/'
```

## Core API (Five Essentials)

### 1. Parse to AST

```php
use RegexParser\Regex;

$regex = Regex::create();
$ast = $regex->parse('/\d{3}-\d{4}/');
```

### 2. Validate

```php
$result = $regex->validate('/(?<year>\d{4})-(?<month>\d{2})/');
```

### 3. Explain

```php
$text = $regex->explain('/\d{3}-\d{4}/');
```

### 4. ReDoS Check

```php
$analysis = $regex->redos('/(a+)+$/');
```

### 5. Highlight

```php
$console = $regex->highlight('/\d+/', 'console');
```

## Configuration Options (Common)

| Option | What It Does |
| --- | --- |
| `cache` | Cache parsed ASTs |
| `runtime_pcre_validation` | Validate against PCRE runtime |
| `max_pattern_length` | Guard against huge inputs |

```php
$regex = Regex::create([
    'cache' => '/var/cache/regex-parser',
    'runtime_pcre_validation' => true,
]);
```

---

Previous: `README.md` | Next: `COOKBOOK.md`
