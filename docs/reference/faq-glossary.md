# FAQ and Glossary

Short answers to common questions and quick definitions of core terms.

## Frequently Asked Questions

### General

#### Does RegexParser execute regex patterns?

No. RegexParser parses and analyzes patterns statically. Runtime validation is optional and only compiles the pattern via `preg_match()` to confirm PCRE behavior.

```php
use RegexParser\Regex;

$analysis = Regex::create()->redos('/(a+)+b/');
$regex = Regex::create(['runtime_pcre_validation' => true]);
$result = $regex->validate('/test/');
```

#### Is this PCRE2-only?

Yes. RegexParser targets PHP's `preg_*` engine, which is PCRE2. Patterns are validated against PCRE2 semantics.

#### Does this guarantee ReDoS safety?

No. We detect common structural risks and provide guidance, but input sizes and runtime limits still matter.

```php
$analysis = Regex::create()->redos('/(a+)+b/');
```

> Use `Regex::redos()` plus application-level limits to stay safe.

#### What is tolerant parsing?

Tolerant parsing returns a partial AST plus errors so tooling can continue.

```php
$result = Regex::create()->parse('/[broken/', true);
```

### Usage

#### How do I check ReDoS safety?

```php
$analysis = Regex::create()->redos('/(a+)+b/');
```

#### How do I optimize a pattern?

```php
$result = Regex::create()->optimize('/[0-9]+/');
```

#### How do I explain a pattern?

```php
$text = Regex::create()->explain('/\d{3}-\d{4}/');
```

#### How do I generate a matching sample?

```php
$sample = Regex::create()->generate('/[A-Z][a-z]{3,5}\d{2}/');
```

### Technical

#### What is the difference between parse() and validate()?

| Method | Returns | On Error |
| --- | --- | --- |
| `parse()` | `RegexNode` | Throws exceptions |
| `validate()` | `ValidationResult` | Returns `isValid = false` |

```php
try {
    $ast = Regex::create()->parse('/[broken/');
} catch (\RegexParser\Exception\ParserException $e) {
    // Handle parse errors
}

$result = Regex::create()->validate('/[broken/');
```

#### How do I control caching?

Use the `cache` option in `Regex::create()`.

```php
$regex = Regex::create(['cache' => null]);
$regex = Regex::create(['cache' => '/tmp/regex-cache']);
```

## Glossary

| Term | Meaning |
| --- | --- |
| AST | Abstract Syntax Tree. The structured representation of a regex pattern. |
| Lexer | Splits pattern text into tokens with positions. |
| Parser | Builds the AST from tokens. |
| TokenStream | Ordered token list with byte offsets. |
| Visitor | A tour guide that walks the AST and produces a result. |
| ReDoS | Regular Expression Denial of Service. Catastrophic backtracking. |
| PCRE2 | The regex engine used by PHP. |
| Lookaround | Assertions like `(?=...)` or `(?<=...)`. |
| Backtracking | The engine revisits choices when a match fails. |
| Quantifier | Repetition like `*`, `+`, `{m,n}`. |

---

Previous: `diagnostics-cheatsheet.md` | Next: `../nodes/README.md`
