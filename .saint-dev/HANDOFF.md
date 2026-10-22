# Handoff for Codex and Antigravity

Start with PROJECT.md, ARCHITECTURE.md, DECISIONS.md, STATUS.md and TASKS.md. Root README.md is the current source-development guide. app/README.md is preserved upstream documentation and attribution, not the primary Vertex setup guide.

Baseline: pinned NexoPOS 6.2.4 commit 488573a38503940031a447ac2e5850ff76ca2047 plus current selected local changes at the migration snapshot. This repository starts new history; it is not an upstream clone or a backup of the installed database. Check Git status and read relevant source before assuming historical QA covers later edits.

Keep business logic and module interfaces stable. Make small changes through existing extension points. Use the module skill before module edits, version-specific framework documentation before Laravel edits, and narrowly scoped tests. Preserve legal provenance while keeping daily product identity Vertex POS / Saint Vertex.

Source is in app/; PHP/Node dependencies are restored there. Test credentials come from ignored .env.testing or a locally created ignored secrets file. Never print them in logs/reviews. Do not run feature suites until the dedicated vertex_qa fixture database exists. Do not point a fresh clone at the original installation's database.

The external original working installation, operational backups and automation tool binaries were deliberately left unchanged. Migration manifests and raw audit/scanner logs stay outside the repository. Future agents must not depend on those files or prior conversations for normal development.
