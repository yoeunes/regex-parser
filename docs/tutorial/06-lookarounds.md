# Chapter 6: Lookarounds and Assertions

> **Goal:** Match based on what comes before or after, without including it in the match.

---

## What are Lookarounds?

**Lookarounds** check what's around a position **without matching it**. Think of them like a **security camera** - they observe but don't take:

```
Text: "price is $100"

Pattern: /(?<=\$)\d+/
         └────┬────┘ └┘
         Lookbehind  Digits
         
         Matches: "100"
         Lookbehind: "Must be preceded by $"
         (The $ is NOT included in the match!)
```

### Real-World Analogy

| Lookaround | Analogy                       | Example                       |
|------------|-------------------------------|-------------------------------|
| `(?=...)`  | "Make sure X is ahead"        | Drive if "gas station ahead"  |
| `(?!...)`  | "Make sure X is NOT ahead"    | Go if "no construction ahead" |
| `(?<=...)` | "Check rearview mirror for X" | Turn if "sign behind you"     |
| `(?<!...)` | "Check X is NOT behind"       | Drive if "no car behind"      |

---

## Types of Lookarounds

### 1. Lookahead: What's Ahead?

#### Positive Lookahead `(?=...)`

Match only if **followed by** something:

```php
// Match digits followed by "USD"
preg_match('/\d+(?=USD)/', '100USD', $matches);
echo $matches[0];  // "100" (USD is not included!)

// Match words followed by a number
preg_match('/\w+(?=\d)/', 'test123', $matches);
echo $matches[0];  // "test"
```

#### Negative Lookahead `(?!...)`

Match only if **NOT followed by** something:

```php
// Match digits NOT followed by "USD"
preg_match('/\d+(?!USD)/', '100EUR', $matches);
echo $matches[0];  // "100"

// Match words NOT followed by a number
preg_match('/\w+(?!\d)/', 'test', $matches);
echo $matches[0];  // "test" (test is not followed by digit)
```

### 2. Lookbehind: What's Behind?

#### Positive Lookbehind `(?<=...)`

Match only if **preceded by** something:

```php
// Match "100" preceded by "$"
preg_match('/(?<=\$)\d+/', 'price is $100', $matches);
echo $matches[0];  // "100"

// Match words preceded by "@"
preg_match('/(?<=@)\w+/', 'email: @username', $matches);
echo $matches[0];  // "username"
```

#### Negative Lookbehind `(?<!...)`

Match only if **NOT preceded by** something:

```php
// Match "100" NOT preceded by "$"
preg_match('/(?<!\$)\d{3}/', 'cost: 100', $matches);
echo $matches[0];  // "100"
```

---

## ASCII Diagram: Lookaround Behavior

```
Text: "price is $100"

Pattern: /(?<=\$)\d+/

Position analysis:
  p r i c e   i s   $ 1 0 0
              ^    ^  ^  ^  ^
              │    │  │  │  │
              │    │  │  │  └─ Position 9: Digit, preceded by 0 ✓
              │    │  │  └──── Position 8: Digit, preceded by $ ✓
              │    │  └─────── Position 7: $, not a digit
              │    └────────── Position 6: Space
              └─────────────── Position 0-5: Not relevant

Matches: "100" at positions 7-9
         The $ at position 7 is CHECKED but NOT MATCHED
```

---

## Lookaround Examples

### Validate Without Matching

```php
// Check password has digit ahead (without including it)
preg_match('/.+(?=\d)/', 'password1', $matches);
echo $matches[0];  // "password" (digit not included)

// Validate email format
preg_match('/^[a-z]+(?=@)/', 'user@example', $matches);
echo $matches[0];  // "user" (checks for @ ahead)
```

### Password Validation

```php
// Password with at least one digit ahead (not included)
$password = 'secure123';
if (preg_match('/.{6,}(?=\d)/', $password)) {
    echo "Valid: has digits ahead";
}

// Negative lookahead: no spaces
if (!preg_match('/\s/', $password)) {
    echo "Valid: no spaces";
}
```

### File Extension Check

```php
// Match filename before extension
preg_match('/^.+(?=\.(?:jpg|png|gif)$)/', 'image.jpg', $matches);
echo $matches[0];  // "image" (extension not included)
```

---

## Important: Lookbehind Limitations

In PCRE, lookbehind must have a **bounded maximum length**:

