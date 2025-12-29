# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2025-12-29

### Added
- PCRE-style lexer/parser that builds a typed AST with byte offsets (groups, alternation, quantifiers, lookarounds, character classes, subroutines, conditionals, recursion, callouts, verbs)
- Regex facade APIs: parse, tokenize, validate (error codes + caret snippets + optional runtime PCRE validation), analyze, redos, optimize, explain, highlight, generate, literals
- AST visitors: compiler, optimizer, modernizer, linter, explainer/highlighters, literal extractor, sample generator, complexity scoring
- ReDoS analyzer with severity scoring and thresholds
- CLI `vendor/bin/regex` for analyze/highlight/validate/lint, config file support, and CI output formats
- Symfony and PHPStan bridges
- Cache interfaces, cache stats, and `ValidatorNodeVisitor::clearCaches()` for long-running processes

### Documentation
- User docs including Quick Start, CLI guide, references, and tutorial

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
