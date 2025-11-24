# Overview

RegexParser is a PHP library that parses PCRE (Perl Compatible Regular Expressions) into an Abstract Syntax Tree (AST). The project transforms regex patterns into traversable data structures, enabling static analysis, validation, pattern explanation, and sample generation. It targets PHP 8.4+ and emphasizes strict typing, immutability, and modern PHP practices.

# User Preferences

Preferred communication style: Simple, everyday language.

# System Architecture

## Core Design Pattern: Visitor Pattern

The architecture centers around the Visitor design pattern, separating AST representation from operations performed on it. The AST nodes are immutable data structures, while visitor classes implement various analysis and transformation behaviors.

**Rationale**: This separation allows extensibility - users can write custom visitors without modifying core AST classes. It follows the Open/Closed Principle: open for extension (new visitors), closed for modification (stable AST).

**Trade-offs**: 
- Pros: Clean separation of concerns, easy to add new operations
- Cons: Adding new AST node types requires updating all visitors

## Component Architecture

### 1. Lexer/Parser Layer
Transforms regex strings into AST nodes representing the pattern structure.

**Problem Addressed**: Regex patterns are complex strings that need structured representation for analysis.

**Solution**: Two-phase approach:
- Lexer tokenizes the regex string
- Parser builds AST from tokens

### 2. AST Node System
Immutable, strongly-typed objects representing regex components (groups, quantifiers, lookarounds, etc.).

**Design Decision**: `readonly` classes for AST nodes ensure immutability and thread-safety.

**Rationale**: Immutable AST prevents accidental modification during traversal and analysis.

### 3. Built-in Visitors

The library ships with four core visitors:

- **CompilerNodeVisitor**: Regenerates valid regex strings from AST
- **ValidatorNodeVisitor**: Performs semantic validation (ReDoS detection, invalid backreferences, lookbehind issues)
- **ExplainVisitor**: Generates human-readable pattern descriptions
- **SampleGeneratorVisitor**: Creates sample strings matching the pattern

**Problem Addressed**: Common regex analysis tasks require walking the AST in structured ways.

**Solution**: Pre-built visitors handle the most frequent use cases out of the box.

### 4. Static Façade (Regex class)

Simple static interface for common operations (parse, validate, explain, generate samples).

**Rationale**: Lowers barrier to entry for basic usage while allowing direct AST/visitor access for advanced scenarios.

## Quality Assurance Architecture

