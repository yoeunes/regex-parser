# RegexParser

<p align="center">
    <a href="https://packagist.org/packages/yoeunes/regex-parser"><img src="https://poser.pugx.org/yoeunes/regex-parser/v/stable" alt="Latest Stable Version"></a>
    <a href="https://packagist.org/packages/yoeunes/regex-parser"><img src="https://poser.pugx.org/yoeunes/regex-parser/v/unstable" alt="Latest Unstable Version"></a>
    <a href="https://packagist.org/packages/yoeunes/regex-parser"><img src="https://poser.pugx.org/yoeunes/regex-parser/downloads" alt="Total Downloads"></a>
    <a href="https://packagist.org/packages/yoeunes/regex-parser"><img src="https://poser.pugx.org/yoeunes/regex-parser/license" alt="License"></a>
</p>

Turn any PCRE pattern into an easy-to-work-with Abstract Syntax Tree (AST) so you can **understand, validate, and safely optimize** regexes in PHP.

---

## ⚠️ Experimental Status

This library is alpha. It parses and analyzes real-world patterns but is not fully validated against the entire PCRE spec.

**Current status:** core parsing validated • ReDoS detection fixed • behavioral compliance tests green • 140 tests / 284 assertions • Symfony + Rector + PHPStan integrations available.

---

## 🎯 Why Use This?

- Parse PCRE patterns into a traversable AST.
- Get plain-English explanations for complex regexes.
- Validate semantics (lookbehinds, backreferences, nested quantifiers).
- Detect and score ReDoS risks before deployment.
- Generate sample strings and optimized patterns.
- Works with PHP 8.4+, integrates with popular tools.

---

## 📦 Install (1 minute)

```bash
composer require yoeunes/regex-parser
```

Needs: PHP 8.4+, ext-mbstring.

---

## 🚀 60-Second Quick Start

```php
<?php

use RegexParser\Regex;

$regex = Regex::create();
$pattern = '/(?<email>[\\w.-]+@[\\w.-]+\\.\\w+)/i';

// 1) Explain it (plain English)
echo $regex->explain($pattern);

// 2) Validate it (syntax + semantics + ReDoS)
$result = $regex->validate($pattern);
echo $result->isValid ? 'OK' : $result->error;

// 3) Generate a matching sample
echo $regex->generate($pattern); // e.g. test.user@example.com

// 4) Check safety score
echo $regex->analyzeReDoS($pattern)->severity->value; // safe/low/...
```

---

## 📖 Core Tasks

### Parse to AST

```php
use RegexParser\Regex;
use RegexParser\Exception\ParserException;

try {
    $ast = Regex::create()->parse('/^Hello (?<name>\w+)!$/i');
    echo $ast->flags; // i
} catch (ParserException $e) {
    echo $e->getMessage();
}
```

### Validate

```php
use RegexParser\Regex;

$regex = Regex::create();

$result = $regex->validate('/(a+)*b/');
echo $result->isValid ? 'OK' : $result->error; // Potential catastrophic backtracking: nested quantifiers detected.

$result = $regex->validate('/(?<!a*b)/');
echo $result->isValid ? 'OK' : $result->error; // Variable-length quantifiers (*) are not allowed in lookbehinds.
```

### Explain

```php
use RegexParser\Regex;

echo Regex::create()->explain('/(foo|bar){1,2}?/s');
```

Output:
```
Regex matches (with flags: s):
  Start Quantified Group (between 1 and 2 times (as few as possible)):
    Start Capturing Group:
      EITHER:
          Literal: 'foo'
        OR:
          Literal: 'bar'
    End Group
  End Quantified Group
```

### Generate Sample Data

```php
use RegexParser\Regex;

echo Regex::create()->generate('/[a-f0-9]{4}-[a-f0-9]{4}/'); // e.g. c4e1-9b2a
```

### Optimize Patterns

