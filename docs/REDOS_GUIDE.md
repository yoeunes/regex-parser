# ReDoS Guide: Regular Expression Denial of Service

Understanding ReDoS (Regular Expression Denial of Service) is critical for building secure applications that accept user-provided patterns or process untrusted input with regex.

## What is ReDoS?

ReDoS occurs when a maliciously crafted input causes a regex engine to spend exponential time trying to find a match. This happens because most regex engines use **backtracking** — they try one path, fail, then back up and try another.

### The Traffic Jam Analogy

Imagine a one-way road with a single lane that has several junctions:

```
┌─────────────────────────────────────────────────────────────┐
│              BACKTRACKING TRAFFIC JAM                       │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Pattern: /(a+)+b/                                          │
│                                                             │
│  Junction 1: How many 'a's for the inner (a+)?              │
│              ↓                                              │
│              Can match: a, aa, aaa, aaaa, aaaaa...          │
│              ↓                                              │
│  Junction 2: How many groups for the outer (...)+?          │
│              ↓                                              │
│              Can match: 1, 2, 3, 4, 5... times              │
│              ↓                                              │
│  Input: a a a a a a a a a a !                               │
│              (10 'a's followed by '!')                      │
│              ↓                                              │
│  Engine tries:                                              │
│    Path 1: Inner (a+) matches all 10, outer + matches 1     │
│            → 'b' fails at position 10                       │
│            → Backtrack: outer + now matches 0               │
│            → 'b' still fails                                │
│            → Backtrack to inner (a+)                        │
│            → Inner now matches 9                            │
│            → Outer + tries 2                                │
│            → 'b' still fails                                │
│            → Continue backtracking...                       │
│                                                             │
│  Result: Exponential combinations!                          │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Why Backtracking is Dangerous

```
┌─────────────────────────────────────────────────────────────┐
│              EXPLOSIVE GROWTH                               │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Input Length    │  Backtracking Paths                      │
│  ────────────────┼───────────────────────────────────────── │
│  5  'a's         │  ~5 combinations                         │
│  10 'a's         │  ~55 combinations                        │
│  15 'a's         │  ~120 combinations                       │
│  20 'a's         │  ~210 combinations                       │
│  25 'a's         │  ~325 combinations                       │
│                                                             │
│  Pattern: /(a+)+c/ with input "aaaaa...ac"                  │
│  Each additional 'a' adds more combinations!                │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

## Anatomy of a Vulnerable Pattern

### Vulnerable Pattern #1: Nested Quantifiers

```
Pattern: /(a+)+b/
```

This pattern is vulnerable because:

1. **Inner `(a+)`** can match 1, 2, 3... 'a's
2. **Outer `(...)+`** can repeat the group 1, 2, 3... times
3. When `'b'` fails, the engine explores ALL combinations

```
┌─────────────────────────────────────────────────────────────┐
│              NESTED QUANTIFIER EXPLOSION                    │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Input: a a a a a !                                         │
│          (5 'a's, then '!')                                 │
│                                                             │
│  The engine must try:                                       │
│                                                             │
│  Outer +, 1x │ Inner (a+) matches:                          │
│  ────────────┼───────────────────────────────────────────── │
│  1x          │ aaaa                                         │
│              │ aaaa a                                       │
│              │ aaaa a a                                     │
│              │ aaaa a a a                                   │
│              │ aaaa a a a a                                 │
│              └──────────────────────────────────────────────┘
│  Outer +, 2x │ Inner (a+) matches:                          │
│  ────────────┼───────────────────────────────────────────── │
│  2x          │ aaa aa                                       │
│              │ aaa a aa                                     │
│              │ aaa a a aa                                   │
│              └───────────────────────────────────────────────
│  ...         │ ...                                          │
│                                                             │
│  Total paths grow exponentially with input length!          │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Vulnerable Pattern #2: Overlapping Alternations

```
Pattern: /(a|aa)+b/
```

```
┌─────────────────────────────────────────────────────────────┐
│              OVERLAPPING ALTERNATIVES                       │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Input: a a a a a !                                         │
│          (5 'a's, then '!')                                 │
│                                                             │
│  The (a|aa)+ can match in many ways:                        │
│                                                             │
│  Path 1: a + a + a + a + a = "aaaaa"                        │
│  Path 2: aa + a + a + a = "aaaaa"                           │
│  Path 3: a + aa + a + a = "aaaaa"                           │
│  Path 4: a + a + aa + a = "aaaaa"                           │
│  Path 5: a + a + a + aa = "aaaaa"                           │
│  Path 6: aa + aa + a = "aaaaa"                              │
│  ...                                                        │
│                                                             │
│  When 'b' fails, engine tries ALL paths!                    │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Vulnerable Pattern #3: Unanchored Patterns

