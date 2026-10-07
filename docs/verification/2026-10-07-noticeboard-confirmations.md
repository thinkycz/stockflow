# Dated noticeboard cards and first-arrival confirmation

Approved behavior is implemented: optional `display_on`, one immutable daily
reading list per retail store and Prague day, first-worker attribution,
mandatory per-card selection in Attendance, idempotent confirmation, and a green
accessible `BadgeCheck` indicator on the corresponding current card date.

## Implementation and regression evidence

| Requirement                                                           | Implementation and verification                                                                                                                                                               |
| --------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Create, preserve omitted date, change and explicitly clear            | Card controller/service, assistant tool definitions and executor; controller and assistant feature tests                                                                                      |
| First arrival, including arrival without a shift                      | Attendance transaction invokes Noticeboard under the store row lock; daily store/date unique constraint                                                                                       |
| All eligible cards without pagination; empty first list stays empty   | Immutable item snapshots; service tests cover 28 cards, deleted/expired/foreign dates, later changes and empty lists                                                                          |
| Prague midnight and DST; no retroactive or next-day obligation        | Business-day service tests and pre-existing attendance regression                                                                                                                             |
| Complete list, account/time attribution, idempotence, store isolation | Confirmation controller and service tests reject incomplete, duplicate and foreign IDs and unauthorized stores                                                                                |
| Snapshot content and private images survive original changes/deletion | Reference-aware image cleanup and authorized snapshot image route; service/image controller tests                                                                                             |
| Required dialog and server-backed icon                                | Isolated Chromium flow covers reading each card, blocked dismissal, immediate running attendance, failed request retry, refresh/revisit, later arrival, date change/removal and mobile layout |
| Architecture and human-only assistant action                          | Workforce → Noticeboard ADR and architectural gate; assistant route/read parity exclusions                                                                                                    |
| Actual MySQL serialization                                            | Two-connection tests added for competing arrivals and a card edit; execution currently unavailable because the local MySQL endpoint refuses connections                                       |

## Verification status

- Targeted backend and architecture checks passed before dependency remediation.
- The new Chromium scenario passed in the final complete gate, using the isolated
  SQLite test database and production asset build. Mobile screenshots were
  visually inspected at 390 × 844.
- Final `make fix` and `make check`: exit 0. PHPStan level max, Prettier/Pint,
  Composer platform/manifest/audit, npm production audit, TypeScript, Vite build,
  Laravel cache build/clear smoke and all executable suites passed.
- Vitest: 32 files, 146 tests. Pest: 1,323 tests, 58,026 assertions, with 21
  expected skips (19 MySQL-only tests, including the two new races, and two
  opt-in live provider smokes). Chromium: 89 tests, including the complete new
  confirmation flow and existing dismissible-dialog regression.
- Composer remediation was explicitly approved. Only Laravel 13.30.0 → 13.35.0,
  CommonMark 2.10.0 → 2.10.3 and Flysystem 3.35.3 → 3.36.0 changed in the lockfile.
  Installed vendor was synchronized with the existing lockfile; Composer now
  reports no security advisories.
- The first complete gate reached npm audit and stopped on existing frontend
  advisories. Compatible fixes update Vue and Vitest patch families, Axios,
  DOMPurify, ProseMirror, source-map-js and Undici, including their required
  transitive packages. All available Concurrently releases pin a vulnerable
  shell-quote version, so an explicit `shell-quote: ^1.12.0` override supplies
  the compatible corrected release. No direct dependency range was changed;
  the complete npm audit now has zero findings, including development tools.
- The final gate also caught and corrected an Attendance import of a Noticeboard
  workflow type. The shared payload contract now lives in
  `resources/js/types/noticeboard.ts`; frontend feature isolation is unchanged.
  Synchronizing the locked Prettier version required format-only union changes
  in four existing frontend files.

## Runtime limits

The local application uses MySQL, which is not running on this host. The additive
migration is verified by the isolated SQLite feature/browser suites. Applying
only the new migration failed with `SQLSTATE[HY000] [2002] Connection refused`
at `127.0.0.1:3306`, so it has not been applied to the local application database.
When that service is available, run:

```sh
php artisan migrate --path=database/migrations/2026_10_07_000001_create_noticeboard_confirmations.php --force
```

The MySQL-only race tests require MySQL and pcntl
and should be run against a disposable database, never the application database.
No production deployment was performed.