```php
use RegexParser\Regex;

echo Regex::create()->optimize('/(?:a|b|c)/'); // /[abc]/
```

---

## 💡 Advanced Usage

### Work with the AST

Create a custom `NodeVisitorInterface` to analyze or transform patterns.

```php
use RegexParser\Regex;
use RegexParser\NodeVisitor\DumperNodeVisitor;

$ast = Regex::create()->parse('/^(?<id>\d+)/');
$dumper = new DumperNodeVisitor();
echo $ast->accept($dumper);
```

### Literal Extraction (fast pre-checks)

```php
use RegexParser\Regex;

$regex = Regex::create();
$literals = $regex->extractLiterals('/user_(\d+)@example\.com/');
$prefix = $literals->getLongestPrefix(); // user_
$subject = 'admin_123@test.com';

if (!str_contains($subject, $prefix)) {
    return false; // Skip regex entirely
}
```

### ReDoS Analysis

```php
use RegexParser\Regex;

$analysis = Regex::create()->analyzeReDoS('/(a+)+b/');
echo $analysis->severity->value; // critical/high/...
echo $analysis->score; // 0-10
```

Severity levels: SAFE, LOW, MEDIUM, UNKNOWN, HIGH, CRITICAL (2^n worst cases; UNKNOWN means analysis could not complete safely).

Limitations: heuristic/static only; quantified alternations with complex character classes may still warn conservatively, and deeply recursive backreference/subroutine patterns can evade detection. Treat `UNKNOWN` as a signal to fail closed.

---

## ❓ Why?

- Security: parse-first flow catches dangerous backtracking paths before runtime.
- Static analysis: AST visitors let you lint, rewrite, and document patterns with real structure instead of brittle string checks.
- ReDoS prevention: complexity scoring and path analysis detect catastrophic cases earlier than `preg_match` failures.

## ✅ Cross-Validation PCRE

1. Parse with `Regex::create()->parse($pattern)` and compile back using the `CompilerNodeVisitor`.
2. Run `preg_match($compiled, $subject)` and compare against the AST-driven evaluator or visitors to ensure flags, delimiters, and groups match.
3. Keep failing cases as fixtures to guard against drift between the parser and PHP's PCRE engine.

## 🧪 Fuzzing

- Fuzz the parser with random/edge-case inputs to ensure it never crashes or hangs on malformed patterns.
- Combine short seeds (lookbehinds, nested quantifiers, named groups) with mutation to surface parser and lexer edge cases.
- Keep regressions as deterministic tests so production builds stay resilient.

## 🗄️ Caching

Parsing is CPU-heavy; cache ASTs to PHP files for Opcache to warm:

```php
use RegexParser\Regex;

$regex = Regex::create(['cache' => __DIR__ . '/var/cache/regex']);
$ast = $regex->parse('/[A-Z][a-z]+/');
```

Or plug your app cache (PSR-6/16) for shared keys:

```php
use RegexParser\Regex;
use RegexParser\Cache\PsrCacheAdapter;
use RegexParser\Cache\PsrSimpleCacheAdapter;

// PSR-6 (CacheItemPoolInterface)
$cache = new PsrCacheAdapter($yourPool, prefix: 'route_login_');
$regex = Regex::create(['cache' => $cache]);

// PSR-16 (SimpleCache)
$cache = new PsrSimpleCacheAdapter($yourSimpleCache, prefix: 'constraint_user_email_');
$regex = Regex::create(['cache' => $cache]);
```

Pass a writable directory string to `Regex::create(['cache' => '/path'])` or a custom `CacheInterface` implementation. Use `null` (default) to disable.

---

## 🔧 Framework Integration (quick setup)

### Symfony Validator

```bash
composer require yoeunes/regex-parser
```

```php
// src/Validator/Constraints/ValidRegex.php
namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ValidRegex extends Constraint
{
    public string $message = 'The regex pattern "{{ pattern }}" is invalid: {{ error }}';
}
```