```
Pattern: /^(a+)+b/
```

Even with `^` anchor, the nested quantifiers inside can still explode.

## How RegexParser Detects ReDoS

RegexParser uses **static analysis** on the AST — it examines the pattern structure without executing it. This is safe, fast, and runs in CI pipelines.

### Analysis Process

```
┌─────────────────────────────────────────────────────────────┐
│              STATIC ANALYSIS FLOW                           │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  1. Parse pattern into AST                                  │
│     /foo/ ──► RegexNode                                     │
│                └─► SequenceNode                             │
│                    └─► [LiteralNode("foo")]                 │
│                                                             │
│  2. ReDoS visitor analyzes structure                        │
│     - Detects quantifier nodes                              │
│     - Checks nesting depth                                  │
│     - Identifies alternations inside repetition             │
│                                                             │
│  3. Classifies risk level                                   │
│     ┌─────────────────────────────────────────────────┐     │
│     │ SAFE     │ No exponential backtracking          │     │
│     │ LOW      │ Minimal risk, small input needed     │     │
│     │ MEDIUM   │ Requires specific input pattern      │     │
│     │ CRITICAL │ Easily triggered, exponential growth │     │
│     └─────────────────────────────────────────────────┘     │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### What the Analyzer Looks For

| Pattern Type                        | Risk Level | Example               |
|-------------------------------------|------------|-----------------------|
| Nested unbounded quantifiers        | Critical   | `/(a+)+b/`            |
| Alternation inside repetition       | Critical   | `/(a                  |b)+c/` |
| Overlapping patterns in alternation | High       | `/(a                  |aa)+b/` |
| Backreferences with repetition      | Medium     | `/(\w+)\1+/`          |
| Simple bounded patterns             | Low        | `/[a-z]{1,10}/`       |
| Possessive/atomic patterns          | Safe       | `/a++b/`, `/(?>a+)b/` |

### PHP Example: Detecting Vulnerable Patterns

```php
use RegexParser\Regex;

// Check if a pattern is vulnerable
$pattern = '/(a+)+b/';
$analysis = Regex::create()->redos($pattern);

echo "Severity: " . $analysis->severity->value;      // Output: critical
echo "Confidence: " . $analysis->confidence->value;  // Output: high

// Check a safe pattern
$safePattern = '/a++b/';
$safeAnalysis = Regex::create()->redos($safePattern);

echo "Safe Severity: " . $safeAnalysis->severity->value;  // Output: safe
```

## Fixing Vulnerable Patterns

### Solution 1: Possessive Quantifiers

```
Vulnerable: /(a+)+b/
Fixed:      /a++b/
```

**What possessive quantifiers do:**
- They match as much as possible and **never give back**
- Once `a++` matches "aaa", it won't backtrack

```
┌─────────────────────────────────────────────────────────────┐
│              POSSESSIVE VS GREEDY                           │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Pattern: /(a+)+b/  (greedy, vulnerable)                    │
│                                                             │
│  Input: "aaa!b"                                             │
│                                                             │
│  Step 1: (a+) matches "aaa"                                 │
│  Step 2: + repeats 1 time                                   │
│  Step 3: 'b' fails at '!'                                   │
│  Step 4: BACKTRACK! Try different splits                    │
│          - (a+) matches "aa", + repeats 2                   │
│          - (a+) matches "a", + repeats 3                    │
│          - ...                                              │
│  Result: Exponential!                                       │
│                                                             │
│  ─────────────────────────────────────────────────────────  │
│                                                             │
│  Pattern: /a++b/  (possessive, safe)                        │
│                                                             │
│  Input: "aaa!b"                                             │
│                                                             │
│  Step 1: a++ matches "aaa"                                  │
│  Step 2: 'b' fails at '!'                                   │
│  Step 3: NO BACKTRACK! Possessive gives nothing back        │
│  Result: Immediate failure, constant time!                  │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Solution 2: Atomic Groups

```
Vulnerable: /(a+)+b/
Fixed:      /(?>a+)b/
```

**What atomic groups do:**
- Like possessive quantifiers but works with any pattern
- Once `(?>a+)` matches, the engine "commits" to that match

### Solution 3: Simplify the Pattern

Often, nested quantifiers are redundant:

```
Vulnerable: /(a+)+b/
Simplified: /a+b/
```

Why? `(a+)+` means "one or more of (one or more a's)", which is just "one or more a's".

