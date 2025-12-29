# ReDoS Guide: How Backtracking Bites

ReDoS (Regular Expression Denial of Service) happens when a pattern makes the engine explore too many paths. RegexParser detects those patterns before they reach production.

> We do not just say "dangerous". We point to the exact structure that causes the explosion.

## The Backtracking Mental Model

Imagine the engine walking a tree of choices. Each ambiguous step multiplies the work.

```
Pattern: /(a+)+$/
Input:   aaaaaaaaaaaaa!

Engine tries to match:
  (a+)+ -> [aaaaaaaaaaaa] then fails at '!'
              ^ backtrack
  (a+)+ -> [aaaaaaaaaaa][a] then fails at '!'
                  ^ backtrack
  (a+)+ -> [aaaaaaaaaa][aa] then fails at '!'
                      ... exponential paths
```

That is catastrophic backtracking. It is fast to write, slow to run.

## How RegexParser Detects It

We parse the pattern into an AST, then analyze its structure. Nested quantifiers and overlapping branches show up clearly in the tree.

```php
use RegexParser\Regex;

$analysis = Regex::create()->redos('/(a+)+$/');

echo $analysis->severity->value;   // critical
echo $analysis->confidence->value; // high
```

## Severity and Confidence

| Severity | Meaning |
| --- | --- |
| `safe` | No known risk |
| `low` | Low risk patterns |
| `medium` | Potentially risky |
| `high` | Likely problematic |
| `critical` | High confidence ReDoS |
| `unknown` | Not enough signal |

Confidence indicates how certain the analysis is (`low`, `medium`, `high`).

## Use the CLI

```bash
vendor/bin/regex analyze '/(a+)+$/'
vendor/bin/regex debug '/(a+)+$/'
```

`debug` prints hotspots and a heatmap to show which parts of the input trigger backtracking.

## Common Risk Patterns (And Fixes)

### 1. Nested Quantifiers

```
Bad:  /(a+)+$/
Fix:  /a+$/
Fix:  /(?>a+)+$/  (atomic group)
Fix:  /(a++)+$/   (possessive quantifier)
```

### 2. Overlapping Alternation

```
Bad:  /(a|aa)+b/
Fix:  /(?>aa|a)+b/
Fix:  /a+b/
```

### 3. Dot-Star in Repetition

```
Bad:  /(?:.*)+x/
Fix:  /(?:.*+)x/
Fix:  /[^x]*x/
```

### 4. Optional Prefix + Repetition

```
Bad:  /(a?)+$/
Fix:  /a*$/
```

## Mitigation Toolbox

| Technique | Example | Why It Helps |
| --- | --- | --- |
| Atomic groups | `(?>a+)` | Prevents backtracking inside the group |
| Possessive quantifiers | `a++` | Never backtracks once matched |
| Anchoring | `^...$` | Reduces search space |
| Narrow classes | `[^x]*` | Avoids full wildcard matching |
| Input limits | App-level limit | Caps worst-case work |

## A Safe Workflow

1. Validate syntax.
2. Run ReDoS analysis.
3. Apply mitigations.

```php
$regex = Regex::create(['runtime_pcre_validation' => true]);

$validation = $regex->validate('/(a+)+$/');
$analysis = $regex->redos('/(a+)+$/');
```

> ReDoS analysis is structural. If you change structure, re-run the check.

---

Previous: `COOKBOOK.md` | Next: `ARCHITECTURE.md`
