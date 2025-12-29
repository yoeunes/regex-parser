# Regex Cookbook

These recipes are safe, practical patterns with RegexParser-first examples. We include a short explanation and a quick validation call so you can drop them into tooling or code reviews.

> Always validate and run `redos()` before accepting user-defined patterns.

## Table of Contents

- [Email (Basic)](#email-basic)
- [ISO Date](#iso-date)
- [UUID v4](#uuid-v4)
- [IPv4 Address](#ipv4-address)
- [Slug](#slug)
- [Hex Color](#hex-color)
- [Simple Price](#simple-price)
- [Whitespace Cleanup](#whitespace-cleanup)

## Email (Basic)

Pattern:
```
/^[^\s@]+@[^\s@]+\.[^\s@]+$/
```

```php
use RegexParser\Regex;

Regex::create()->validate('/^[^\s@]+@[^\s@]+\.[^\s@]+$/');
```

## ISO Date

Pattern:
```
/^\d{4}-\d{2}-\d{2}$/
```

```php
Regex::create()->validate('/^\d{4}-\d{2}-\d{2}$/');
```

## UUID v4

Pattern:
```
/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i
```

```php
Regex::create()->validate('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i');
```

## IPv4 Address

Pattern:
```
/^(?:25[0-5]|2[0-4]\d|[01]?\d\d?)(?:\.(?:25[0-5]|2[0-4]\d|[01]?\d\d?)){3}$/
```

```php
Regex::create()->validate('/^(?:25[0-5]|2[0-4]\\d|[01]?\\d\\d?)(?:\\.(?:25[0-5]|2[0-4]\\d|[01]?\\d\\d?)){3}$/');
```

## Slug

Pattern:
```
/^[a-z0-9-]+$/
```

```php
Regex::create()->validate('/^[a-z0-9-]+$/');
```

## Hex Color

Pattern:
```
/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i
```

```php
Regex::create()->validate('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i');
```

## Simple Price

Pattern:
```
/^\$\d+(?:\.\d{2})?$/
```

```php
Regex::create()->validate('/^\\$\\d+(?:\\.\\d{2})?$/');
```

## Whitespace Cleanup

Pattern:
```
/\s+/
```

```php
Regex::create()->validate('/\\s+/');
```

> If you use this in a replacement, consider trimming input first.

---

Previous: `QUICK_START.md` | Next: `REDOS_GUIDE.md`
