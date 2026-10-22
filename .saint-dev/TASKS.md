# Tasks

Next milestone: validate the complete current POS source on a disposable development database before release work.

- Provision isolated developer/QA databases through the normal setup workflow and document any reproducible fixture gaps.
- Run targeted current payment, partial-payment, stock rejection, coupon, tax/refund and branding/health regressions. Review later edits on their merits; do not change money logic merely to satisfy a doubtful precision assertion.
- Improve repeatable QA fixture setup without introducing a live-data dependency or credentials into Git.
- Verify fresh source links/assets, Indonesian presentation and local scheduler lifecycle on the intended development environment.
- Keep packaging/deployment, cross-PC testing and hardware checks out of scope until separately authorized.

No task in this list authorizes modifying the existing working installation or pushing secrets/business data.