```
┌─────────────────────────────────────────────────────────────┐
│              WHEN SIMPLIFICATION WORKS                      │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Pattern: /(a+)+b/                                          │
│  Meaning: One or more groups, each with one or more 'a's    │
│           followed by 'b'                                   │
│                                                             │
│  Simplified: /a+b/                                          │
│  Meaning: One or more 'a's followed by 'b'                  │
│                                                             │
│  These are EQUIVALENT for most purposes!                    │
│                                                             │
│  ─────────────────────────────────────────────────────────  │
│                                                             │
│  Pattern: /(ab)+c/                                          │
│  Simplified: /abc/                                          │
│  Reason: + repeats the entire group, but group is "ab"      │
│          So (ab)+ = "ab", "abab", "ababab"...               │
│          Which is equivalent to "a" followed by "b"+ "c"    │
│          = "abc"                                            │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Solution 4: Avoid Alternation in Repetition

```
Vulnerable: /(a|b)+c/
Fixed:      /[ab]+c/
```

Character classes are more efficient than alternation and don't backtrack internally.

### Solution 5: Use Atomic Groups for Complex Cases

```php
use RegexParser\Regex;

// Before: vulnerable to ReDoS
$vulnerable = '/(\w+\d+)+/';

// After: protected with atomic groups
$safe = '/(?>\w+\d+)+/';

$vulnAnalysis = Regex::create()->redos($vulnerable);
$safeAnalysis = Regex::create()->redos($safe);

echo "Vulnerable: " . $vulnAnalysis->severity->value;  // critical
echo "Safe: " . $safeAnalysis->severity->value;        // safe
```

## Common Vulnerable Patterns to Avoid

```
┌─────────────────────────────────────────────────────────────┐
│              DANGEROUS PATTERNS                             │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌─────────────────────────────────────────────────────┐    │
│  │ BAD                     │ GOOD                      │    │
│  ├─────────────────────────────────────────────────────┤    │
│  │ (a+)+                   │ a++ or (?>a+)             │    │
│  │ (a|b)+                  │ [ab]+                     │    │
│  │ (a|aa)+                 │ a+                        │    │
│  │ (\d+)+                  │ \d++ or \d{1,10}          │    │
│  │ (.+)+                   │ .++ or .{1,100}           │    │
│  │ (.*)*(a|$)              │ a+$                       │    │
│  │ (a?){100}               │ a{0,100}                  │    │
│  │ (\w+\d+)+               │ (?>\w+\d+)+               │    │
│  └─────────────────────────────────────────────────────┘    │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

## Defense-in-Depth Strategies

### 1. Input Length Limits

```php
// Reject inputs that are too long before matching
$maxLength = 1000;
if (strlen($input) > $maxLength) {
    throw new \InvalidArgumentException('Input too long');
}
```

### 2. Engine Safeguards

```php
// PCRE provides backtrack limits
// Set via php.ini or ini_set()
ini_set('pcre.backtrack_limit', '1000000');  // 1 million

// For preg_match_with_limit() (if available)
$result = preg_match_with_limit($pattern, $input, $matches, 's', 10000);
```

### 3. Timeout-Based Execution

```php
// Set a timeout for regex operations
set_time_limit(5);  // 5 seconds

// For long-running operations, use a signal handler
pcntl_async_signals(true);
pcntl_signal(SIGALRM, function() {
    throw new \RuntimeException('Regex timeout');
});
alarm(5);
```

### 4. Use RegexParser's Static Analysis

```php
use RegexParser\Regex;

class PatternValidator
{
    public function validatePattern(string $pattern): bool
    {
        $analysis = Regex::create()->redos($pattern);
        
        if ($analysis->severity->value !== 'safe') {
            throw new \InvalidArgumentException(
                "Pattern may be vulnerable to ReDoS: {$analysis->severity->value}"
            );
        }
        
        return true;
    }
}
```

### 5. Whitelist Patterns When Possible

```php
// Instead of accepting any user pattern
// Whitelist allowed patterns
$allowedPatterns = [
    '/^[a-z]+$/',
    '/^\d{3}-\d{4}$/',
    '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
];

if (!in_array($userPattern, $allowedPatterns)) {
    throw new \InvalidArgumentException('Pattern not allowed');
}
```

## ReDoS in Production: Real-World Examples

### Example 1: Input Validation Endpoint

```php
// VULNERABLE CODE
function validateEmail(string $email): bool
{
    // User-provided pattern (very dangerous!)
    $pattern = $_POST['validation_pattern'];
    return preg_match($pattern, $email) === 1;
}

// SECURE CODE
function validateEmail(string $email): bool
{
    // Use whitelisted pattern
    $pattern = '/^[a-z0-9]([a-z0-9._-]*[a-z0-9])?@[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/i';
    return preg_match($pattern, $email) === 1;
}
```

