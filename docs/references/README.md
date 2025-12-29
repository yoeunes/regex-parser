# External References

This is a curated list of references we rely on when validating diagnostics and documenting edge cases.

> We prefer official sources when there is a conflict.

## PCRE2 Specification

| Resource | Description |
| --- | --- |
| https://www.pcre.org/current/doc/html/pcre2syntax.html | Syntax overview |
| https://www.pcre.org/current/doc/html/pcre2pattern.html | Full pattern reference |
| https://www.pcre.org/current/doc/html/pcre2perform.html | Performance notes |
| https://www.pcre.org/current/doc/html/pcre2api.html | Engine API |

## PHP References

| Resource | Description |
| --- | --- |
| https://www.php.net/manual/en/book.pcre.php | PHP PCRE manual |
| https://www.php.net/manual/en/regexp.reference.php | Pattern syntax |
| https://www.php.net/manual/en/reference.pcre.pattern.modifiers.php | Modifiers |
| https://www.php.net/manual/en/function.preg-last-error.php | Error codes |
| https://www.php.net/manual/en/function.preg-last-error-msg.php | Error messages |

## Security and ReDoS

| Resource | Description |
| --- | --- |
| https://owasp.org/www-community/attacks/Regular_expression_Denial_of_Service_-_ReDoS | ReDoS overview |
| https://www.regular-expressions.info/catastrophic.html | Backtracking risk |
| https://cheatsheetseries.owasp.org/cheatsheets/Regular_Expression_Denial_of_Service_-_ReDoS_Prevention_Cheat_Sheet.html | Prevention guide |

## Engine Internals

| Resource | Description |
| --- | --- |
| https://swtch.com/~rsc/regexp/regexp1.html | NFA vs DFA overview |
| https://en.wikipedia.org/wiki/Backtracking | Backtracking algorithms |

## Tutorials and Practice

| Resource | Description |
| --- | --- |
| https://www.regular-expressions.info/ | Comprehensive tutorial |
| https://regexone.com/ | Interactive basics |
| https://www.rexegg.com/ | Advanced topics |

## Testing and Visualization

| Tool | URL | Notes |
| --- | --- | --- |
| regex101 | https://regex101.com/ | Use PCRE (PHP) flavor |
| regexper | https://regexper.com/ | Railroad diagrams |
| debuggex | https://www.debuggex.com/ | Visual matcher |
| regexr | https://regexr.com/ | General tester |

## Unicode References

| Resource | Description |
| --- | --- |
| https://unicode.org/reports/tr18/ | Unicode regex standard |
| https://unicode.org/cldr/utility/blocks.jsp | Unicode blocks |
| https://unicode.org/cldr/utility/category.jsp | Unicode categories |

## Engine Compatibility

| Engine | Notes |
| --- | --- |
| RE2 | Linear time, no backreferences/lookbehinds |
| JavaScript | Different flag set, evolving features |
| Python `re` | Similar but not identical to PCRE2 |
| .NET | Many extra features and different syntax |

---

Previous: `design/AST_TRAVERSAL.md` | Next: `../reference/README.md`
