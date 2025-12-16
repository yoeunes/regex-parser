# Regex Parser Rules Reference

## Optimizations

### Useless Flag 's' (DOTALL)
The `s` modifier (PCRE_DOTALL) forces the dot `.` to match newlines. If your pattern does not contain a dot, this flag adds overhead for no reason.
*Fix: Remove the `s` modifier.*

### Useless Flag 'm' (Multiline)
The `m` modifier (PCRE_MULTILINE) affects how `^` and `$` work. If your pattern does not contain anchors, this flag is useless.
*Fix: Remove the `m` modifier.*

### Useless Flag 'i' (Caseless)
The `i` modifier makes the match case-insensitive. If your pattern contains no letters (e.g. only numbers or symbols), this flag is useless.
*Fix: Remove the `i` modifier.*

## Security (ReDoS)

### Catastrophic Backtracking
Your pattern contains nested quantifiers (e.g., `(a+)+`) that can cause exponential backtracking. A malicious input could cause your application to hang (Denial of Service).
*Fix: Use [Atomic Groups](#atomic-groups) `(?>...)` or [Possessive Quantifiers](#possessive-quantifiers) `++`.*

## Advanced Syntax

### Possessive Quantifiers
Possessive quantifiers (e.g., `*+`, `++`) match greedily and do not backtrack. They can improve performance and prevent ReDoS in certain patterns.
*Fix: Replace standard quantifiers with possessive ones (e.g., `*` → `*+`) where backtracking is not needed.*

### Atomic Groups
Atomic groups `(?>...)` prevent backtracking within the group once matched. They are useful for optimizing patterns and avoiding exponential backtracking.
*Fix: Wrap quantified expressions in atomic groups to eliminate unnecessary backtracking.*

### Assertions
Assertions like lookahead `(?=...)` and lookbehind `(?<=...)` check conditions without consuming characters. They are useful for conditional matching.
*Fix: Use positive/negative lookahead or lookbehind to assert patterns without advancing the match position.*