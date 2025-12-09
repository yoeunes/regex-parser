# Agent Instructions for RegexParser

## Build/Lint/Test Commands

### Testing
- **All tests**: `composer test` or `vendor/bin/phpunit`
- **Single test method**: `vendor/bin/phpunit --filter test_method_name`
- **Single test file**: `vendor/bin/phpunit tests/SpecificTest.php`
- **Unit tests only**: `vendor/bin/phpunit tests/Unit`
- **Integration tests only**: `vendor/bin/phpunit tests/Integration`

### Code Quality
- **All QA checks**: `composer qa`
- **PHPStan (static analysis)**: `composer qa:phpstan`
- **PHP-CS-Fixer (lint)**: `composer qa:php-cs-fixer`
- **PHP-CS-Fixer (fix)**: `composer fix:php-cs-fixer`
- **Rector (refactor)**: `composer qa:rector`
- **Rector (apply fixes)**: `composer fix:rector`

### Benchmarking
- **Run benchmarks**: `composer bench`

## Code Style Guidelines

### PHP Standards
- PSR-12 + Symfony coding standards
- PHP 8.2 minimum, bleeding edge features enabled
- Strict types declaration required (`declare(strict_types=1)`)
- Short array syntax (`[]` instead of `array()`)
- Single quotes for strings
- 4 spaces indentation (no tabs)

### Imports & Organization
- Ordered imports: const, class, function
- Remove unused imports automatically
- No leading slashes in imports
- Single import per statement
- Blank line after imports

### Naming Conventions
- **Classes**: PascalCase (e.g., `RegexParser`)
- **Methods/Properties**: camelCase (e.g., `parsePattern`)
- **Constants**: UPPER_SNAKE_CASE (e.g., `DEFAULT_MAX_PATTERN_LENGTH`)
- **Test methods**: snake_case (e.g., `test_parse_simple_literal`)

### Code Structure
- Final classes by default
- Readonly classes where appropriate
- Ordered class elements (constants, properties, methods)
- One property/method per line in class definitions
- Header comment required on all files
- No trailing whitespace or empty lines at EOF

### Error Handling
- Custom exceptions in `src/Exception/`
- Descriptive error messages
- Proper exception hierarchy

### Documentation
- PHPDoc comments for public APIs
- No superfluous PHPDoc tags
- Proper type hints in PHPDoc