```php
// src/Validator/Constraints/ValidRegexValidator.php
namespace App\Validator\Constraints;

use RegexParser\Regex;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ValidRegexValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidRegex) {
            throw new UnexpectedTypeException($constraint, ValidRegex::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        $regex = Regex::create();
        $result = $regex->validate($value);

        if (!$result->isValid) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ pattern }}', $value)
                ->setParameter('{{ error }}', $result->error)
                ->addViolation();
        }
    }
}
```

```php
// In a form
use App\Validator\Constraints\ValidRegex;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class RegexPatternType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('pattern', TextType::class, [
            'label' => 'Regex Pattern',
            'constraints' => [
                new ValidRegex(),
            ],
        ]);
    }
}
```

### Rector Rule

```bash
composer require --dev rector/rector
```

```php
<?php

use Rector\Config\RectorConfig;
use RegexParser\Rector\RegexOptimizationRector;

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/src'])
    ->withRules([RegexOptimizationRector::class]);
```

```bash
vendor/bin/rector process --dry-run
```

### PHPStan Extension

```bash
composer require --dev phpstan/phpstan
```

```neon
includes:
    - vendor/yoeunes/regex-parser/extension.neon

parameters:
    level: max
    paths:
        - src
```

```bash
vendor/bin/phpstan analyze
```

---

## 🧪 Testing & Validation

```bash
# Full test suite
./vendor/bin/phpunit

# Targeted suites
./vendor/bin/phpunit tests/Unit
./vendor/bin/phpunit tests/Integration
./vendor/bin/phpunit tests/Integration/BehavioralComplianceTest.php
```

Run the validation script:
```bash
php validate_library.php
```

Expected output:
```
Test 1: Sample Generation         4/4  PASSED ✓
Test 2: ReDoS Detection           4/4  PASSED ✓
Test 3: PCRE Feature Coverage    12/12 PASSED ✓
Test 4: Round-trip Validation     4/4  PASSED ✓
Test 5: Invalid Pattern Detection 3/3  PASSED ✓

OVERALL: 27/27 tests passed (100%)

Behavioral Compliance Tests: 19/19 tests, 128 assertions - ALL PASS ✓
```

Web demo:
```bash
php server.php
# open http://localhost:5000
```

---

## 🛠️ CLI Tool

```bash
php bin/regex-parser '/your_regex_here/flags'
```

Example:
```bash
php bin/regex-parser '/(?<email>[\\w.-]+@[\\w.-]+\\.\\w+)/i'
```

---

## 🤝 Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for code of conduct, dev setup, and PR guidelines.

---

## 🐛 Troubleshooting

- Class not found: run `composer install` then `composer dump-autoload`.
- PHPStan memory issues: `php -d memory_limit=512M vendor/bin/phpstan analyze`.
- Pattern fails to parse: ensure valid PCRE syntax; read the error message location.
- ReDoS false positives/backreferences: update to the latest version.

---

## 📊 Performance Benchmarks

Literal extraction can speed up checks with prefixes/suffixes:

| Pattern               | Subject        | Without Optimization | With Optimization | Speedup          |
|-----------------------|----------------|----------------------|-------------------|------------------|
| `/user_\d+/`          | "admin_123"    | 1.2μs                | 0.1μs             | **12x faster**   |
| `/error: .*/`         | "info: msg"    | 2.5μs                | 0.2μs             | **12.5x faster** |
| `/\d{3}-\d{2}-\d{4}/` | "abc-def-ghij" | 3.1μs                | 0.15μs            | **20x faster**   |

---

## 📜 License

MIT License. See [LICENSE](LICENSE).

---

## 📚 Further Reading

- [PCRE Specification](https://www.pcre.org/current/doc/html/pcre2syntax.html)
- [ReDoS Explained](https://owasp.org/www-community/attacks/Regular_expression_Denial_of_Service_-_ReDoS)