### Example 2: Log Search

```php
// VULNERABLE CODE
function searchLogs(string $query): array
{
    // User query converted to pattern
    $pattern = "/{$query}/";  // User can inject (a+)+!
    return preg_grep($pattern, $this->logs);
}

// SECURE CODE
function searchLogs(string $query): array
{
    // Escape special regex characters
    $escaped = preg_quote($query, '/');
    $pattern = "/{$escaped}/";
    return preg_grep($pattern, $this->logs);
}
```

### Example 3: Template Engine

```php
// VULNERABLE CODE
function renderTemplate(string $template, array $data): string
{
    // Replace {{variable}} with values
    $pattern = '/\{\{(.+?)\}\}/';  // Greedy .+? can cause issues
    return preg_replace($pattern, fn($m) => $data[$m[1]] ?? '', $template);
}

// SECURE CODE
function renderTemplate(string $template, array $data): string
{
    // Use non-greedy with character restriction
    $pattern = '/\{\{([a-zA-Z_][a-zA-Z0-9_]*)\}\}/';
    return preg_replace_callback($pattern, fn($m) => $data[$m[1]] ?? '', $template);
}
```

## Testing for ReDoS

### Using RegexParser

```php
use RegexParser\Regex;

class ReDoSTester
{
    public function analyze(string $pattern): array
    {
        $analysis = Regex::create()->redos($pattern);
        
        return [
            'pattern' => $pattern,
            'severity' => $analysis->severity->value,
            'confidence' => $analysis->confidence->value,
            'description' => $this->getDescription($analysis),
        ];
    }
    
    private function getDescription($analysis): string
    {
        return match ($analysis->severity->value) {
            'safe' => 'Pattern appears safe from ReDoS attacks',
            'low' => 'Pattern has minimal ReDoS risk',
            'medium' => 'Pattern may have ReDoS risk with specific inputs',
            'critical' => 'Pattern is vulnerable to ReDoS attacks!',
        };
    }
}

// Test
$tester = new ReDoSTester();
print_r($tester->analyze('/(a+)+b/'));
/*
Array
(
    [pattern] => /(a+)+b/
    [severity] => critical
    [confidence] => high
    [description] => Pattern is vulnerable to ReDoS attacks!
)
*/
```

### Fuzz Testing

```php
function fuzzTestPattern(string $pattern, array $attackInputs): array
{
    $results = [];
    
    foreach ($attackInputs as $input) {
        $start = microtime(true);
        $match = @preg_match($pattern, $input);
        $duration = microtime(true) - $start;
        
        $results[] = [
            'input' => $input,
            'duration' => $duration,
            'timeout' => $duration > 1.0,  // 1 second threshold
        ];
    }
    
    return $results;
}

// Common ReDoS test inputs
$attackInputs = [
    str_repeat('a', 10) . '!',
    str_repeat('a', 15) . '!',
    str_repeat('a', 20) . '!',
    str_repeat('a', 25) . '!',
    str_repeat('ab', 10) . 'c!',
    str_repeat('aaab', 10) . 'c!',
];

$results = fuzzTestPattern('/(a+)+b/', $attackInputs);
```

## Quick Reference: Safe Patterns

```
┌─────────────────────────────────────────────────────────────┐
│              SAFE PATTERN QUICK REFERENCE                   │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Instead of:          Use:                                  │
│  ─────────────────────────────────────────────────────────  │
│  (a+)+               a++ or (?>a+)                          │
│  (a|b)+              [ab]+                                  │
│  (.*)                .*? (lazy) or .++ (possessive)         │
│  (.+)+               .++ or .{1,100}                        │
│  \d+                 \d++ or \d{1,10}                       │
│  \w+                 \w++ or \w{1,20}                       │
│  (?:a|b|c)+          [abc]+                                 │
│  a?a?a?...           a{0,100}                               │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

## Summary

| Concept             | Key Point                                             |
|---------------------|-------------------------------------------------------|
| ReDoS               | Exponential time due to backtracking                  |
| Vulnerable Patterns | Nested quantifiers, alternation in repetition         |
| Detection           | Static AST analysis (RegexParser)                     |
| Fixes               | Possessive quantifiers, atomic groups, simplification |
| Defense             | Input limits, engine safeguards, pattern whitelisting |
| Testing             | Fuzz with increasing input lengths                    |

---

Previous: [Reference](reference.md) | Next: [Cookbook](COOKBOOK.md)
