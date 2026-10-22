# Status

As of 2026-10-22, this snapshot preserves the current customized source, current assets and current maintained tests. Migration validation is limited to source integrity, secret/category review, dependency restoration and selected non-database checks. It does not certify every POS business workflow.

Historical local evidence from VTX-POS-006 (2026-10-20): health/branding suite passed 15 tests / 131 assertions; six browser checks passed; all three scheduler/junction/NTFS warnings were resolved. That evidence predates later preserved POS payment/stock/coupon edits. Earlier upstream full-suite runs had nine precision/fixture-related failures/errors; they were not falsely marked passed and production calculations were not changed merely to satisfy assertions.

Known limits: fresh PHP feature runs need an independently installed vertex_qa database, schema/default fixtures and vertexadmin user. No operational database or admin password is included. Physical printer/scanner/cash-drawer behavior is unverified here. Windows health branches were tested locally; other OS branches were not retested. No crash supervisor, public deployment or installer is provided.

See README.md for setup/test prerequisites and TASKS.md for the next milestone.
