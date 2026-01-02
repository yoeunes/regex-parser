# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Tests
- Added regression coverage to ensure non-alternation patterns do not trigger overlap warnings.
- Added ReDoS coverage for nested possessive quantifier patterns that should remain safe.

## [1.0.3] - 2026-01-02

### Added
- ReDoS analysis now detects empty-match repetitions and ambiguous adjacent quantifiers.
- Suggested rewrites are surfaced for the highest-severity ReDoS finding.
- Linter warns about suspicious ASCII ranges like `[A-z]` and alternation-like character classes such as `[error|failure]`.

### Changed
- Optimizer now respects Unicode mode when merging digit classes and cleans up unused multiline flags.
- Optimizer can reduce negated digit/word classes to `\D`/`\W` and applies safer auto-possessify rules.
- Linter now recognizes escaped literals and Unicode property classes when evaluating case-sensitive flags, and validates `\g{n}` backreferences and anchor assertions more consistently.
- Optimizer avoids creating new character-class ranges that start or end with `-` unless the range was explicit in the original pattern.
- Highlighter visitors now cover all AST nodes, preserve inline comment text, and emit richer HTML token classes with updated console colors.

### Fixed
- Lint output now properly escapes special characters in alternation branch literals to prevent display issues.

### Tests
- Expanded the ReDoS test suite with real-world patterns and mitigation cases.
- Added optimizer coverage for Unicode handling, negated classes, auto-possessify safety, and flag cleanup.
- Added a corpus-driven linter regression suite plus new unit coverage for Unicode escapes, property flags, anchor assertions, and `\g{}` backreferences.

## [1.0.2] - 2025-12-31

### Added
- Optimization option `minQuantifierCount` to control when repeats are compacted into `{n}`.

### Fixed
- Optimizer now preserves explicit fixed quantifiers (e.g., `\d{2}`) instead of expanding them into repeated literals.

## [1.0.1] - 2025-12-31

### Added
- Regression coverage for optimized character class ranges that include the delimiter.

### Fixed
- Character class compilation now preserves literal `[` where safe and escapes delimiters to prevent invalid ranges.
- Lexer tests updated to reflect literal `[` handling inside character classes.
- Linter anchor checks now reindex sliced sequences to satisfy static analysis.

## [1.0.0] - 2025-12-31

### Added
- **Parser & Lexer**: Full PCRE2-compliant recursive descent parser with lexer that produces a well-typed Abstract Syntax Tree (AST)
- **AST Nodes**: Complete node types including groups, alternations, quantifiers, lookarounds, character classes, subroutines, conditionals, callouts, verbs, and more
- **Regex Facade**: High-level API (`Regex::create()`) for all operations:
  - `parse()` - Parse regex into AST
  - `validate()` - Validate with detailed error codes, caret snippets, and optional runtime PCRE validation
  - `analyze()` - Comprehensive analysis including validation, ReDoS risk, optimizations, and explanations
  - `redos()` - Static ReDoS vulnerability analysis returning `ReDoSAnalysis` with severity scoring and `isSafe()` check
  - `optimize()` - Pattern optimization with configurable rules
  - `explain()` - Human-readable regex explanations
  - `highlight()` - Syntax highlighting for console
  - `generate()` - Generate sample strings matching the pattern
  - `literals()` - Extract literal strings from patterns
- **AST Visitors**: Extensible visitor pattern for transformations:
  - `CompilerNodeVisitor` - Compile AST back to regex string
  - `OptimizerNodeVisitor` - Optimize patterns for performance
  - `ModernizerNodeVisitor` - Modernize legacy patterns
  - `LinterNodeVisitor` - Check patterns for issues
  - `ExplainNodeVisitor` / `HtmlExplainNodeVisitor` - Generate explanations
  - `ConsoleHighlighterVisitor` / `HtmlHighlighterVisitor` - Syntax highlighting
  - `LiteralExtractorNodeVisitor` - Extract literal strings
  - `SampleGeneratorNodeVisitor` - Generate matching samples
  - `ComplexityScoreNodeVisitor` - Calculate complexity scores
- **ReDoS Analyzer**: Static analysis engine for detecting Regular Expression Denial of Service risks with severity levels (CRITICAL, HIGH, MEDIUM, LOW) and actionable recommendations
- **CLI Tool**: Full-featured `vendor/bin/regex` command with subcommands:
  - `parse` - Parse and recompile patterns
  - `analyze` - Full pattern analysis
  - `debug` - Deep ReDoS analysis with heatmap
  - `diagram` - ASCII AST diagrams
  - `highlight` - Syntax highlighting
  - `validate` - Pattern validation
  - `lint` - Lint entire codebases for regex issues
  - `self-update` - PHAR self-update
- **CI/CD Integration**: Multiple output formats (console, JSON, GitHub, Checkstyle, JUnit)
- **Symfony Bridge**: `RegexParserBundle` with DI integration and console commands
- **PHPStan Integration**: Static analysis rule for detecting invalid regex patterns
- **Caching**: PSR-6 / PSR-16 compatible caching with `ArrayCache`, `FilesystemCache`, and stats
- **Configuration**: Comprehensive options for pattern length, lookbehind limits, recursion depth, PHP version targeting
- `explain` command to CLI tool for regex explanations
- `bin/phpunit` and `bin/infection` symlinks for easier tooling access
- `regex.phar` binary for standalone CLI usage

### Documentation
- **Quick Start Guide**: Getting started in 5 minutes
- **CLI Guide**: Complete command reference with examples
- **Regex Tutorial**: Comprehensive tutorial from basics to advanced
- **API Reference**: Complete PHP API documentation
- **ReDoS Guide**: Understanding and preventing ReDoS attacks
- **Cookbook**: Common patterns and recipes
- **Architecture Guide**: Internal design documentation
- **Extending Guide**: Custom visitors and integrations

### Fixed
- None (initial release)

### Changed
- Updated CI workflows to install PHPUnit tooling consistently across jobs
- Improved `.gitattributes` for cleaner distribution archives (excluded more dev files)
- Updated PHP-CS-Fixer config to preserve `@var` tags in `phpdoc_to_comment`
- Added `Fixtures/` exclusion in PHPLint configuration
- Completely rewrote README.md for better user onboarding and navigation

### Deprecated
- None

### Removed
- Deleted automated release workflow from GitHub Actions (manual releases for pre-versions)

### Breaking Changes
- None (initial release)
