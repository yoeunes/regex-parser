# Contributing to RegexParser

First off, thank you for considering contributing to RegexParser! It's people like you that make the open-source community such an amazing place to learn, inspire, and create.

## 🛠️ Development Setup

This project uses **[Task](https://taskfile.dev/)** to manage development scripts.

1. **Clone the repository**
2. **Install dependencies**:

```bash
   task install
````

## 🧪 Running Tests & Quality Checks

Before submitting a Pull Request, please ensure all checks pass. You can run everything with a single command:

```bash
task lint
```

Or run individual checks:

  - **Unit Tests**: `task phpunit`
  - **Static Analysis**: `task phpstan`
  - **Coding Standards**: `task phpcs`
  - **Rector**: `task rector`

## 📝 Coding Standards

  - **PHP Version**: We target PHP 8.4+.
  - **Strict Types**: All files must declare `declare(strict_types=1);`.
  - **Type Hinting**: Use specific types everywhere (params, returns, properties). Avoid `mixed` unless absolutely necessary.
  - **Immutability**: Prefer `readonly` classes and immutable objects for AST nodes.
  - **Documentation**: Public API methods must have PHPDoc blocks if the type hint isn't enough description.

## 🐛 Reporting Bugs

Please use the GitHub Issue Tracker. Include:

1.  The regex pattern causing the issue.
2.  The expected AST or behavior.
3.  The actual output or error.

## 🎁 Pull Requests

1.  Fork the repo and create your branch from `main`.
2.  If you've added code that should be tested, add tests.
3.  If you've changed APIs, update the documentation.
4.  Ensure the test suite passes (`task lint`).
5.  Issue that PR\!

## 🧩 Architecture

If you are adding a new feature:

  - **Tokens**: Go in `src/TokenType.php` and `src/Lexer.php`.
  - **Nodes**: Go in `src/Node/`.
  - **Logic**: Should be implemented as a **Visitor** in `src/NodeVisitor/`. Avoid putting logic directly in Nodes.
