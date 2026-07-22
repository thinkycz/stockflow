# Limited Store Incoming Traceability Matrix

| Req ID | Source              | Requirement                             | Phase | Tasks                      | Status   | Verification                     | Notes                   |
| ------ | ------------------- | --------------------------------------- | ----- | -------------------------- | -------- | -------------------------------- | ----------------------- |
| R1     | user request        | Dedicated receipt section for non-admin | 2     | sidebar and form mode      | verified | type-check and frontend build    | assigned-store workflow |
| R2     | user request        | Increase stock at assigned branch       | 1     | controller/service         | verified | controller feature test          | reuse incoming ledger   |
| R3     | security convention | Reject another branch                   | 1     | service authorization      | verified | forbidden feature test           | server-enforced         |
| R4     | repo convention     | Preserve admin and consumption behavior | 3     | surrounding regression run | verified | 39 movement tests and full suite | no admin UX changes     |
| R5     | i18n convention     | Keep cs/en/sk synchronized              | 2, 3  | locale keys                | verified | i18n parity test                 | no hardcoded labels     |
