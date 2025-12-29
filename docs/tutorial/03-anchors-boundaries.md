# Chapter 3: Anchors and Boundaries

> **Goal:** Control where your pattern matches using start/end markers and word boundaries.

---

## 🤔 What are Anchors?

**Anchors** don't match characters - they match **positions** in the text. Think of them like **bookmarks** in a document:

```
Text: "hello world"

Pattern: /^hello/
         ^^
         Anchors to the START of the string

Pattern: /world$/
              ^^
              Anchors to the END of the string
```

### Real-World Analogy

| Anchor | Analogy           | Matches                   |
|--------|-------------------|---------------------------|
| `^`    | Start of the line | Beginning of text         |
| `$`    | End of the line   | End of text               |
| `\b`   | Fence post        | Between word and non-word |

---

## 🎯 Start and End Anchors

### The Caret `^` - Start of String

Matches the beginning of the input:

```php
// Does the string START with "error"?
preg_match('/^error/i', 'Error occurred');     // ✅ Matches
preg_match('/^error/i', 'File error occurred'); // ❌ No match (not at start)
```

### The Dollar Sign `$` - End of String

Matches the end of the input:

```php
// Does the string END with "error"?
preg_match('/error$/i', 'File error');         // ✅ Matches
preg_match('/error$/i', 'error in file');      // ❌ No match (not at end)
```

### Together: Exact Match

Combine `^` and `$` to match the **entire** string:

```php
// Is the string EXACTLY "hello"?
preg_match('/^hello$/', 'hello');    // ✅ Matches
preg_match('/^hello$/', 'hello!');   // ❌ No match (has '!')
preg_match('/^hello$/', 'say hello'); // ❌ No match (not exact)
```

### ASCII Diagram

```
Text: "error occurred"

Pattern: /^error/
         ^^^^^^
         Matches at position 0 (start)
         
Pattern: /error$/
              ^^^^^
              No match (error is not at end)

Pattern: /^error$/
         ^^^^^^^^
         No match (entire string is not "error")
```

---

## 🔒 Absolute Anchors

### `\A` - Start of Subject (Always)

Unlike `^`, `\A` never matches at line boundaries (even with `/m` flag):

```php
// Multiline text
$text = "line1\nline2\nline3";

// ^ matches at start of ANY line with /m
preg_match('/^line/m', $text);  // ✅ Matches (line1)

// \A ONLY matches at the absolute start
preg_match('/\Aline/m', $text); // ✅ Matches (line1 only)
```

### `\z` - End of Subject (Always)

Unlike `$`, `\z` only matches the absolute end:

```php
$text = "line1\nline2\nline3";

// $ matches at end of ANY line with /m
preg_match('/line3$/m', $text);  // ✅ Matches

// \z ONLY matches at the absolute end
preg_match('/line3\z/m', $text); // ✅ Matches
preg_match('/line2\z/m', $text); // ❌ No match (not at absolute end)
```

### When to Use Absolute Anchors

| Anchor | Use When                           |
|--------|------------------------------------|
| `^`    | You want `/m` to affect behavior   |
| `$`    | You want `/m` to affect behavior   |
| `\A`   | Always need start of entire string |
| `\z`   | Always need end of entire string   |

---

## 📍 Word Boundaries `\b`

A `\b` matches the **boundary between a word character** (`\w`) **and a non-word character** (`\W`):

```php
// Match whole word "cat"
preg_match('/\bcat\b/', 'category');    // ❌ No match (cat is part of word)
preg_match('/\bcat\b/', 'the cat sat'); // ✅ Matches (standalone word)
preg_match('/\bcat\b/', 'catastrophe'); // ❌ No match (starts word)
```

### Word Boundary Examples

| Pattern     | Text              | Match? | Why                      |
|-------------|-------------------|--------|--------------------------|
| `/\bcat\b/` | "the **cat** sat" | ✅      | Boundaries on both sides |
| `/\bcat\b/` | "**cat**astrophe" | ❌      | No boundary after cat    |
| `/\bcat\b/` | "wild**cat**"     | ❌      | No boundary before cat   |
| `/\bcat/`   | "**cat**egory"    | ✅      | Boundary before cat      |
| `/cat\b/`   | "wild**cat**"     | ✅      | Boundary after cat       |

### ASCII Diagram: Word Boundaries

```
Text: "the cat sat"

Word characters: \w = [a-zA-Z0-9_]
Non-word characters: \W = everything else

Positions:
  t h e   c a t   s a t
  ↑ ↑ ↑   ↑ ↑ ↑   ↑ ↑ ↑
  │ │ │   │ │ │   │ │ │
  │ │ │   │ │ │   │ │ └── \b (t is word, end is word) → NO BOUNDARY
  │ │ │   │ │ │   │ │
  │ │ │   │ │ │   │ └──── \b (t is word, space is not) → BOUNDARY ✓
  │ │ │   │ │ │   │
  │ │ │   │ │ │   └────── \b (space is not, s is word) → BOUNDARY ✓
  │ │ │   │ │ │
  │ │ │   │ │ └────────── \b (t is word, space is not) → BOUNDARY ✓
  │ │ │   │ │           Matches "cat" here! ✓
  │ │ │   │ │
  │ │ │   │ └──────────── \b (a is word, t is word) → NO BOUNDARY
  │ │ │   │
  │ │ │   └────────────── \b (space is not, c is word) → BOUNDARY ✓
  │ │ │
  │ │ └────────────────── \b (e is word, space is not) → BOUNDARY ✓
  │ │
  │ └──────────────────── \b (h is word, e is word) → NO BOUNDARY
  │
  └────────────────────── \b (start is not, t is word) → BOUNDARY ✓
```

