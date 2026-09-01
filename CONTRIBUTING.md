# Contributing to BareTOC

Thank you for helping improve BareTOC. Contributions should preserve its central principle: the plugin handles document structure while the theme handles presentation.

## Before opening an issue

- Search existing issues for the same behavior or proposal.
- Confirm the problem with the latest release and a default WordPress theme when practical.
- For security vulnerabilities, follow [SECURITY.md](SECURITY.md) instead of opening a public issue.

## Local setup

BareTOC requires PHP 7.4 or newer. Development dependencies are managed with Composer.

```bash
composer install
composer test
composer lint
node --check assets/js/smooth-scroll.js
```

The smoke test is self-contained and does not require a running WordPress database.

## Pull requests

1. Create a focused branch from `main`.
2. Keep changes small and related to one problem.
3. Follow the WordPress PHP coding standards and preserve PHP 7.4 compatibility.
4. Add or update smoke coverage when behavior changes.
5. Update `README.md`, `readme.txt`, and `CHANGELOG.md` for user-facing changes.
6. Verify that no frontend dependency, external request, inline style, or unnecessary asset has been introduced.

By contributing, you agree that your contribution is licensed under GPL-2.0-or-later, the same license as BareTOC.
