# Maintainers Guide

This guide is for maintainers and integrators building tools around RegexParser: linters, framework bridges, CI pipelines, or IDE plugins.

> We keep the public API stable and the AST immutable so your tooling can evolve safely.

## Integration Checklist

```
[ ] Create a shared Regex instance via Regex::create()
[ ] Decide on caching strategy (FilesystemCache vs NullCache)
[ ] Run validate() before storing patterns
[ ] Run redos() for security checks
[ ] Clear validator caches in long-running workers
[ ] Emit diagnostics with byte offsets
```

## Integration Patterns

### 1. Wrap RegexParser in a Service

```php
use RegexParser\Regex;

final class RegexAuditService
{
    public function __construct(private Regex $regex) {}

    public static function create(): self
    {
        return new self(Regex::create([
            'runtime_pcre_validation' => true,
        ]));
    }

    public function audit(string $pattern): array
    {
        $result = $this->regex->validate($pattern);
        $redos = $this->regex->redos($pattern);

        return [
            'valid' => $result->isValid(),
            'error' => $result->error,
            'redos' => $redos->severity->value,
        ];
    }
}
```

### 2. Run Full Analysis in CI

`Regex::analyze()` aggregates validation, lint, optimization, explanation, and ReDoS analysis into one report. This is the simplest path for CI.

### 3. Build a Custom Visitor

If you need a custom rule, add a visitor. Start from `AbstractNodeVisitor` and override only the nodes you care about.

> Visitors are the safest extension point. They do not change the AST contract.

## Long-Running Processes

If you run RegexParser inside workers or daemons, clear static caches periodically.

```php
$regex = Regex::create();

foreach ($patterns as $i => $pattern) {
    $regex->validate($pattern);

    if (0 === ($i + 1) % 100) {
        $regex->clearValidatorCaches();
    }
}
```

## Design Constraints You Should Respect

- Nodes are immutable and carry source positions. Do not mutate.
- The AST is the contract between the parser and all visitors.
- The lexer and parser use byte offsets, not Unicode code points.
- ReDoS analysis depends on structure, not heuristics.

> If you change AST structure, bump the cache version and update the node reference.

## Where to Look Next

- Public API and options: `docs/reference/api.md`
- Diagnostics catalog: `docs/reference.md`
- CLI usage and JSON output: `docs/guides/cli.md`
- Visitor mechanics: `docs/design/AST_TRAVERSAL.md`

---

Previous: `ARCHITECTURE.md` | Next: `EXTENDING_GUIDE.md`