---

## 🔧 Combining Anchors with Other Patterns

### Anchor Use Cases

| Pattern            | Use Case                    |
|--------------------|-----------------------------|
| `/^[0-9]+$/`       | String is all digits        |
| `/^error:/i`       | String starts with "error:" |
| `/\.txt$/`         | String ends with ".txt"     |
| `/\b\w+\b/`        | Whole words only            |
| `/^\w+@\w+\.\w+$/` | Simple email format         |

### Examples

```php
// Check if string is a number
preg_match('/^\d+$/', '12345');     // ✅ Matches
preg_match('/^\d+$/', '123-45');    // ❌ No match (has hyphen)

// Validate file extension
preg_match('/\.(jpg|png|gif)$/i', 'image.jpg');  // ✅ Matches
preg_match('/\.(jpg|png|gif)$/i', 'image.doc');  // ❌ No match

// Check for exact phrase
preg_match('/^hello world$/', 'hello world');    // ✅ Matches
preg_match('/^hello world$/', 'hello world!');   // ❌ No match
```

---

## ✅ Good Patterns vs ❌ Bad Patterns

### Good: Properly Anchored

```php
// Match whole word
'/\b\w+\b/'

// Validate email format
'/^[^\s@]+@[^\s@]+\.[^\s@]+$/'

// Check file extension
'/\.(?:txt|md|json)$/i'
```

### Bad: Missing Anchors

```php
// ❌ Without anchors, matches partial strings
'/[a-z]+@[a-z]+\.[a-z]+/'  // Matches "foo@bar.com" in "xyz foo@bar.com xyz"

// ✅ With anchors, matches entire string
'/^[a-z]+@[a-z]+\.[a-z]+$/'  // Only matches if ENTIRE string is an email

// ❌ Forgetting \b allows partial word matches
'/cat/'  // Matches "cat" in "category"

// ✅ With \b, matches whole words only
'/\bcat\b/'  // Only matches "cat" as a standalone word
```

---

## 🧪 Exercises

### Exercise 1: Identify Matches

For each pattern, determine if it matches the text:

1. Pattern: `/^hello$/`, Text: "hello world"
2. Pattern: `/hello$/`, Text: "say hello"
3. Pattern: `/\bcat\b/`, Text: "the category"
4. Pattern: `/^[0-9]+$/`, Text: "123abc"

```php
// Answers:
// 1. ❌ No - "hello world" is not exactly "hello"
// 2. ✅ Yes - text ends with "hello"
// 3. ❌ No - "category" has "cat" as part of a larger word
// 4. ❌ No - "123abc" contains non-digits
```

### Exercise 2: Write Patterns

Write patterns that:

1. Match strings that start with "http://"
2. Match strings that end with ".json"
3. Match the word "test" as a whole word
4. Match strings containing only letters and spaces

```php
// Solution 1
$pattern1 = '/^http:\/\//';

// Solution 2
$pattern2 = '/\.json$/';

// Solution 3
$pattern3 = '/\btest\b/';

// Solution 4
$pattern4 = '/^[A-Za-z ]+$/';
```

### Exercise 3: Validate and Explain

```php
use RegexParser\Regex;

$regex = Regex::create();

$patterns = [
    '/^error/',
    '/error$/',
    '/\bword\b/',
    '/^\d{3}-\d{4}$/',
];

foreach ($patterns as $pattern) {
    $result = $regex->validate($pattern);
    echo "$pattern: " . ($result->isValid() ? "Valid" : "Invalid") . "\n";
    echo "  Explanation: " . $regex->explain($pattern) . "\n\n";
}
```

---

## 📚 Key Takeaways

1. **Anchors** `^`, `$`, `\A`, `\z` match positions, not characters
2. **`^`** matches start of string (every line with `/m`)
3. **`$`** matches end of string (every line with `/m`)
4. **`\A`** always matches absolute start
5. **`\z`** always matches absolute end
6. **`\b`** matches word boundaries (between `\w` and `\W`)
7. **Combine anchors** `^` and `$` for exact matches

---

## 🆘 Common Errors

### Error: Forgetting Anchors in Validation

```php
// ❌ Wrong: Partial match
preg_match('/^[0-9]+$/', '123-456');  // ❌ No match (has hyphen)
preg_match('/[0-9]+/', '123-456');    // ✅ Matches "123"

// ✅ Correct: Anchors for validation
preg_match('/^[0-9]+$/', '123456');   // ✅ Matches
```

### Error: $ vs \z

```php
$text = "line1\nline2\nline3";

// $ matches the last line with /m
preg_match('/line2$/m', $text);  // ✅ Matches (line2 is end of a line)

// \z only matches the absolute end
preg_match('/line2\z/m', $text); // ❌ No match (line2 is not at end)
```

### Error: Word Boundary Confusion

```php
// \b at start and end of underscores
preg_match('/\b_\w+_\b/', '_hello_');  // ❌ No match (_ is part of \w)

// Underscores are word characters!
preg_match('/__\w+__/', '__hello__');  // ✅ Matches
```

---

## 🎉 You're Ready!

You now understand:
- Start (`^`) and end (`$`) anchors
- Absolute anchors (`\A`, `\z`)
- Word boundaries (`\b`)
- Combining anchors with patterns
- Common pitfalls and fixes

**Next:** [Chapter 4: Quantifiers and Greediness](04-quantifiers.md)

---

<p align="center">
  <b>Chapter 3 Complete! →</b>
</p>

---

Previous: [Character Classes](02-character-classes.md) | Next: [Quantifiers](04-quantifiers.md)
