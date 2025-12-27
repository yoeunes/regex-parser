# Regex Cookbook (Safer Patterns for PHP)

This page provides a small set of **ReDoS-resilient** patterns for common use cases.
Each pattern is anchored and avoids ambiguous, nested backtracking. The examples
have been validated with RegexParser's ReDoS analyzer and are optimized by design.

No regex can be guaranteed safe in all contexts. Always apply input limits and
engine safeguards when matching untrusted data (for example, backtrack limits
or timeouts).

> These patterns are intentionally conservative. For edge-case validation,
> consider domain-specific parsing instead of regex.

## Email (RFC 5322 simplified)

```text
/^[a-z0-9]+(?:[._%+-][a-z0-9]+)*+@[a-z0-9-]+(?:\.[a-z0-9-]+)++$/i
```

- Simplified but practical for most application input validation.
- Uses possessive quantifiers to avoid backtracking on long inputs.

## UUID (v1-v5)

```text
/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i
```

- Fully bounded; no unbounded quantifiers.

## Dates (ISO 8601, date only)

```text
/^\d{4}-\d{2}-\d{2}$/
```

- Strict `YYYY-MM-DD` format.
- Use additional validation if you need calendar correctness.

## Slugs (lowercase, hyphen-separated)

```text
/^[a-z0-9]+(?:-[a-z0-9]+)*+$/
```

- Allows `my-post-123`, rejects leading/trailing hyphens.
- Possessive quantifier ensures linear matching time.

---

Previous: [ReDoS Guide](REDOS_GUIDE.md) | Next: [Architecture](ARCHITECTURE.md)
