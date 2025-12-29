# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2025-12-29

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
- None (initial release)

### Deprecated
- None

### Removed
- None

### Breaking Changes
- None (initial release)
