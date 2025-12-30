# Reference Index

This section groups the core reference documents for RegexParser. Use these guides when you need detailed information about specific aspects of the library.

## Documentation Map

```
┌─────────────────────────────────────────────────────────────┐
│              REGEXPARSER DOCUMENTATION STRUCTURE            │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌─────────────────────────────────────────────────────┐    │
│  │                   GETTING STARTED                   │    │
│  │                                                     │    │
│  │   README.md ──► Quick Start ──► Tutorial            │    │
│  └─────────────────────────────────────────────────────┘    │
│                           │                                 │
│                           ▼                                 │
│  ┌─────────────────────────────────────────────────────┐    │
│  │                    REFERENCE                        │    │
│  │                                                     │    │
│  │   ┌─────────────────────────────────────────────┐   │    │
│  │   │ CORE REFERENCE                              │   │    │
│  │   ├─────────────────────────────────────────────┤   │    │
│  │   │ • reference.md        - All lint rules      │   │    │ • api │
│  │.md              - API methods                   │   │    │
│  │   │ • diagnostics.md      - Error messages      │   │    │
│  │   │ • diagnostics-cheatsheet.md - Quick fixes   │   │    │
│  │   │ • faq-glossary.md     - FAQ and definitions │   │    │
│  │   └─────────────────────────────────────────────┘   │    │
│  │                                                     │    │
│  │   ┌─────────────────────────────────────────────┐   │    │
│  │   │ AST REFERENCE                               │   │   │
│  │   ├─────────────────────────────────────────────┤   │   │
│  │   │ • nodes/README.md     - Node types          │   │   │
│  │   │ • visitors/README.md  - Visitor types       │   │   │
│  │   │ • design/AST_TRAVERSAL.md - Traversal       │   │   │
│  │   └─────────────────────────────────────────────┘   │   │
│  │                                                     │   │
│  │   ┌─────────────────────────────────────────────┐   │   │
│  │   │ EXTERNAL                                    │   │   │
│  │   ├─────────────────────────────────────────────┤   │   │
│  │   │ • references/README.md - External resources │   │   │
│  │   └─────────────────────────────────────────────┘   │   │
│  │                                                     │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                            │
└────────────────────────────────────────────────────────────┘
```

## Core Reference Documents

### Lint Rules and Diagnostics

| Document                                             | Description           | When to Read                            |
|------------------------------------------------------|-----------------------|-----------------------------------------|
| [Lint Rule Reference](reference.md)                  | Complete rule catalog | When you see an unfamiliar warning      |
| [API Reference](api.md)                              | Method documentation  | When using the library programmatically |
| [Diagnostics](diagnostics.md)                        | Error message guide   | When debugging validation failures      |
| [Diagnostics Cheat Sheet](diagnostics-cheatsheet.md) | Quick fix reference   | When you need a fast solution           |
| [FAQ and Glossary](faq-glossary.md)                  | Common questions      | When learning or clarifying terms       |

### AST and Visitors

| Document                                        | Description            | When to Read                        |
|-------------------------------------------------|------------------------|-------------------------------------|
| [AST Nodes](nodes/README.md)                    | Node type reference    | When building custom visitors       |
| [AST Visitors](visitors/README.md)              | Visitor type reference | When implementing analysis          |
| [AST Traversal Design](design/AST_TRAVERSAL.md) | How traversal works    | When understanding the architecture |

### External Resources

| Document                                    | Description   | When to Read                       |
|---------------------------------------------|---------------|------------------------------------|
| [External References](references/README.md) | Curated links | When you need deeper understanding |

---

## Quick Access by Task

### I need to...

| Task                          | Document                                                          |
|-------------------------------|-------------------------------------------------------------------|
| ...understand a lint warning  | [Lint Rule Reference](reference.md)                               |
| ...fix a validation error     | [Diagnostics Cheat Sheet](diagnostics-cheatsheet.md)              |
| ...use the library in my code | [API Reference](api.md)                                           |
| ...build a custom visitor     | [AST Nodes](nodes/README.md) + [AST Visitors](visitors/README.md) |
| ...learn regex patterns       | [Tutorial](../tutorial/README.md)                                 |
| ...check ReDoS safety         | [ReDoS Guide](../REDOS_GUIDE.md)                                  |
| ...find production patterns   | [Cookbook](../COOKBOOK.md)                                        |
| ...integrate with CI/CD       | [CLI Guide](../guides/cli.md)                                     |
| ...extend the library         | [Extending Guide](../EXTENDING_GUIDE.md)                          |

---

## Document Statistics

| Category            | Count |
|---------------------|-------|
| Core reference docs | 5     |
| AST reference docs  | 3     |
| Tutorial chapters   | 10    |
| Guides              | 5+    |
| External links      | 30+   |

---

## Navigation

| From                    | To                                                     |
|-------------------------|--------------------------------------------------------|
| Docs Home               | [README.md](../README.md)                              |
| Lint Rule Reference     | [reference.md](reference.md)                           |
| API Reference           | [api.md](api.md)                                       |
| Diagnostics             | [diagnostics.md](diagnostics.md)                       |
| FAQ and Glossary        | [faq-glossary.md](faq-glossary.md)                     |
| Diagnostics Cheat Sheet | [diagnostics-cheatsheet.md](diagnostics-cheatsheet.md) |
| AST Nodes               | [nodes/README.md](nodes/README.md)                     |
| AST Visitors            | [visitors/README.md](visitors/README.md)               |
| AST Traversal           | [design/AST_TRAVERSAL.md](design/AST_TRAVERSAL.md)     |
| External References     | [references/README.md](references/README.md)           |

---

Previous: [Docs Home](../README.md) | Next: [Lint Rule Reference](reference.md)