```php
// ❌ Invalid: Unbounded lookbehind (infinite possible)
preg_match('/(?<=a+)b/', 'aaab');  // ERROR!

// ✅ Valid: Bounded lookbehind (max 3 characters)
preg_match('/(?<=a{1,3})b/', 'aaab');  // OK
```

### Variable-Length Lookbehind Detection

```php
use RegexParser\Regex;

$regex = Regex::create(['runtime_pcre_validation' => true]);
$result = $regex->validate('/(?<=a+)b/');

if (!$result->isValid()) {
    echo $result->getErrorMessage();
    // Output: "Variable-length lookbehind is not supported in PCRE"
}
```

---

## Good Patterns vs Bad Patterns

### Good: Proper Lookarounds

```php
// Match word before period (period not included)
/\w+(?=\.)/

// Match number preceded by $ (dollar not included)
/(?<=\$)\d+/

// Validate without consuming
'/^(?=.*[A-Z]).{8,}$/'  // At least 8 chars, has uppercase
```

### Bad: Invalid or Confusing

```php
// ❌ Variable-length lookbehind (invalid in PCRE)
/(?<=a+)b/

// ❌ Using lookbehind when lookahead is clearer
// Match "foo" after "bar" - lookbehind is harder
/(?<=bar)foo/

// ✅ Match "bar" before "foo" - lookahead is clearer
/(?=bar)foo/
```

---

## Exercises

### Exercise 1: Predict Matches

For each pattern, what matches?

1. `/\w+(?=\d)/` on "test123"
2. `/(?<=\$)\d+/` on "price $50"
3. `/\w+(?!\d)/` on "hello"

```php
// Answers:
// 1. "test" (word followed by digit)
// 2. "50" (digits preceded by $)
// 3. "hello" (word not followed by digit)
```

### Exercise 2: Write Patterns

Write patterns that:

1. Match "test" only if followed by "123"
2. Match "end" only if preceded by "the "
3. Match a word that's NOT followed by a number

```php
// Solution 1
$pattern1 = '/test(?=123)/';

// Solution 2
$pattern2 = '/(?<=the )end/';

// Solution 3
$pattern3 = '/\w+(?!\d)/';
```

### Exercise 3: Validate Lookbehind

```php
use RegexParser\Regex;

$regex = Regex::create(['runtime_pcre_validation' => true]);

$result = $regex->validate('/(?<=a{1,3})b/');
echo $pattern . ": " . ($result->isValid() ? "Valid" : "Invalid") . "\n";

$result = $regex->validate('/(?<=a+)b/');
echo $pattern . ": " . ($result->isValid() ? "Valid" : "Invalid") . "\n";
```

---

## Key Takeaways

1. **Lookarounds** check context without matching
2. **Lookahead** `(?=...)` checks what's ahead
3. **Negative lookahead** `(?!...)` checks what's NOT ahead
4. **Lookbehind** `(?<=...)` checks what's behind
5. **Negative lookbehind** `(?<!...)` checks what's NOT behind
6. Lookarounds are **zero-width** (don't consume characters)
7. **PCRE requires bounded lookbehind** (max length)

---

## Common Errors

### Error: Variable-Length Lookbehind

```php
// ❌ Invalid in PCRE
preg_match('/(?<=a+)b/', 'aaab');  // ERROR!

// ✅ Valid: Specify bounds
preg_match('/(?<=a{1,10})b/', 'aaab');  // OK
```

### Error: Forgetting Lookaround is Zero-Width

```php
// What gets matched?
preg_match('/(?=\d)\d/', '5', $matches);
echo $matches[0];  // "5" (same position checked twice!)
```

### Error: Using Lookbehind When Lookahead Is Better

```php
// Checking if "foo" comes after "bar"
// ❌ Unnatural: Lookbehind reads backward
/(?<=bar)foo/

// ✅ Better: Lookahead reads forward
/(?=bar)foo/
```

---

## You're Ready!

You now understand:
- All four lookaround types
- Zero-width behavior
- PCRE limitations
- Common pitfalls and fixes

**Next:** [Chapter 7: Backreferences and Recursion](07-backreferences-recursion.md)

---

<p align="center">
  <b>Chapter 6 Complete! →</b>
</p>

---

Previous: [Groups & Alternation](05-groups-alternation.md) | Next: [Backreferences](07-backreferences-recursion.md)
