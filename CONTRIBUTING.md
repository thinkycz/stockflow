# Contributing

## Ground rules

- `docs/guidelines.md` is the source of truth for coding standards. Read it before
  opening a PR.
- `AGENTS.md` captures project-specific architecture, workflows, and conventions
  that build on top of `docs/guidelines.md`.
- All commits must pass `make fix && make check`.

## Local setup

```sh
make local           # composer + npm install, migrate, seed
make serve           # http://localhost:8000
```

## Workflow

1. Branch off `main`.
2. Implement in small, reviewable commits.
3. Run `make fix` (Prettier + Pint auto-format).
4. Run `make check` (PHPStan, Prettier/Pint --test, audits, frontend
   build/type-check, PHPUnit).
5. Run `make e2e` for browser-level checks.
6. Open a PR with a clear description and reference any related issue.

## Commit style

- Imperative mood ("Add login rate limiting", not "Added").
- Body explains _why_, not _what_.
- Keep changes scoped: one logical change per commit.

## Adding new code

- **Backend**: keep app behavior thin; delegate to
  `packages/thinkycz/laravel-core` helpers (`Resolver`, `Config`, `Env`,
  `Typer`, `AuthValidity`, `DatabaseToken`, `EmailBrokerService`).
- **Frontend**: prefer small UI components under `resources/js/components/ui`,
  import via `@/`, and keep `pages/` thin.
- **Tests**: every new controller path needs both a happy-path feature test
  and an e2e spec if it surfaces a user-visible screen or form.

## Pull request checklist

- [ ] `make fix && make check` passes locally.
- [ ] `make e2e` passes locally (or scope is documented).
- [ ] Tests added for new behavior.
- [ ] No new PHPStan / Pint / Prettier warnings.
- [ ] `CHANGELOG.md` updated under `[Unreleased]`.
- [ ] No secrets, no debug `dd()` / `console.log` left in code.
