# Diagnostics Cheat Sheet

Fast fixes for common RegexParser diagnostics. For full context and rule IDs,
see the lint reference.

## Quick fixes

- **Lookbehind is unbounded**
  - Make the lookbehind fixed length, or rewrite using a lookahead + capture.
  - Use `(*LIMIT_LOOKBEHIND=...)` only if you control the engine limits.

- **Backreference to non-existent group**
  - Renumber the backreference or add the missing group.
  - For named backrefs, ensure `(?<name>...)` exists before `\k<name>`.

- **Duplicate group name**
  - Use unique names, or add the `(?J)` modifier if duplicates are intended.

- **Invalid quantifier range**
  - Fix `{min,max}` where `min` must be <= `max`.

- **Nested quantifiers detected**
  - Flatten the quantifier or use an atomic group/possessive quantifier.

- **Dot-star in repetition**
  - Replace `.*` with a specific character class or make it atomic/possessive.

- **Overlapping alternation branches**
  - Order longer branches first or make the alternation atomic.

- **Redundant non-capturing group**
  - Remove the extra `(?:...)` wrapper.

- **Useless flag (i/m/s)**
  - Remove the flag or add the token it affects (`.` for `s`, anchors for `m`).

- **Invalid delimiter**
  - Use `/pattern/flags` or escape the chosen delimiter.

## Where to look next

- Full rule reference: [docs/reference.md](../reference.md)
- Diagnostics deep dive: [docs/reference/diagnostics.md](diagnostics.md)
- ReDoS patterns: [docs/REDOS_GUIDE.md](../REDOS_GUIDE.md)

---

Previous: [FAQ and Glossary](faq-glossary.md) | Next: [Reference Index](README.md)
