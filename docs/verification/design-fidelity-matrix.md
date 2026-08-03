# Design fidelity matrix

## 2026-08-03 tab and noticeboard alignment polish

| Surface             | Issue                                                              | Result                                                                                 | Verification                                                                       |
| ------------------- | ------------------------------------------------------------------ | -------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------- |
| Reports tabs        | Underline tabs inherited the base button's full border             | Exact: active state uses a single bottom indicator and inactive tabs remain borderless | Keyboard semantics retained; browser regression covered by dashboard/report checks |
| Noticeboard filters | Labeled search stretched adjacent select and tabs out of alignment | Exact: toolbar controls align to the same bottom edge at desktop widths                | Responsive layout and noticeboard interactions covered by Playwright               |
| Segmented tabs      | Tab groups expanded unnecessarily outside constrained toolbars     | Close: segmented groups now use intrinsic width with horizontal overflow retained      | Shared component contract and mobile overflow checked                              |

No asset, font, overlay, motion, or route ownership changes were required.
