# RegexParser Project Guidelines

## Core Architecture
- **Design Pattern:** This library relies strictly on the **Visitor Pattern**. 
- **AST Nodes:** All nodes must implement `RegexParser\Node\NodeInterface`.
- **Immutability:** All Node classes must be `readonly` and immutable.
- **Separation of Concerns:** The `Lexer` converts strings to `TokenStream`. The `Parser` consumes the stream to build the AST.

## Coding Standards (PHP 8.4)
- **Strict Types:** All files must start with `declare(strict_types=1);`.
- **Constructor Promotion:** Use constructor property promotion for all DTOs and Nodes.
- **Type Safety:** Avoid `mixed`. Use specific types. Use `void` return types where applicable.
- **Visibility:** Use `private` properties for internal state, `public readonly` for DTOs/Nodes.

## Implementation Rules
1. **Adding a New Node:**
   - Create the class in `src/Node/`.
   - Implement `public function accept(NodeVisitorInterface $visitor)`.
   - You MUST update `RegexParser\NodeVisitor\NodeVisitorInterface` to include `visitNewNode`.
   - You MUST update ALL existing Visitors (Compiler, Validator, Explain, etc.) to handle the new node.

2. **Error Handling:**
   - Throw `RegexParser\Exception\LexerException` for tokenization errors.
   - Throw `RegexParser\Exception\ParserException` for syntax errors.

3. **Testing:**
   - **Unit Tests:** Test individual components in `tests/Unit`.
   - **Behavioral Tests:** When adding PCRE features, add a case to `tests/Integration/BehavioralComplianceTest.php` to ensure `preg_match` parity.

## Contextual Constraints
- **Zero Dependency:** Do not suggest external composer packages (except for dev-tools).
- **Performance:** Avoid array allocations in tight loops (e.g., Lexer).
