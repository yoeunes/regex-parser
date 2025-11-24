# Contributing to RegexParser

Thank you for your interest in contributing to RegexParser! This document provides guidelines and instructions for contributing to the project.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Workflow](#development-workflow)
- [Coding Standards](#coding-standards)
- [Testing Requirements](#testing-requirements)
- [Submitting Changes](#submitting-changes)
- [Project Structure](#project-structure)

## Code of Conduct

This project follows standard open-source community guidelines:

- **Be respectful** - Treat all contributors with respect
- **Be constructive** - Provide helpful, actionable feedback
- **Be collaborative** - Work together toward common goals
- **Be patient** - Remember that everyone is learning

## Getting Started

### Prerequisites

- PHP 8.4 or higher
- Composer
- Git
- ext-mbstring extension

### Setting Up Your Development Environment

1. **Fork the repository** on GitHub

2. **Clone your fork:**
   ```bash
   git clone https://github.com/YOUR-USERNAME/regex-parser.git
   cd regex-parser
   ```

3. **Add the upstream repository:**
   ```bash
   git remote add upstream https://github.com/yoeunes/regex-parser.git
   ```

4. **Install dependencies:**
   ```bash
   composer install
   ```

5. **Install development tools:**
   ```bash
   # PHPStan
   cd tools/phpstan && composer install && cd ../..
   
   # Rector
   cd tools/rector && composer install && cd ../..
   
   # PHP-CS-Fixer
   cd tools/php-cs-fixer && composer install && cd ../..
   
   # PHPLint
   cd tools/phplint && composer install && cd ../..
   
   # PHPBench
   cd tools/phpbench && composer install && cd ../..
   ```

6. **Run tests to verify setup:**
   ```bash
   vendor/bin/phpunit
   ```

## Development Workflow

### Creating a Feature Branch

```bash
# Update your local main branch
git checkout main
git pull upstream main

# Create a feature branch
git checkout -b feature/your-feature-name
```

### Making Changes

1. **Write code** following the coding standards (see below)
2. **Write tests** for your changes
3. **Run tests** to ensure nothing breaks
4. **Update documentation** if needed

### Running Quality Checks

Before submitting your changes, run these quality checks:

```bash
# Run all tests
vendor/bin/phpunit

# Run PHPStan (static analysis)
cd tools/phpstan
php vendor/bin/phpstan analyze --memory-limit=512M
cd ../..

# Run Rector (code quality)
cd tools/rector
php vendor/bin/rector process --dry-run
cd ../..

# Run PHP-CS-Fixer (code style)
cd tools/php-cs-fixer
php vendor/bin/php-cs-fixer fix --dry-run --diff
cd ../..

# Run PHPLint (syntax check)
cd tools/phplint
php vendor/bin/phplint
cd ../..
```

### Auto-fixing Code Style

```bash
cd tools/php-cs-fixer
php vendor/bin/php-cs-fixer fix
cd ../..
```

## Coding Standards

### PHP Standards

This project follows strict PHP coding standards:

#### 1. Strict Types

**ALL** PHP files must declare strict types:

```php
<?php

declare(strict_types=1);

namespace RegexParser\YourNamespace;
```

#### 2. Type Declarations

- **All parameters** must have type declarations
- **All return types** must be declared
- **All class properties** must have types
- Avoid `mixed` type unless absolutely necessary

```php
// ✅ Good
public function parse(string $regex): RegexNode
{
    // ...
}

private string $pattern;

// ❌ Bad
public function parse($regex)  // Missing types
{
    // ...
}

private $pattern;  // No type
```

#### 3. Immutability

- Use `readonly` classes and properties where possible
- AST nodes should be immutable

```php
// ✅ Good
readonly class LiteralNode implements NodeInterface
{
    public function __construct(
        public string $value,
        public int $startPos,
        public int $endPos,
    ) {}
}

// ❌ Bad
class LiteralNode implements NodeInterface
{
    public string $value;  // Mutable property
}
```

#### 4. Naming Conventions

- **Classes:** `PascalCase`
- **Methods/Functions:** `camelCase`
- **Constants:** `SCREAMING_SNAKE_CASE`
- **Variables:** `camelCase`
- **Interfaces:** End with `Interface` (e.g., `NodeVisitorInterface`)

#### 5. Visitor Pattern

When adding new AST nodes:

1. Create the node class implementing `NodeInterface`
2. Add `accept(NodeVisitorInterface $visitor)` method
3. Update **ALL** existing visitors with the new visit method
4. Add tests for the new node

```php
readonly class YourNewNode implements NodeInterface
{
    public function accept(NodeVisitorInterface $visitor): mixed
    {
        return $visitor->visitYourNew($this);
    }
}

// Update ALL visitors:
interface NodeVisitorInterface
{
    public function visitYourNew(YourNewNode $node): mixed;
}
```

## Testing Requirements

### Test Coverage

All contributions must include tests:

- **New features:** Write comprehensive unit tests
- **Bug fixes:** Write a test that reproduces the bug, then fix it
- **Behavioral changes:** Add behavioral compliance tests

### Test Structure

```php
<?php

declare(strict_types=1);

namespace RegexParser\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RegexParser\YourClass;

class YourClassTest extends TestCase
{
    public function test_descriptive_name_of_what_youre_testing(): void
    {
        // Arrange
        $input = '...';
        
        // Act
        $result = YourClass::method($input);
        
        // Assert
        $this->assertSame($expected, $result);
    }
}
```

### Types of Tests

1. **Unit Tests** (`tests/Unit/`) - Test individual components in isolation
2. **Integration Tests** (`tests/Integration/`) - Test component interactions
3. **Behavioral Tests** (`tests/Integration/BehavioralComplianceTest.php`) - Test against PHP's PCRE engine

### Running Specific Tests

```bash
# Run all tests
vendor/bin/phpunit

# Run unit tests only
vendor/bin/phpunit tests/Unit

# Run a specific test file
vendor/bin/phpunit tests/Unit/Parser/ParserTest.php

# Run a specific test method
vendor/bin/phpunit --filter test_parse_simple_literal
```

### Behavioral Compliance Tests

When adding PCRE features, add behavioral tests to ensure your implementation matches PHP's PCRE engine:

```php
public static function providePatterns(): \Iterator
{
    yield 'your feature' => [
        'pattern' => '/your_pattern/',
        'testCases' => [
            'match_this' => true,
            'not_this' => false,
        ],
    ];
}

#[DataProvider('providePatterns')]
public function test_pattern_behavior_matches_pcre(string $pattern, array $testCases): void
{
    $parser = new Parser();
    $compiler = new CompilerNodeVisitor();
    
    $ast = $parser->parse($pattern);
    $compiled = $ast->accept($compiler);
    
    foreach ($testCases as $input => $shouldMatch) {
        $input = (string) $input; // Ensure string type
        $originalMatch = (bool) preg_match($pattern, $input);
        $compiledMatch = (bool) preg_match($compiled, $input);
        
        $this->assertEquals($originalMatch, $compiledMatch);
        $this->assertEquals($shouldMatch, $originalMatch);
    }
}
```

## Submitting Changes

### Commit Messages

Write clear, descriptive commit messages:

```bash
# Good commit messages:
git commit -m "Fix: Backreference compilation now escapes numeric refs"
git commit -m "Feature: Add support for branch reset groups (?|...)"
git commit -m "Test: Add behavioral compliance tests for lookarounds"
git commit -m "Docs: Update README with Symfony integration example"

# Bad commit messages:
git commit -m "fix bug"
git commit -m "updates"
git commit -m "WIP"
```

**Format:**
- Start with type: `Fix:`, `Feature:`, `Test:`, `Docs:`, `Refactor:`, `Perf:`
- Be concise but descriptive
- Use present tense ("Add feature" not "Added feature")

### Pull Request Process

1. **Update your branch:**
   ```bash
   git checkout main
   git pull upstream main
   git checkout your-feature-branch
   git rebase main
   ```

2. **Push your changes:**
   ```bash
   git push origin your-feature-branch
   ```

3. **Create a Pull Request** on GitHub

4. **PR Description Template:**
   ```markdown
   ## Description
   Brief description of what this PR does
   
   ## Type of Change
   - [ ] Bug fix
   - [ ] New feature
   - [ ] Breaking change
   - [ ] Documentation update
   
   ## Changes Made
   - Specific change 1
   - Specific change 2
   
   ## Testing
   - [ ] All existing tests pass
   - [ ] New tests added
   - [ ] Behavioral compliance tests updated (if applicable)
   
   ## Checklist
   - [ ] Code follows project style guidelines
   - [ ] Self-review completed
   - [ ] Comments added for complex code
   - [ ] Documentation updated
   - [ ] No new warnings
   ```

5. **Address Review Feedback**
   - Respond to comments
   - Make requested changes
   - Push updates to your branch

## Project Structure

```
regex-parser/
├── bin/                      # CLI tools
│   └── regex-parser         # Command-line interface
├── config/                   # Configuration files
│   └── rector/              # Rector rules
├── public/                   # Web demo
│   └── index.php            # Interactive web interface
├── src/                      # Source code
│   ├── Builder/             # Fluent regex builder
│   ├── Exception/           # Custom exceptions
│   ├── Lexer/               # Tokenization
│   ├── Node/                # AST node classes
│   ├── NodeVisitor/         # Visitor implementations
│   ├── Parser.php           # Main parser
│   └── Regex.php            # Façade class
├── tests/                    # Test suite
│   ├── Unit/                # Unit tests
│   └── Integration/         # Integration tests
├── tools/                    # Development tools (isolated)
│   ├── phpstan/             # Static analysis
│   ├── rector/              # Code quality
│   ├── php-cs-fixer/        # Code style
│   ├── phplint/             # Syntax checking
│   └── phpbench/            # Performance benchmarking
├── CONTRIBUTING.md          # This file
├── README.md                # Project documentation
├── VALIDATION_REPORT.md     # Validation findings
├── composer.json            # Dependencies
├── phpunit.dist.xml         # Test configuration
├── phpstan.dist.neon        # PHPStan configuration
└── rector.php               # Rector configuration
```

## Areas Needing Contribution

### High Priority

1. **Integration Testing**
   - End-to-end tests for PHPStan integration
   - End-to-end tests for Rector integration
   - Symfony bundle integration tests

2. **PCRE Feature Coverage**
   - Script runs `(*sr:...)`
   - Additional PCRE verbs
   - Edge cases in existing features

3. **Documentation**
   - More usage examples
   - Video tutorials
   - Blog posts / articles

### Medium Priority

1. **Performance Optimization**
   - Parser optimization
   - Visitor optimization
   - Benchmark suite expansion

2. **Developer Experience**
   - Better error messages
   - IDE autocompletion support
   - Debugging tools

### Low Priority

1. **Platform Support**
   - Docker development environment
   - GitHub Actions CI/CD
   - Automated releases

## Questions?

- **General questions:** Open a [GitHub Discussion](https://github.com/yoeunes/regex-parser/discussions)
- **Bug reports:** Open a [GitHub Issue](https://github.com/yoeunes/regex-parser/issues)
- **Security issues:** Email maintainers directly (see README)

## License

By contributing to RegexParser, you agree that your contributions will be licensed under the MIT License.

---

Thank you for contributing to RegexParser! 🎉
