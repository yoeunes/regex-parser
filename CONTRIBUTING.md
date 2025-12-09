# Contributing to RegexParser

🎉 **Thank you for your interest in contributing to RegexParser!**

This document provides comprehensive guidelines and instructions for contributing to the project. Whether you're fixing bugs, adding features, improving documentation, or helping with testing, your contributions are valuable and appreciated.

## 🚀 Quick Start

Want to contribute quickly? Here's the fast track:

```bash
# 1. Fork & clone
git clone https://github.com/YOUR-USERNAME/regex-parser.git
cd regex-parser

# 2. Set up environment
composer install

# 3. Run tests
vendor/bin/phpunit

# 4. Make your changes
# 5. Submit a PR!
```

For detailed setup instructions, see [Getting Started](#getting-started).

## 📋 Code of Conduct

This project follows the [Contributor Covenant Code of Conduct](CODE_OF_CONDUCT.md):

- **Be respectful** - Treat all contributors with respect and kindness
- **Be constructive** - Provide helpful, actionable feedback
- **Be collaborative** - Work together toward common goals
- **Be patient** - Remember that everyone is learning and mistakes happen

## Table of Contents

- [🚀 Quick Start](#quick-start)
- [📋 Code of Conduct](#code-of-conduct)
- [🏁 Getting Started](#getting-started)
- [🔄 Development Workflow](#development-workflow)
- [💻 Coding Standards](#coding-standards)
- [🧪 Testing Requirements](#testing-requirements)
- [📤 Submitting Changes](#submitting-changes)
- [🏗️ Project Structure](#project-structure)
- [🎯 Areas for Contribution](#areas-for-contribution)
- [❓ Questions & Support](#questions--support)

## Code of Conduct

This project follows standard open-source community guidelines:

- **Be respectful** - Treat all contributors with respect
- **Be constructive** - Provide helpful, actionable feedback
- **Be collaborative** - Work together toward common goals
- **Be patient** - Remember that everyone is learning

## 🏁 Getting Started

### Prerequisites

- **PHP 8.2 or higher** (required for modern features)
- **Composer** (dependency management)
- **Git** (version control)
- **ext-mbstring** (multibyte string support)

### Setting Up Your Development Environment

#### 1. Fork & Clone
```bash
# Fork the repository on GitHub, then:
git clone https://github.com/YOUR-USERNAME/regex-parser.git
cd regex-parser
git remote add upstream https://github.com/yoeunes/regex-parser.git
```

#### 2. Install Dependencies
```bash
composer install
```

#### 3. Install Development Tools
```bash
# Install all quality assurance tools automatically
composer run install-tools

# Or install individually if needed
composer run install-phpstan
composer run install-rector
composer run install-php-cs-fixer
composer run install-phplint
composer run install-phpbench
```

#### 4. Verify Setup
```bash
# Run the complete test suite
composer run test

# Try the CLI tool
php bin/regex --help

# Or run the full automated setup (install + tools + test)
composer run setup
```

#### 5. Optional: Set Up IDE
For the best development experience:
- Install PHPStan plugin for your IDE
- Configure Rector rules
- Set up PHP-CS-Fixer integration

## 🔄 Development Workflow

### Creating a Feature Branch

```bash
# Sync with upstream
git checkout main
git pull upstream main

# Create feature branch
git checkout -b feature/your-feature-name
# or for bug fixes:
git checkout -b fix/issue-description
```

### Making Changes

1. **Follow coding standards** (see below)
2. **Write tests first** (TDD approach recommended)
3. **Keep commits focused** on single changes
4. **Update documentation** as needed

### Quality Assurance Pipeline

Run all quality checks with a single command:

```bash
# Run complete QA pipeline (tests + static analysis + code quality + style + syntax)
composer run qa

# Or run individual checks:
composer run qa:test          # Run tests
composer run qa:phpstan       # Static analysis
composer run qa:rector        # Code quality
composer run qa:php-cs-fixer  # Code style
composer run qa:phplint       # Syntax check
```

### Manual Quality Checks (if needed)

```bash
# 🧪 Run tests
vendor/bin/phpunit

# 🔍 Static analysis
cd tools/phpstan && php vendor/bin/phpstan analyse && cd ../..

# 🛠️ Code quality
cd tools/rector && php vendor/bin/rector process --dry-run && cd ../..

# 💅 Code style
cd tools/php-cs-fixer && php vendor/bin/php-cs-fixer fix --dry-run --diff && cd ../..

# ✅ Syntax check
cd tools/phplint && php vendor/bin/phplint && cd ../..
```

### Auto-fixing Issues

Apply all automatic fixes with a single command:

```bash
# Fix code style and apply Rector transformations
composer run fix

# Or run individually:
composer run fix:php-cs-fixer  # Fix code style
composer run fix:rector        # Apply Rector fixes
```

### Manual Auto-fixing (if needed)

```bash
# Fix code style automatically
cd tools/php-cs-fixer && php vendor/bin/php-cs-fixer fix && cd ../..

# Apply Rector fixes
cd tools/rector && php vendor/bin/rector process && cd ../..
```

## 💻 Coding Standards

This project follows **strict PHP coding standards** to ensure code quality, maintainability, and consistency.

### PHP Standards

#### 1. 🔒 Strict Types

**ALL** PHP files must declare strict types:

```php
<?php
declare(strict_types=1);

namespace RegexParser\YourNamespace;
```

#### 2. 📝 Type Declarations

- ✅ **All parameters** must have type declarations
- ✅ **All return types** must be declared
- ✅ **All class properties** must have types
- ❌ Avoid `mixed` type unless absolutely necessary

```php
// ✅ Good
public function parse(string $regex): RegexNode
{
    // Implementation
}

private string $pattern;

// ❌ Bad - Missing types
public function parse($regex)
{
    // Implementation
}

private $pattern; // No type declaration
```

#### 3. 🔒 Immutability

- Use `readonly` classes and properties where possible
- AST nodes should be **immutable** by design

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

// ❌ Bad - Mutable properties
class LiteralNode implements NodeInterface
{
    public string $value; // Can be changed
}
```

#### 4. 🏷️ Naming Conventions

- **Classes:** `PascalCase` (e.g., `RegexParser`)
- **Methods/Functions:** `camelCase` (e.g., `parseRegex`)
- **Constants:** `SCREAMING_SNAKE_CASE` (e.g., `MAX_DEPTH`)
- **Variables:** `camelCase` (e.g., `$parsedResult`)
- **Interfaces:** End with `Interface` (e.g., `NodeVisitorInterface`)

#### 5. 🎯 Visitor Pattern Implementation

When adding new AST nodes, follow this **exact process**:

1. **Create the node class** implementing `NodeInterface`
2. **Add the `accept()` method** with proper return type
3. **Update ALL existing visitors** with the new visit method
4. **Add comprehensive tests** for the new node

```php
readonly class YourNewNode implements NodeInterface
{
    public function accept(NodeVisitorInterface $visitor): mixed
    {
        return $visitor->visitYourNew($this);
    }
}

// Update ALL visitor interfaces and implementations:
interface NodeVisitorInterface
{
    public function visitYourNew(YourNewNode $node): mixed;
}
```

## 🧪 Testing Requirements

**All contributions MUST include tests.** No exceptions. Quality code requires quality tests.

### Test Coverage Requirements

- ✅ **New features:** Comprehensive unit tests + integration tests
- ✅ **Bug fixes:** Test that reproduces the bug, then verify the fix
- ✅ **Behavioral changes:** Behavioral compliance tests against PHP's PCRE
- ✅ **Performance changes:** Benchmark tests to measure impact

### Test Structure & Best Practices

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
        // Given: Clear setup
        $input = 'test input';

        // When: Action being tested
        $result = YourClass::process($input);

        // Then: Assertions
        $this->assertSame('expected output', $result);
    }
}
```

### Test Organization

| Test Type             | Location                                         | Purpose                                 |
|-----------------------|--------------------------------------------------|-----------------------------------------|
| **Unit Tests**        | `tests/Unit/`                                    | Test individual components in isolation |
| **Integration Tests** | `tests/Integration/`                             | Test component interactions             |
| **Behavioral Tests**  | `tests/Integration/BehavioralComplianceTest.php` | Verify PCRE compliance                  |

### Running Tests

```bash
# Run complete test suite
vendor/bin/phpunit

# Run specific test types
vendor/bin/phpunit tests/Unit          # Unit tests only
vendor/bin/phpunit tests/Integration  # Integration tests only

# Run specific files/methods
vendor/bin/phpunit tests/Unit/Parser/ParserTest.php
vendor/bin/phpunit --filter test_parse_simple_literal

# Run with coverage
vendor/bin/phpunit --coverage-html=coverage/
```

### Behavioral Compliance Testing

For PCRE features, ensure your implementation matches PHP's engine exactly:

```php
#[DataProvider('providePcrePatterns')]
public function test_behavior_matches_php_pcre(string $pattern, array $testCases): void
{
    $parser = new Parser();
    $compiler = new CompilerNodeVisitor();

    $ast = $parser->parse($pattern);
    $compiledPattern = $ast->accept($compiler);

    foreach ($testCases as $input => $expectedMatch) {
        $phpResult = preg_match($pattern, $input);
        $ourResult = preg_match($compiledPattern, $input);

        $this->assertSame($phpResult, $ourResult,
            "Pattern '$pattern' behavior mismatch on input '$input'");
        $this->assertSame($expectedMatch, $phpResult,
            "Expected match result for '$input' doesn't match PHP PCRE");
    }
}

public static function providePcrePatterns(): iterable
{
    yield 'basic literals' => [
        '/hello/',
        ['hello' => true, 'world' => false]
    ];
}
```

## 📤 Submitting Changes

### Commit Message Guidelines

Write clear, descriptive commit messages following conventional commits:

```bash
# ✅ Good examples:
git commit -m "fix: backreference compilation now escapes numeric refs"
git commit -m "feat: add support for branch reset groups (?|...)"
git commit -m "test: add behavioral compliance tests for lookarounds"
git commit -m "docs: update README with Symfony integration example"
git commit -m "refactor: simplify parser state management"

# ❌ Bad examples:
git commit -m "fix bug"
git commit -m "updates"
git commit -m "WIP"
git commit -m "Fixed stuff"
```

**Format Structure:**
```
type(scope): description

[optional body]

[optional footer]
```

**Types:**
- `fix:` - Bug fixes
- `feat:` - New features
- `test:` - Testing related changes
- `docs:` - Documentation updates
- `refactor:` - Code restructuring
- `perf:` - Performance improvements
- `chore:` - Maintenance tasks

### Pull Request Process

#### 1. Prepare Your Branch
```bash
# Sync with upstream
git checkout main
git pull upstream main

# Rebase your feature branch
git checkout your-feature-branch
git rebase main

# Force push if needed
git push --force-with-lease origin your-feature-branch
```

#### 2. Create Pull Request
- Go to GitHub and create a PR from your branch
- Use the PR template below

#### 3. PR Description Template
```markdown
## 🎯 Description
Brief description of what this PR accomplishes

## 📋 Type of Change
- [ ] 🐛 Bug fix (non-breaking change)
- [ ] ✨ New feature (non-breaking change)
- [ ] 💥 Breaking change
- [ ] 📚 Documentation update
- [ ] 🔧 Refactoring
- [ ] 🧪 Testing improvements

## 🔧 Changes Made
- Specific change 1
- Specific change 2
- Technical details...

## 🧪 Testing
- [x] All existing tests pass
- [x] New tests added for new functionality
- [ ] Behavioral compliance tests updated
- [ ] Manual testing completed

## ✅ Checklist
- [x] Code follows project style guidelines
- [x] Self-review completed
- [x] Comments added for complex logic
- [x] Documentation updated
- [x] No new PHPStan/Rector warnings
- [x] Commit messages follow guidelines

## 🔗 Related Issues
Closes #123, Fixes #456
```

#### 4. Address Review Feedback
- **Respond promptly** to reviewer comments
- **Make requested changes** or explain why changes aren't needed
- **Push updates** to the same branch
- **Request re-review** when ready

#### 5. Merge Process
Once approved:
- Maintainers will merge using "Squash and merge"
- Delete the feature branch after merging
- PR will be closed automatically

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

## 🎯 Areas for Contribution

### 🔥 High Priority

#### 1. Integration Testing
- End-to-end tests for PHPStan regex validation rules
- End-to-end tests for Rector regex refactoring rules
- Symfony bundle integration and compatibility tests

#### 2. PCRE Feature Coverage
- Script runs `(*sr:...)` and other script-related features
- Additional PCRE verbs (`(*ACCEPT)`, `(*FAIL)`, etc.)
- Edge cases and complex regex patterns
- Unicode property support expansion

#### 3. Documentation & Examples
- Comprehensive usage examples for all features
- Video tutorials for getting started
- Blog posts and case studies
- API documentation improvements

### 🟡 Medium Priority

#### 1. Performance Optimization
- Parser algorithm optimizations
- Visitor pattern performance improvements
- Memory usage optimization for large regexes
- Benchmark suite expansion and automation

#### 2. Developer Experience
- Enhanced error messages with suggestions
- IDE autocompletion support and type hints
- Debugging tools for AST inspection
- Interactive regex builder/tester

### 🟢 Low Priority

#### 1. Platform Support
- Docker development environment
- GitHub Actions CI/CD pipeline
- Automated releases and versioning
- Multi-platform testing (Windows, macOS, Linux)

#### 2. Ecosystem Integration
- Laravel package wrapper
- VS Code extension for regex highlighting
- Web-based regex tester integration
- Third-party tool integrations

## ❓ Questions & Support

### Getting Help
- **📖 Documentation:** Check the [README](README.md) first
- **💬 General Questions:** [GitHub Discussions](https://github.com/yoeunes/regex-parser/discussions)
- **🐛 Bug Reports:** [GitHub Issues](https://github.com/yoeunes/regex-parser/issues)
- **🔒 Security Issues:** Email maintainers directly (see README)

### Community Resources
- **📧 Email:** younes.ennaji.pro@gmail.com
- **🐙 GitHub:** [yoeunes/regex-parser](https://github.com/yoeunes/regex-parser)
- **💼 LinkedIn:** [Younes ENNAJI](https://www.linkedin.com/in/younes--ennaji/)

## 📄 License

By contributing to RegexParser, you agree that your contributions will be licensed under the **MIT License**.

---

## 🎉 Recognition

Contributors will be:
- Listed in the project's CONTRIBUTORS file
- Mentioned in release notes
- Recognized in the README
- Invited to join the project maintainer team for significant contributions

**Thank you for contributing to RegexParser and helping make regex parsing better for everyone!** 🚀✨
