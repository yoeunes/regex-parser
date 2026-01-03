# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.8] - 2026-01-03

### Changed
- Improved lint command output clarity: changed progress labels from "Collecting patterns" to "Scanning files" and added clear summary showing "Scanned X files, found Y patterns" to eliminate confusion about what progress bars represent.
- Removed redundant collection timing display from lint output.

### Fixed
- Fixed Lexer token offsets for `\xNN` escape sequences.
- Fixed Compiler output for non-ASCII characters when using the `/u` flag.
- Fixed cache key generation to include library versioning.
- Improved CLI pattern detection for non-standard delimiters.

## [1.0.7] - 2026-01-03

### Added
- Corpus-based test suite (`LinterNodeVisitorCorpusBugsTest`) to prevent regressions based on real-world patterns from open-source projects.
- Tests to verify suspicious ASCII range `A-z` detection (includes non-letter characters between Z and a).

### Fixed
- Corrected suspicious ASCII range detection to properly identify `A-z` as including non-letter characters (`[ \ ] ^ _ \` `) and recommend `A-Za-z` instead.
- Linter now correctly skips unparseable regex patterns in test extraction to prevent crashes.
- Improved corpus log generation to include accurate pattern column and file offset metadata.

## [1.0.6] - 2026-01-02

### Added
- Lint results now include pattern columns and file offsets to disambiguate multiple patterns on the same line.

### Changed
- Default lint excludes now skip `vendor`, `tests`, and `Fixtures` in the CLI and Symfony bridge configuration.
- Console and Symfony lint output now include column numbers when available.

### Fixed
- Inline flag linting now respects flags set by earlier inline modifiers in the same sequence.
- Alternation duplicate linting now ignores lookaround-only branches to avoid false positives.
- ReDoS empty-repeat severity is downgraded for recursive patterns with possess/atomic branches.

### Tests
- Added coverage for token-based and PHPStan extraction column/offset metadata, inline-flag sequencing, lookaround alternations, and Symfony route requirement patterns.

## [1.0.5] - 2026-01-02

### Added
- Python named group syntax preservation: `(?P<name>...)` syntax is now preserved when parsing and compiling regex patterns instead of being converted to `(?<name>...)`.
- Added `usePythonSyntax` property to `GroupNode` to track original named group syntax.
- Added `Style` and `Perf` severity levels to the `Severity` enum for categorizing lint issues.
- Added `severity` property to `LintIssue` class for categorizing issues by severity level.

### Changed
- Linter now only flags overlapping alternation warnings when the alternation is inside an unbounded quantifier (e.g., `+`, `*`, `{n,}`), reducing false positives for safe patterns like `/\r\n|\r|\n/` and `/^(978|979)/`.
- Improved heuristic for detecting alternations inside unbounded quantifiers to properly handle nested parentheses.

### Fixed
- Fixed false positive overlap warnings for patterns without quantifiers that don't pose ReDoS risk.
- Fixed pretty-print mode in `CompilerNodeVisitor` to output correct regex syntax for lookaheads (`(?=`), lookbehinds (`(?<!`), atomic groups (`(?>`), branch resets (`(?|`), and inline flags (`(?im:`).
- Built the `/r` modifier probe regex dynamically to avoid static regex validation false positives.

### Tests
- Updated `LinterRulesTest::test_alternation_overlap_warning` to use pattern with unbounded quantifier.
- Updated `OptimizerTest` to expect Python syntax preservation.
- Updated `CompilerNodeVisitorCoverageTest` to expect correct regex syntax in pretty-print mode.
- Reworked formatter and visitor coverage tests to assert behavior, and added coverage for JSON payload normalization and validator errors.

## [1.0.4] - 2026-01-02

### Added
- Added a formatter benchmark script to measure lint output throughput and memory usage.
- Added atomic-group tip suggestions for nested-quantifier and dot-star lint warnings.

### Documentation
- Documented formatter benchmark usage in the README and benchmarks guide.
- Documented the `suggestedPattern` lint issue field in diagnostics reference.

### Changed
- Optimizer now reuses a compiler visitor while resetting its state between string conversions.
- Lint output formatters now assemble output using buffered chunks for large reports.
- PHPStan pattern truncation now relies on a named constant.
- Suspicious ASCII range warnings now describe the ASCII-order endpoints of the reported range.
- Atomic-group lint suggestions are now validated before being emitted.
- Extended-mode optimization tips now diff against a normalized baseline to avoid mixing raw formatting with pretty-printed suggestions.

### Tests
- Added regression coverage to ensure non-alternation patterns do not trigger overlap warnings.
- Added ReDoS coverage for nested possessive quantifier patterns that should remain safe.
- Added coverage for compiler state resets between compilations.
- Added coverage for the PHPStan truncation default length.
- Added coverage for lint suggestion tips and the updated ASCII range message.
- Added coverage for escaped dollar literals, char-class group-like tokens, and extended-mode optimization baselines.
- Added coverage for invalid delimiter validation in lint analysis.

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
