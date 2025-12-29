# 02. Character Classes

Character classes let you match a set of characters. This is where regex starts to feel powerful.

> We explain the set first, then we show the AST node (`CharClassNode`).

## Basic Classes

- `[abc]` matches `a` or `b` or `c`.
- `[a-z]` matches any lowercase letter.
- `[^a-z]` matches anything that is not a lowercase letter.

```php
use RegexParser\Regex;

$regex = Regex::create();
$regex->validate('/[a-z]+/');
$regex->validate('/[^0-9]+/');
```

## Shorthand Classes

| Shorthand | Meaning |
| --- | --- |
| `\d` | Digit |
| `\w` | Word character |
| `\s` | Whitespace |

These are represented as `CharTypeNode` in the AST.

## Visualize the Class

```bash
bin/regex diagram '/[a-z]+/'
```

```
RegexNode
+-- SequenceNode
    +-- QuantifierNode("+")
        +-- CharClassNode("[a-z]")
```

## Exercises

1. Match any hex digit: `[0-9a-fA-F]`.
2. Match any non-digit: `\D`.
3. Use `bin/regex explain` and confirm the meaning.

---

Previous: `01-basics.md` | Next: `03-anchors-boundaries.md`
