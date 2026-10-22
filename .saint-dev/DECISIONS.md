# Decisions

1. Use a new initial source snapshot rather than importing detached inherited history. Preserve the exact upstream commit and all current selected customization bytes; intentionally absent old build files are not restored.
2. Retain lockfiles, prebuilt bundles, fonts/icons, original emblem and runtime emblem copy. Their size is justified by current UI/offline behavior, not treated as disposable solely because they are generated or duplicated.
3. Exclude vendor/node_modules, runtimes, databases/uploads, secrets, caches, machine configs, old AI outputs/reports/automation tools, backups, obsolete plans and inherited deployment/CI workflows. This is exclusion from migration, never deletion from the working installation.
4. Root .gitignore and credential-free environment/testing templates replace only staged configuration templates. All application/module/calculation source files remain copied verbatim. The safe PHP test config is new migration support, not a production behavior change.
5. Keep upstream GPL/source attribution, dependency notices and icon notices. Saint Vertex copyright identifies its original additions. The upstream README/LICENSE vs Composer license metadata inconsistency remains documented, not silently relicensed. Line Awesome's bundled notice requests its link when used publicly.
6. Provision fresh local dependencies and disposable test databases. No test or dependency-restore step may change the original installation or reuse its operational data. No packaging, installers or public hosting is part of migration.
