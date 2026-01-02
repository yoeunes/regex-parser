# Regex Parser Benchmarks

This directory contains performance benchmark scripts for various components of the Regex Parser library.

## Available Benchmarks

- `benchmark_tokenstream.php` - TokenStream performance tests
- `benchmark_lexer.php` - Lexer performance tests
- `benchmark_parser.php` - Parser performance tests
- `benchmark_literalset.php` - LiteralSet performance tests
- `benchmark_complexity.php` - ComplexityScoreNodeVisitor performance tests
- `benchmark_validator.php` - ValidatorNodeVisitor performance tests
- `benchmark_compiler.php` - CompilerNodeVisitor performance tests
- `benchmark_regexoptions.php` - RegexOptions performance tests
- `benchmark_formatters.php` - Console, GitHub, and Symfony formatter output benchmarks

Note: The Symfony formatter benchmark runs only when `symfony/console` is installed.

## Running Benchmarks

To run a specific benchmark:

```bash
php benchmarks/benchmark_tokenstream.php
```

To run all benchmarks:

```bash
for file in benchmarks/benchmark_*.php; do echo "Running $file"; php "$file"; echo; done
```

## Benchmark Results

Each benchmark measures:
- Execution time for various operations
- Memory usage
- Performance improvements after optimizations

The benchmarks help ensure that performance optimizations don't regress over time.
