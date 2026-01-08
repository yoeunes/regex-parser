# Safety and Correctness Contracts

This page documents the formal guarantees of each analysis feature: what language is modeled, and whether the results
are **sound** (no false negatives), **complete** (no false positives), or **best-effort**.

## Parser & Validation

**Parser**
- **Semantics:** Parses PCRE syntax into an AST. Intended to preserve byte offsets and structure.
- **Guarantee:** Best-effort PCRE compatibility. Syntax acceptance can be more permissive than PCRE in edge cases.
- **Fallbacks:** Use `Regex::validate()` (with runtime validation enabled) for stricter PCRE checks.

**Validator (`Regex::validate`)**
- **Semantics:** AST-based validation plus optional runtime PCRE compile check.
- **Guarantee:** Best-effort. With runtime validation enabled, PCRE compilation errors are surfaced.
- **Fallbacks:** If runtime validation is disabled, only structural checks run.

## Linting & Optimization

**Lint**
- **Semantics:** Heuristic diagnostics for readability, correctness, and maintainability.
- **Guarantee:** Best-effort; warnings may be conservative. Some rules skip unsupported constructs.
- **Fallbacks:** Unsupported nodes are ignored for that specific rule.

**Optimizer**
- **Semantics:** Applies semantic-preserving rewrites to simplify patterns.
- **Guarantee:** Sound for supported transformations; does not claim completeness.
- **Fallbacks:** Unsafe rewrites are skipped when uncertainty is detected.

## ReDoS Analysis

**ReDoS Analyzer**
- **Semantics:** Static heuristics for catastrophic backtracking risk.
- **Guarantee:** Best-effort. Can produce false positives or miss complex backtracking cases.
- **Fallbacks:** When patterns are unsupported, the analyzer reports a skip reason instead of guessing.

## Automata Solver

**Compare / Equivalence / Subset / Intersection**
- **Semantics:** For supported regexes, the solver builds an NFA and DFA and compares languages using BFS over the
  product automaton. Counter-examples are shortest strings in the modeled language.
- **Guarantee:** **Sound and complete** for the supported regular subset **under byte-based semantics**.
- **Limitations:** Unicode-aware semantics are not modeled. `/u` and case-insensitive behavior are approximated in ASCII.
- **Fallbacks:** Unsupported constructs raise `ComplexityException`.

**Match modes**
- **FULL:** Models exact match language `L`.
- **PARTIAL:** Models search semantics `Σ* L Σ*` with start/end anchors allowed at the outer boundaries.

## Symfony Bridge Analyzers

**Routes (`regex:routes`)**
- **Semantics:** Analyzes compiled Symfony route regexes with automata comparisons.
- **Guarantee:** Sound for supported regex subset and route conditions considered in analysis.
- **Fallbacks:** Unsupported flags or host requirements are reported and skipped; route conditions are treated as unknown.

**Security Access Control (`regex:security`)**
- **Semantics:** Models access_control as search semantics (`Σ* L Σ*`) to match `preg_match` behavior.
- **Guarantee:** Sound for supported regex subset and listed rule constraints.
- **Fallbacks:** `allow_if`, IP constraints, and request matchers are reported in notes and excluded from automata checks.

**Firewall ReDoS (`regex:security`)**
- **Semantics:** Uses ReDoS heuristics on firewall patterns.
- **Guarantee:** Best-effort; reports above-threshold findings.
- **Fallbacks:** `request_matcher` firewalls are skipped with a reason.
