# FAQ and Glossary

Short answers to common questions plus quick definitions of core terms.

## FAQ

**Does RegexParser execute regexes?**
No, it parses and analyzes patterns statically. Runtime validation is optional
and uses a safe compile check with `preg_match()`.

**Is this PCRE2-only?**
Yes. RegexParser targets PHP's `preg_*` engine (PCRE2).

**Does this guarantee ReDoS safety?**
No. It detects known risky structures and suggests safer shapes, but safety
still depends on input, flags, and runtime limits.

**What is tolerant parsing?**
`parse($regex, true)` returns a `TolerantParseResult` with an AST plus errors so
tools can keep going even when a pattern is partially invalid.

**Can I use this in CI?**
Yes. Use the CLI `lint` command or the PHPStan rule for codebase scanning.

**Why an AST?**
ASTs give stable structure for precise diagnostics, refactors, and tooling.

## Glossary

- **AST (Abstract Syntax Tree)**: a structured tree representation of the regex.
- **Node**: a single AST element (literal, group, quantifier, etc.).
- **Visitor**: an algorithm that traverses the AST (compile, explain, lint).
- **PCRE2**: the regex engine used by PHP `preg_*` functions.
- **ReDoS**: Regular Expression Denial of Service (catastrophic backtracking).
- **Backtracking**: engine behavior that retries alternative paths on failure.
- **Lookaround**: zero-width assertion like `(?=...)` or `(?<=...)`.
- **Atomic group**: `(?>...)`, prevents backtracking inside the group.
- **Possessive quantifier**: `*+`, `++`, `{m,n}+`, no backtracking.
- **Branch reset**: `(?|...)`, resets capture numbering per branch.
- **Subroutine**: `(?1)` or `(?&name)`, reuse a group definition.

---

Previous: [Diagnostics](diagnostics.md) | Next: [Diagnostics Cheat Sheet](diagnostics-cheatsheet.md)
