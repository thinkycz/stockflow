# Responsive shift calendar verification

## Scope verified

- The authenticated `/shifts` page and public shift calendar use the same
  responsive calendar component.
- Desktop renders a seven-column month grid with clearer day and shift
  hierarchy.
- Mobile renders a compact month picker and a selected-day agenda instead of a
  horizontally scrolling desktop calendar.
- Mobile users can toggle between the selected-day agenda and the complete
  seven-column month calendar containing all shift cards; selecting a date in
  the full calendar returns to its day detail.
- Mobile day selection updates the agenda and authenticated administrators can
  open the existing add-shift flow from the selected day.
- Public shifts remain read-only and sorted by start time.

## Runtime evidence

- Authenticated and public pages were inspected at desktop and 390 × 844 mobile
  viewport sizes.
- Both mobile pages reported equal document scroll width and client width, so
  there is no horizontal overflow.
- Selecting July 15 updated `aria-pressed` and the selected-day heading.
- Both pages produced zero browser console errors.

## Automated checks

- Focused Playwright shift suite: 2 tests passed, including the new mobile
  responsive regression.
- PHPStan: no errors.
- Prettier and Pint: passed.
- Vue type-check and production build: passed.
- Vitest: 14 tests passed.
- Pest: 492 tests with 9,392 assertions passed.

## Known repository-level blocker

The dependency audit remains outside this change: the existing lockfile has four
known Guzzle advisories documented by the earlier verification work.
