# Saint Vertex agent handoff

Read README.md and .saint-dev/HANDOFF.md before work, then the relevant architecture/decisions/status notes. Codex and Antigravity share the same source of truth here; record meaningful decisions rather than prompt transcripts.

- Product Vertex POS; company Saint Vertex. Preserve the original upstream/dependency licenses, ownership and attribution. Copyright for original Saint Vertex work is Copyright © 2026 Saint Vertex.
- The original working installation is external to this source snapshot. Do not reset, clean, reinitialize, modify or run migrations against it without explicit task authorization. Never copy its .env, operational database, uploaded media, secrets or backups into this repository.
- Read app/AGENTS.md for Laravel conventions. For module work, read app/.agents/skills/create-nexopos-module/SKILL.md and the applicable module references. Scope shell commands to app/ where framework commands expect it.
- Preserve current customizations and intentional deleted/renamed build assets. Do not restore upstream files simply because they differ from the pinned baseline.
- No cloud integration, paid modules, business workflow redesign or packaging unless explicitly requested. Bind development servers to 127.0.0.1.
- Use dedicated disposable databases. Vertex fixtures expect vertex_qa and a vertexadmin administrator. Never run the destructive upstream suite on a working business database.
- Run focused tests, report actual results and distinguish historical evidence. A passing migration or dependency install does not prove checkout, tax or stock behavior.
- Commit source and lockfiles, not dependencies, local runtimes, logs, reports, browser auth state or secrets. Keep the verified current public/build assets unless intentionally rebuilding frontend changes.
- Do not enable copied upstream CI/release/cloud workflows automatically. No such workflow is part of this snapshot.
