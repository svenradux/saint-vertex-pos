# Vertex POS — Saint Vertex

Private source repository for the current customized local Saint Vertex POS. Core baseline: NexoPOS v6.2.4, commit `488573a38503940031a447ac2e5850ff76ca2047`. This is a clean source snapshot, not an installer or database backup.

Application source is in `app/`. Current branding is in `app/modules/SaintVertex/`; original emblem source is in `branding/`. Frontend source and the currently installed prebuilt assets are retained together. Root PHP/browser helpers are current source utilities. The existing machine's bundled runtime and Start/Stop scripts remain in that installation; they are deliberately not presented as a portable stack here.

## Development prerequisites

Use PHP 8.4 (tested local version 8.4.26), Composer 2, Node 24, npm, and a separately configured local MariaDB/MySQL instance. The prior local stack used MariaDB 11.4.10. `composer.lock` is authoritative for PHP/platform requirements; extensions include curl, gd, mbstring, intl, zip, PDO MySQL, DOM/XML, fileinfo, openssl and tokenizer. Run `composer check-platform-reqs` after restoration.

## Restore dependencies (PowerShell)

From the repository root:

```powershell
Set-Location app
composer install --prefer-dist --no-interaction --no-scripts
npm.cmd ci
composer check-platform-reqs
```

`--no-scripts` intentionally avoids upstream post-install hooks before a fresh local environment/database is configured. Do not run composer update or npm update as a restoration step. Installed dependencies stay untracked.

## Configure a fresh, isolated development instance

1. Copy `app/.env.example` to `app/.env`; configure a new local database and least-privilege account with a unique password. Defaults identify `vertex_dev`, not the existing business store. Keep the database bound to localhost. Never copy the original installation's .env, APP_KEY or operational data for this setup.
2. Ensure writable `app/storage/app/public`, `app/storage/framework/cache/data`, `app/storage/framework/sessions`, `app/storage/framework/views`, `app/storage/framework/testing`, `app/storage/logs`, and `app/bootstrap/cache` directories exist. Git placeholders preserve these directories. Create root `logs/` only when using root smoke helpers.
3. From `app/`, run `php artisan package:discover --ansi` and `php artisan key:generate`. Run `php artisan serve --host=127.0.0.1 --port=8074` for local development. Use an unused port if another instance already uses 8074 and update APP_URL and smoke-helper base URL accordingly.
4. Open the local setup wizard and initialize **only the new empty database**. Create your own secure administrator; use username `vertexadmin` if planning to use the maintained Vertex fixtures. The setup workflow owns installation/schema/seed steps; never import business data or run a destructive test installer against a live database. Keep locale Indonesian, currency IDR and timezone Asia/Jakarta where supported; tax rates are explicit store configuration, not invented legal defaults.
5. Once installation is complete, run `php artisan storage:link`, `php artisan ns:translate --symlink --force` and `php artisan modules:symlink`. These recreate excluded public asset links/junctions from the selected source. Verify `public/lang` points to `lang`, and `public/modules/saintvertex` to `modules/SaintVertex/Public`.
6. In a second terminal, from `app/`, run `php artisan schedule:work` while the instance is in use. Stop it when stopping the local web runtime. Sync queue mode requires no separate Redis/worker service. The first heartbeat can take up to one minute.

The checked-in assets allow use without rebuilding. To intentionally rebuild frontend changes, run `npm.cmd run build` from app/ and review asset replacements/deletions with the manifest. To refresh branding dictionaries, run `php Refresh-VertexBranding.php` from the repository root after configuring that instance.

Internet is required to restore missing dependencies. Once dependencies and assets are installed locally, normal local POS operation does not require cloud services. Marketplace/OAuth, update checks and promotional requests are disabled by the Saint Vertex local module/guards.

## Tests

The standalone coupon eligibility test has no database dependency:

```powershell
Set-Location app
node --experimental-strip-types --test ts-tests/coupon-eligibility.test.ts
```

For PHP feature tests, provision a **separate disposable** `vertex_qa` database with the application schema, seed/default units, permissions and a `vertexadmin` administrator. Use the normal installation workflow on that isolated database; no database dump or account password is included. Copy `.env.testing.example` to `.env.testing`, set only that QA account's connection details and a fresh application key, and reflect the isolated installation's version marker. Never set QA credentials to a business database.

```powershell
php vendor/bin/phpunit --configuration phpunit.vertex.xml
```

That safe config selects health/branding tests and forces the QA database name. Existing refund/triage tests can be selected explicitly using that config after their fixture needs are met. Some tests create business fixtures and must never use a live store. The upstream `phpunit.xml` is retained for reference and uses different historical fixture assumptions; it is not a claim that the full upstream suite passes. `npm test` also has inherited Jest configuration/dependency limitations.

For root `qa-operational-health.mjs` or `qa-brand-copyright.mjs`, use a disposable local instance on 127.0.0.1:8074, create `logs/`, and copy `secrets/local-credentials.example.json` to ignored `secrets/local-credentials.json` with that instance's own login. Chrome is required. These scripts assume Indonesian copy and an empty cart. Never commit credentials, browser storage state, screenshots or logs.

## Data, maintenance and licensing

Operational data belongs in the separately configured database and `storage/app/public`; it is absent from this repository. Before maintaining a real store, take its normal consistent database/media/environment backup using that installation's private backup procedure. Dependency restoration and source cloning do not restore business data.

Keep source and database backups separate. Source provenance and collaboration guidance are in `.saint-dev/`. Core GPL-3.0 text and upstream attribution remain in `app/LICENSE` and `app/README.md`; dependency and bundled font/icon notices remain intact. The existing upstream Composer MIT metadata conflicts with that GPL declaration and has not been rewritten. No exclusive ownership of upstream code is claimed.
