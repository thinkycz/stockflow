# Shift requests delivery

## Source

Approved implementation plan from 2026-08-07: public future-month shift requests, reversible monthly locking, and an optional admin calendar overlay.

## Phases

- [x] Phase 1: schema, models, validation, transactional service, and factories
- [x] Phase 2: public/admin controllers, routes, and feature tests
- [x] Phase 3: public request page and shared calendar/admin UI
- [x] Phase 4: translations, application documentation, and end-to-end verification

## Requirement matrix

| Requirement                                                 | Status      | Evidence                                        |
| ----------------------------------------------------------- | ----------- | ----------------------------------------------- |
| One request per worker/day with create-update-delete toggle | Implemented | Controller feature tests                        |
| Future months only                                          | Implemented | Index and toggle feature tests                  |
| Reversible per-store monthly lock                           | Implemented | Lock controller feature tests                   |
| Public page through existing share token                    | Verified    | Controller tests and Chrome runtime flow        |
| Admin overlay toggle with distinct request cards            | Verified    | Chrome desktop runtime flow                     |
| Desktop and mobile calendar support                         | Verified    | Chrome desktop and 390 px mobile runtime checks |

## Current slice

Delivery is complete. Targeted tests, the full `make check` gate, and the browser verification flow pass. Detailed evidence is recorded in `docs/verification/2026-08-07-shift-requests.md`.

## Deferred by specification

- Worker identity authentication on the public page
- Request approval or conversion into a shift
- Automatic request removal after shift creation
- Dedicated admin request editing or deletion
