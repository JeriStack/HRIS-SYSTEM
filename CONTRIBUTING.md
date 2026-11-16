# Contributing to NEXORA HR

Thank you for helping improve NEXORA HR. This guide explains how to report issues, set up the project, and submit changes.

- **Repository:** https://github.com/JeriStack/HRIS-SYSTEM
- **Lead Maintainer:** Jeril Mark Estinsu
- **Core Maintainer:** Kevin Quijano
- **Contact:** jerilpalin@gmail.com

By participating, you agree to follow the [Code of Conduct](CODE_OF_CONDUCT.md).

## Ground Rules

- Use synthetic data only. Never post real employee, payroll, leave, or complaint data in issues, pull requests, screenshots, logs, or test fixtures.
- Never commit secrets. `.env` files stay local; update the `.env*.example` files when you add a setting.
- Report security vulnerabilities privately to the contact above. Do not open a public issue.

## Reporting Bugs and Requesting Features

Open an issue at https://github.com/JeriStack/HRIS-SYSTEM/issues. For a bug, include:

- What you expected and what happened
- Steps to reproduce
- Your role (Admin, HR, or Employee), browser, and PHP version
- Screenshots or log excerpts with all personal data removed

## Development Setup

The project runs in Docker. Use the Makefile targets from the repository root:

```bash
make build      # Build Docker images
make up         # Start services
make install    # Install Composer dependencies
make migrate    # Run database migrations
```

Other useful targets: `make ps`, `make logs`, `make down` (never removes volumes), `make restart`, and `make clean`. Run `make help` for the full list. Requirements: PHP 8.2 to 8.5 and MySQL 8.0 (both provided by the Docker setup).

More detail is in the [documentation hub](docs/README.md).

## Making a Change

1. Fork the repository and create a branch from the default branch. Use a descriptive name such as `fix/leave-balance-rounding` or `feat/support-attachments`.
2. Keep the change focused. One concern per pull request.
3. Follow the existing architecture: 4-tier MVC with DDD and 3-role RBAC. Put business rules in services, not controllers or views. Check permissions on the server, not only in the UI.
4. Use parameterized queries, escape output, and keep CSRF protection on every form.
5. Add or update tests for the behavior you change.
6. Update documentation under `docs/` when behavior, configuration, or commands change.

## Code Quality and Tests

Run these before opening a pull request:

```bash
make test       # PHPUnit
make lint       # PHPCS (PSR-12) baseline check and PHPStan
make e2e        # Selenium UI tests (needed for UI or workflow changes)
```

Without Make, the equivalent Composer commands are `composer test` and `composer lint`.

- New code must follow PSR-12 and pass PHPStan.
- Do not add entries to `phpcs-baseline.json` or `phpstan-baseline.neon` to hide new problems. Fix them.
- Do not remove or skip a failing test to get a green build. Fix the cause.
- Do not rely on fixed sleeps in Selenium tests. Wait for explicit conditions.

## Commit Messages

Use the [Conventional Commits](https://www.conventionalcommits.org) format:

```
<type>(<optional scope>): <short summary in imperative mood>

<body explaining what changed and why>
```

Common types: `feat`, `fix`, `docs`, `test`, `refactor`, `chore`, `ci`. Keep the summary under about 72 characters. Every change needs a commit message that explains the reason, not only the files touched.

## Pull Requests

- Describe what changed, why, and how you tested it.
- Link related issues (for example, `Closes #12`).
- Include before and after screenshots for UI changes, in light and dark themes and at mobile width. Remove personal data first.
- Confirm that CI passes. A maintainer reviews every pull request, and you may be asked to revise it.
- Keep your branch up to date with the default branch before requesting a final review.

## Questions

Contact the maintainers at jerilpalin@gmail.com or open a discussion issue.
