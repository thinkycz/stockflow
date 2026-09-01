# Active-store session fix was not deployed

## Symptom

After the active-store selection was moved from the user record to the Laravel
session, switching stores on a phone still appeared to switch the store on a Mac.

## Evidence and root cause

- Forge release `76693546` exited before activation, so production continued
  serving the previous account-scoped implementation.
- Both `composer.lock` and `package-lock.json` were ignored. Forge therefore
  resolved new PHP dependencies during the release and `npm ci` failed before
  falling back to an unlocked install.
- The unlocked Composer resolution installed Laravel `13.30.0` and
  `inertiajs/inertia-laravel` `3.3.1`.
- With those versions, `php artisan view:cache` passes on its own, but the
  production-equivalent `php artisan optimize` sequence fails after config is
  cached because the root view uses the Inertia Blade component tags
  `<x-inertia::head>` and `<x-inertia::app>`.
- The exact Forge error was reproduced locally with the same versions:
  `Unable to locate a class or view for component [inertia::head]`.

## Fix

- Track the Composer and npm lockfiles so deployments use reviewed dependency
  versions and `npm ci` has its required input.
- Use Inertia's Blade directives, `@inertiaHead` and `@inertia`, in the root view.
- Add `make deploy-smoke` to build and clear Laravel's production caches, and run
  it as part of `make check`.

## Verification

- `php artisan optimize` completes with Laravel `13.30.0` and Inertia Laravel
  `3.3.1`.
- `npm ci --include=dev --install-links` completes from `package-lock.json`.
- `composer install --dry-run` confirms the locked PHP graph is installable.
- `make check` passes, including the new deployment smoke, 77 Vitest tests, and
  882 Pest tests with 29,109 assertions (one skipped).
- The Playwright regression with two isolated browser contexts passes and saves
  a statement without changing the other context's active store.
- A fresh-seed full Playwright run passes 60 of 66 tests. Six existing
  payroll/data-table and gift-voucher flows remain red because their fixtures or
  form behavior do not satisfy those tests; they are outside this deployment
  repair and are not represented as green release evidence.

The cross-device behavior must be retested only after the corrected release is
successfully activated; observations made against the failed release do not test
the session-scoped implementation.
