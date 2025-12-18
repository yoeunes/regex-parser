# Contributing to RegexParser

🎉 **Thank you for your interest in contributing to RegexParser!**

## Code of Conduct

This project follows the [Contributor Covenant Code of Conduct](CODE_OF_CONDUCT.md):

- **Be respectful** - Treat all contributors with respect and kindness
- **Be constructive** - Provide helpful, actionable feedback
- **Be collaborative** - Work together toward common goals
- **Be patient** - Remember that everyone is learning and mistakes happen

## Quick Start

```bash
# 1. Fork & clone
git clone https://github.com/YOUR-USERNAME/regex-parser.git
cd regex-parser

# 2. Set up environment
composer install

# 3. Install Development Tools (if you have Task installed)
task install

# 4. Make your changes and submit a PR!
```

## Development Workflow

### Making Changes
1. Follow coding standards (strict types, type declarations, immutability)
2. Write tests first (TDD approach recommended)
3. Keep commits focused on single changes
4. Update documentation as needed

### Quality Assurance

```bash
# Run all checks
task lint
```

## Coding Standards

- **Strict types** required for all PHP files
- **Type declarations** for all parameters, return types, and properties
- **Immutability** - use `readonly` classes and properties where possible
- **Naming conventions** - PascalCase for classes, camelCase for methods

## Testing Requirements

All contributions MUST include tests:

```php
<?php
declare(strict_types=1);

namespace RegexParser\Tests\Unit;

use PHPUnit\Framework\TestCase;

class YourClassTest extends TestCase
{
    public function test_descriptive_name(): void
    {
        // Given
        $input = 'test input';

        // When
        $result = YourClass::process($input);

        // Then
        $this->assertSame('expected output', $result);
    }
}
```

## Submitting Changes

### Commit Messages
Use conventional commits:
```bash
git commit -m "fix: backreference compilation now escapes numeric refs"
git commit -m "feat: add support for branch reset groups (?|...)"
git commit -m "test: add behavioral compliance tests for lookarounds"
```

### Pull Request Process
1. Sync with upstream and rebase your branch
2. Create PR using the template
3. Address review feedback
4. Maintainers will squash and merge

## 🎉 Recognition

Contributors will be:
- Listed in the project's CONTRIBUTORS file
- Mentioned in release notes

**Thank you for contributing to RegexParser and helping make regex parsing better for everyone!** 🚀✨
