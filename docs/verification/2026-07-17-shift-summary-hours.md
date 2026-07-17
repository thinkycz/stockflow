# Shift summary hours verification

## Claim

Morning shifts with zero-padded hours contribute their correct duration to the admin monthly summary.

## Evidence

- Regression fixture `09:00–16:00` returns `420` minutes.
- The actual local MySQL shift `09:00:00–16:00:00` returns `420` minutes / `7` hours after the fix; it previously returned `960` minutes / `16` hours.
- The shift index controller test continues to verify fractional monthly totals and derived salary.
- Related shift tests, PHPStan, formatting, type-checking, and frontend build were run after the final change.

## Recurrence prevention

Parse complete SQL time values as time objects. Do not pass zero-padded time components through a generic integer parser.