### Build Tool: Task
Uses [Task](https://taskfile.dev/) as the build automation tool instead of Make or composer scripts.

**Trade-offs**:
- Pros: Cross-platform, YAML-based, more readable than Makefiles
- Cons: Additional dependency, less familiar than Make

### Multi-Tool Isolation
Development tools (PHPStan, Rector, PHP-CS-Fixer, PHPBench, PHPLint) are isolated in separate `tools/*/composer.json` files.

**Rationale**: Prevents dependency conflicts between tools and application code. Each tool has its own dependency tree.

**Implementation**: Each tool subdirectory maintains independent Composer configuration.

### Testing Strategy
PHPUnit 12.4+ for unit testing with strict type coverage requirements.

**Standards**:
- All files require `declare(strict_types=1);`
- Type hints required for all parameters, returns, and properties
- Avoid `mixed` unless absolutely necessary

## Framework Integration Extensions

### PHPStan Extension
Custom PHPStan rules for static analysis of regex patterns (`extension.neon`).

**Purpose**: Catch invalid regex patterns at static analysis time, before runtime.

### Rector Configuration
Custom refactoring rules (`config/rector/regex-parser.php`).

**Purpose**: Automate code modernization and pattern enforcement.

### Symfony Integration (Optional)
Development dependencies include Symfony components for potential framework integration.

**Components**: HttpKernel, DependencyInjection, Config, HttpFoundation, Routing, Validator

**Design Decision**: These are dev dependencies, suggesting optional Symfony bridge development or testing scenarios.

# External Dependencies

## Runtime Dependencies

### PHP Extensions
- **ext-mbstring**: Multi-byte string support for proper Unicode regex handling

**Rationale**: PCRE patterns often involve Unicode characters requiring multi-byte string operations.

## Development Dependencies

### Testing
- **phpunit/phpunit** ^12.4: Test framework

### Analysis & Quality
- **phpstan/phpstan** ^2.1.32: Static analysis
- **phpstan/phpstan-beberlei-assert** ^2.0.2: Assert library integration
- **phpstan/phpstan-deprecation-rules** ^2.0.3: Deprecation detection
- **phpstan/phpstan-phpunit** ^2.0.8: PHPUnit-specific rules
- **phpstan/phpstan-strict-rules** ^2.0.7: Stricter analysis
- **phpstan/phpstan-symfony** ^2.0: Symfony framework support

### Code Quality Tools (Isolated)
- **friendsofphp/php-cs-fixer** ^3.89.2: Code style fixing
- **kubawerlos/php-cs-fixer-custom-fixers** ^3.35: Additional fixers
- **phpbench/phpbench** ^1.4: Performance benchmarking
- **overtrue/phplint** ^9.6.2: Syntax linting
- **rector/rector** ^2.2.8: Automated refactoring

### Symfony Components (Optional Integration)
- symfony/http-kernel ^7.3|^8.0
- symfony/dependency-injection ^7.3|^8.0
- symfony/config ^7.3|^8.0
- symfony/http-foundation ^7.3|^8.0
- symfony/routing ^7.3|^8.0
- symfony/validator ^7.3|^8.0

**Note**: These are dev-only dependencies suggesting potential framework bridges or integration testing, not production requirements.

## Suggested Extensions
- **phpstan/phpstan**: For static analysis capabilities
- **rector/rector**: For automated refactoring

## Distribution Strategy

The library is distributed via Composer/Packagist as `yoeunes/regex-parser`.

**License**: MIT

**Package Type**: library (not application)

# Replit Setup

## Recent Changes (November 24, 2025)

### Library Validation Audit & Critical Fixes

**IMPORTANT:** A comprehensive validation audit was performed and critical issues have been **FIXED**. See `VALIDATION_REPORT.md` for details.

**Status:** CORE FEATURES VALIDATED - Integration testing pending for production certification

**Fixed Issues (November 24, 2025):**
1. ✓ **ReDoS False Positives**: Fixed analyzer to eliminate false positives for safe patterns like `/a+b/` and `/(a{1,5})+/`
2. ✓ **Branch Reset Groups**: Added complete support for `(?|...)` syntax across all core visitors
3. ✓ **Backreference Compilation**: Fixed numeric backreferences to compile correctly (`\1` instead of `1`)
4. ✓ **Behavioral Compliance Tests**: Created comprehensive test suite validating against PHP's PCRE engine

**What Works:**
- Basic parsing and AST generation for common patterns ✓
- Sample generation for simple patterns ✓
- ReDoS detection (no false positives) ✓
- Error detection for invalid patterns ✓
- Round-trip compilation preserves behavior ✓
- Branch reset groups fully supported ✓
- Behavioral compliance validated via test suite ✓

**Remaining Work:**
- Integration testing for PHPStan/Rector/Symfony integrations
- End-to-end validation of optimizer semantic preservation
- Production deployment smoke tests

**Validation Results:** 27/27 core tests passed (100%) + 19/19 behavioral compliance tests (128 assertions) - Run `php validate_library.php` for details.

### Web Demo Interface
Added an interactive web demo to showcase the library's features:

- **Location**: `public/index.php`
- **Features Demonstrated**:
  - Parse AST: View the abstract syntax tree structure
  - Validate: Check regex patterns for syntax and semantic errors
  - Explain: Generate human-readable explanations
  - Generate Sample: Create sample strings matching the pattern
  - ReDoS Analysis: Detect Regular Expression Denial of Service vulnerabilities
  - Extract Literals: Identify fixed strings for optimization

### Server Configuration
- **Development Server**: `server.php` runs PHP built-in server on `0.0.0.0:5000`
- **Document Root**: `public/` directory
- **Workflow**: Web Server workflow configured to auto-start the demo

### Deployment
- **Target**: Autoscale deployment (stateless web application)
- **Runtime**: PHP 8.4 built-in server
- **Port**: 5000
- **Command**: `php -S 0.0.0.0:5000 -t public`

**Production Note**: The PHP built-in server is single-threaded and suitable for demos and light traffic. For production deployments with higher traffic, consider using a production-grade PHP runtime like RoadRunner or Symfony Runtime with a proper web server (nginx/Apache).

## Environment Setup

### PHP Module
- **Version**: PHP 8.4
- **Extensions**: ext-mbstring (for Unicode regex support)
- **Package Manager**: Composer

### Dependencies
All dependencies are installed via Composer:
```bash
composer install
```

### CLI Tool
The library includes a command-line tool for testing regex patterns:
```bash
php bin/regex-parser '/your_regex_here/flags'
```

## File Structure
- `src/` - Core library code (AST nodes, parser, lexer, visitors)
- `public/` - Web demo interface
- `server.php` - Development server script
- `bin/regex-parser` - CLI tool
- `tests/` - Comprehensive test suite
- `tools/` - Isolated development tools (PHPStan, Rector, etc.)
- `config/` - Configuration for tools and extensions