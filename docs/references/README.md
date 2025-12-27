# External References

This page links to authoritative references and high-quality tutorials for
regex syntax, engine behavior, and performance. These are the sources we use
when verifying diagnostics and documenting edge cases.

## PCRE2 specification

- PCRE2 syntax overview: https://www.pcre.org/current/doc/html/pcre2syntax.html
- Full pattern syntax: https://www.pcre.org/current/doc/html/pcre2pattern.html
- Performance considerations: https://www.pcre.org/current/doc/html/pcre2perform.html
- PCRE2 API (engine details): https://www.pcre.org/current/doc/html/pcre2api.html

## PHP-specific references

- PHP PCRE manual: https://www.php.net/manual/en/book.pcre.php
- Pattern syntax reference: https://www.php.net/manual/en/regexp.reference.php
- Pattern modifiers: https://www.php.net/manual/en/reference.pcre.pattern.modifiers.php

## Security and ReDoS

- OWASP ReDoS overview: https://owasp.org/www-community/attacks/Regular_expression_Denial_of_Service_-_ReDoS
- Catastrophic backtracking: https://www.regular-expressions.info/catastrophic.html

## Engine internals and theory

- Regular Expression Matching Can Be Simple And Fast (Russ Cox): https://swtch.com/~rsc/regexp/regexp1.html
- NFA and DFA background: https://en.wikipedia.org/wiki/Nondeterministic_finite_automaton
- Backtracking (concept overview): https://en.wikipedia.org/wiki/Backtracking

## Tutorials and practical guidance

- Regular-Expressions.info tutorial hub: https://www.regular-expressions.info/
- Character classes: https://www.regular-expressions.info/charclass.html
- Quantifiers and greediness: https://www.regular-expressions.info/repeat.html
- Lookarounds: https://www.regular-expressions.info/lookaround.html

## Engine compatibility

- RE2 syntax (for comparison): https://github.com/google/re2/wiki/Syntax

---

Previous: [AST Traversal Design](../design/AST_TRAVERSAL.md) | Next: [Docs Home](../README.md)
