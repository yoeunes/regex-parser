# Regex Logic & Automata Solver

## The Concept

RegexParser can transform a regex into a deterministic finite automaton (DFA). That means a pattern becomes a **set of strings**, and comparisons become precise set operations instead of guesswork.

Verified example (intersection):

```bash
bin/regex compare '/edit/' '/[a-z]+/'
```

```
  FAIL  Conflict detected!

  Example:           "edit"
```

## Use Case 1: Route Conflict Detection (The "Intersection" Problem)

Scenario: Route A is `/order/\d+` and Route B is `/order/[a-z0-9]+`. They look different, but can they match the same string?

Command:

```bash
bin/regex compare '/order/\d+/' '/order/[a-z0-9]+/'
```

Result:

```
  FAIL  Conflict detected!

  Example:           "order/0"
```

Interpretation: conflict detected on input `order/0`.

Educational value: **Intersection** asks "is there a string that matches BOTH patterns?" If the answer is yes, your routes can shadow each other.

## Use Case 2: Security Audits (The "Subset" Problem)

Scenario: A security policy allows `[a-zA-Z0-9]+`. A developer writes `\w+`, which includes `_` and might be forbidden.

Command:

```bash
bin/regex compare '/\w+/' '/[a-zA-Z0-9]+/' --method=subset
```

Result:

```
  FAIL  Pattern 1 allows strings that Pattern 2 forbids.

  Counter-example:  "_"
```

Interpretation: FAIL. Counter-example: `_`.

Educational value: **Subset** asks "does pattern 1 allow ONLY what pattern 2 allows?" If not, the counter-example shows the exact violation.

## Use Case 3: Safe Refactoring (The "Equivalence" Problem)

Scenario: You want to simplify `[0-9]` to `\d` and prove it is safe.

Command:

```bash
bin/regex compare '/[0-9]+/' '/\d+/' --method=equivalence
```

Result:

```
  PASS  Patterns are mathematically equivalent.
```

Educational value: **Equivalence** asks "do these patterns accept the exact same set of strings?"

## How it Works (Under the Hood)

RegexParser follows a formal pipeline:

1. AST -> NFA (Thompson construction)
2. NFA -> DFA (powerset construction)
3. BFS traversal over product DFA to find overlap or counter-examples

The BFS step guarantees the **shortest possible counter-example** when one exists.

## Minimization Strategies and Complexity

RegexParser minimizes DFAs before comparison to shrink the product graph and keep searches fast.

- **Hopcroft worklist** (default): `O(|Σ_eff| · n log n)`
- **Moore partition refinement**: `O(|Σ_eff| · n^2)`

**Effective alphabet (`Σ_eff`)** means only the symbols that actually appear as DFA transitions are iterated.
This avoids scanning a full Unicode range and keeps minimization proportional to real symbols. For standard
byte-based DFAs, `Σ_eff` is at most 256 symbols and often much smaller.

### Selecting a Strategy

CLI:

```bash
bin/regex compare '/foo/' '/bar/' --minimizer=moore
```

Symfony bundle:

```yaml
# config/packages/regex_parser.yaml
regex_parser:
  automata:
    minimization_algorithm: hopcroft
```

You can also override it per command:

```bash
bin/console regex:compare '/foo/' '/bar/' --minimizer=moore
```

## Limitations

- Supports the **regular subset** of PCRE only (no lookarounds, no backreferences, no recursion).
- Operates on **UTF-8 bytes** (alphabet 0-255), not full Unicode code points.
