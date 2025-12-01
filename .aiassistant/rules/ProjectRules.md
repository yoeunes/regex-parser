---
apply: always
---

# Project Guidelines for Gemini

## Core Architecture
- **Strict Visitor Pattern:** All new nodes must be implemented as `readonly` classes in `src/Node/`.
- **No Logic in Nodes:** AST Nodes are data-only. All logic (validation, compilation) lives in Visitors.
- **Visitor Implementation:** When adding a node, you MUST:
  1. Implement `accept(NodeVisitorInterface $visitor)`.
  2. Update `NodeVisitorInterface` with a new `visit` method.
  3. Update ALL visitor classes (Compiler, Validator, etc.) to handle the new node.

## PHP 8.4 Coding Standards
- **Strict Types:** Always start files with `declare(strict_types=1);`.
- **Constructor Promotion:** Use public constructor property promotion.
- **Unit Tests:** Place tests in `tests/Unit`. Do not use `tests/Fixtures` unless explicitly asked (saves tokens).

## Token Saving Rules
- **Be Concise:** Do not explain the code unless asked. Just give me the code block.
- **No Conversational Filler:** Skip "Here is the code you asked for".
- **Context:** I am using the `yoeunes/regex-parser` library structure.
