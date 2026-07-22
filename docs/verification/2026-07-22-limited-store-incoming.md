# Limited Store Incoming Verification

## Claim

A limited user can open a dedicated goods-receipt section and add received item quantities only to their assigned store.

## Requirement Evidence

- The limited sidebar contains a localized "Goods receipt" entry linking to explicit `mode=incoming`.
- The shared movement form renders incoming-specific title, subtitle, and assigned receiving store.
- The controller exposes only the assigned store and prohibits backdating for limited users.
- The service accepts explicit incoming and consumption modes only when the destination equals `assigned_store_id`.
- Transfer, adjustment, and cross-store incoming submissions remain forbidden.
- Incoming persistence reuses the existing transactional ledger, numbering, value, creator attribution, stock update, and notification flow.

## Automated Evidence

- The new success-path test first failed because limited incoming requests were forced to consumption mode.
- Focused limited-user tests pass: 3 tests, 30 assertions.
- The stock-movement controller and service suite passes: 39 tests, 159 assertions.
- `make fix` passes Prettier and Pint.
- `make check` passes PHPStan at maximum level, formatting, dependency audits, Vue type checking, production build, frontend unit tests, and the complete backend suite.
- Complete backend suite: 530 tests, 10,701 assertions.
- Frontend unit suite: 14 tests across 6 files.
- Translation parity passes for Czech, English, and Slovak.

## Runtime Evidence

The local app responds successfully at `stockflow.test`. A browser login smoke for the limited-only sidebar was not performed because the documented seed provides only an admin credential and creating a local limited account would mutate the user's development database. The Inertia feature test verifies the limited-user page props and the production build verifies the Vue surface.

## Readiness Verdict

Ready for handoff. No code, test, build, security, translation, or documentation blockers remain. The only evidence gap is a non-mutating manual browser login as an existing limited user.
