# Maintainers Guide

This page is for framework maintainers, library authors, and tooling teams
who want to integrate RegexParser at scale.

## Integration surfaces

Choose the level of integration that fits your stack:

- Single pattern checks: use the `Regex` facade (`validate`, `redos`, `optimize`, `explain`).
- Full analysis report: `Regex::analyze()` bundles validation, ReDoS, optimizations, explain, and highlight output.
- Bulk linting: use `RegexAnalysisService` + `RegexLintService` with custom sources.

## Build a custom pattern source

If your framework stores regexes outside PHP code (routing, config, templates),
implement a source that yields `RegexPatternOccurrence` objects.

```php
use RegexParser\Lint\RegexPatternOccurrence;
use RegexParser\Lint\RegexPatternSourceContext;
use RegexParser\Lint\RegexPatternSourceInterface;

final class MyPatternSource implements RegexPatternSourceInterface
{
    public function getName(): string
    {
        return 'my-source';
    }

    public function isSupported(): bool
    {
        return true;
    }

    public function extract(RegexPatternSourceContext $context): array
    {
        if (!$context->isSourceEnabled($this->getName())) {
            return [];
        }

        // Collect patterns from your config, then map to occurrences.
        return [
            new RegexPatternOccurrence(
                '/^foo$/',
                'config/routes.yaml',
                42,
                $this->getName(),
                displayPattern: '^foo$',
                location: 'route:home:slug'
            ),
        ];
    }
}
```

Then wire it into the lint pipeline:

```php
use RegexParser\Lint\RegexLintRequest;
use RegexParser\Lint\RegexLintService;
use RegexParser\Lint\RegexPatternSourceCollection;
use RegexParser\Lint\RegexAnalysisService;
use RegexParser\Regex;

$analysis = new RegexAnalysisService(Regex::create());
$sources = new RegexPatternSourceCollection([
    new MyPatternSource(),
]);
$lint = new RegexLintService($analysis, $sources);

$request = new RegexLintRequest(paths: ['.'], excludePaths: ['vendor'], minSavings: 1);
$patterns = $lint->collectPatterns($request);
$report = $lint->analyze($patterns, $request);
```

## CI usage and thresholds

- For CI gates, prefer `Regex::redos()` with a severity threshold.
- For large codebases, use linting with `analysisWorkers` for parallel processing.
- Parallel analysis uses `pcntl_fork` and requires CLI SAPI.

## Custom visitors in downstreams

If you only need custom analysis, you can ship your own visitor without
modifying RegexParser itself.

```php
$regex = RegexParser\Regex::create();
$ast = $regex->parse('/foo/');

$visitor = new App\Regex\MyVisitor();
$result = $ast->accept($visitor);
```

## Performance notes

- Reuse a `Regex` instance instead of creating new ones repeatedly.
- Cache ASTs with the `cache` option if patterns are reused across requests.
- Tune limits for your environment: `max_pattern_length`, `max_lookbehind_length`, and `max_recursion_depth`.
- Use `php_version` to target a specific runtime version when linting multiple runtimes.

## Contributing and stability

- The `Regex` facade and result objects are stable within 1.x.
- AST node classes and visitor interfaces may evolve; implement visitors defensively.
- For new nodes or parser changes, follow `docs/EXTENDING_GUIDE.md`.

---

Previous: [Architecture](ARCHITECTURE.md) | Next: [Extending Guide](EXTENDING_GUIDE.md)
