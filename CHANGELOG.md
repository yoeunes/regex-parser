# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2024-12-24

### Added
- Support for modern PCRE2 syntax including subroutines, conditionals, and recursion
- Comprehensive validation with detailed error messages and PCRE compliance checks
- Security auditing with ReDoS (Catastrophic Backtracking) risk analysis
- Automatic optimization with modernization and pattern transformations
- Human-readable explanations, HTML visualizations, and sample string generation
- Bridge integrations for Symfony framework and PHPStan static analysis
- Command-line tools for regex analysis and linting

### Fixed
- **Memory leak in ValidatorNodeVisitor**: Added cache size limiting with MAX_CACHE_SIZE constant and clearCaches() method for long-running processes
- **Quote mode handling in Lexer**: Fixed T_QUOTE_MODE_END tokenization and proper EOF behavior
- **Unicode escape consistency**: Restored original behavior where `\xNN` converts to character while `\u{NNNN}` preserves format
- **Parser position recovery**: Rewrote parseNumericSubroutine() for accurate token tracking and position rewinding
- **Auto-possessivization**: Made possessive quantifier conversion opt-in (default: true via API, false for direct use)

### Security
- Added static cache size limits to prevent unbounded memory growth
- Fixed quote mode parsing to prevent potential infinite loops
- Enhanced validation of Unicode code points and PCRE verb parsing

### Performance
- Optimized token matching with precompiled regex patterns
- Added intelligent AST caching for repeated operations
- Improved sequence parsing with reduced method call overhead
- Enhanced character class compilation with optimized meta-character handling

### Changed
- **Auto-possessivization**: Now configurable via `autoPossessify` option in OptimizerNodeVisitor and Regex::optimize()
- **Parser refactoring**: Added parseSimpleGroup() helper to reduce code duplication in parseGroupModifier()
- **Type safety**: Added return type declarations to all closures across the codebase
- **Error codes**: Standardized error reporting with consistent dot notation format

### Deprecated
- None

### Removed
- None

### Security
- All identified potential vulnerabilities have been addressed

### Breaking Changes
- Auto-possessivization is now configurable with default behavior preserved for existing API usage

### Migration Guide
- Existing code continues to work without changes
- To enable auto-possessivization for performance-critical applications:
  ```php
  $optimized = Regex::create()->optimize('/\d+a/', ['autoPossessify' => true])->optimized;
  ```
- To clear caches in long-running processes:
  ```php
  Regex::create()->clearValidatorCaches();
  ```