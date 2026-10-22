# Architecture

- app/: Laravel 12 / PHP backend, models, permissions, migrations, API/web routes and application services.
- app/resources/ts/: Vue 3/TypeScript POS and dashboard frontend. app/public/build/manifest.json describes the preserved current Vite bundles; deleted predecessor bundle names stay absent.
- app/modules/SaintVertex/: narrow module for local branding, auth/dashboard/layout overrides, display dictionaries, browser metadata, About and mail presentation, cloud-route guards and theme behavior. Internal upstream keys/namespaces are retained.
- app/lang/ and module Resources/Lang + Public/lang/: native keys plus branding overlays; Refresh-VertexBranding.php refreshes frontend dictionaries without blind internal renames.
- app/app/Services/CoreService.php: local health compatibility changes recognize NTFS junction targets and effective NTFS read/write/delete access. Sync queues use verified cron activity instead of a missing worker heartbeat.
- Existing core patches include product-barcode metadata retention, saved-tax handling for net-price refunds and a refund-receipt Currency import. Later current edits also address payment normalization/status/partial-payment checks, stock-queue rejection and coupon eligibility. Preserve these changes; validate them on disposable fixtures before changing them further.
- Assets and dependencies are served locally. A local scheduler runs native scheduled tasks; sync jobs run inline. No cloud marketplace, telemetry/update service, packaged database or runtime belongs in the repository.
