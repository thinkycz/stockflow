# Inventory cancel modal and plus reason

## Symptoms

- The cancellation confirmation remained open after a successful cancellation.
- Increasing a counted quantity above the current stock with the plus button could retain a previously saved negative-difference reason.

## Root cause

- The Inertia success path only reset `cancelling`; it never reset `cancelModalOpen`. Inertia can reuse the same page component after the redirect, preserving that local state.
- `setQuantity` intentionally avoids changing a classification marked as touched. A classification loaded from an autosaved draft is marked as touched, so the plus action could not reliably apply its explicit positive-difference preset.

## Fix

- Close the modal in the successful cancellation callback while keeping it open if the request fails.
- When the plus action produces a quantity above current stock, explicitly preset `inventory_correction`.

## Scope check

The modal state belongs only to the inventory cancellation flow. Other modal-backed forms already close their own state in successful callbacks. The classification override is intentionally limited to the inventory plus button and does not change manual quantity entry behavior.
