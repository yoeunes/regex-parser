# 04. Quantifiers

Quantifiers control repetition: how many times a token can appear.

> This is where most regex power (and risk) comes from. We will go deeper in the ReDoS chapter.

## The Basics

| Quantifier | Meaning |
| --- | --- |
| `?` | 0 or 1 |
| `*` | 0 or more |
| `+` | 1 or more |
| `{m,n}` | Between m and n |

```php
use RegexParser\Regex;

$regex = Regex::create();
$regex->validate('/a*/');
$regex->validate('/a{2,4}/');
```

## Greedy vs Lazy vs Possessive

- Greedy: `+` (default)
- Lazy: `+?`
- Possessive: `++`

RegexParser stores this on `QuantifierNode::type`.

## AST Example

```bash
bin/regex diagram '/a{2,4}?/'
```

```
RegexNode
+-- SequenceNode
    +-- QuantifierNode("{2,4}?")
        +-- LiteralNode("a")
```

## Exercises

1. Match 2 to 5 digits.
2. Match optional `http` in `https`.

---

Previous: `03-anchors-boundaries.md` | Next: `05-groups-alternation.md`
