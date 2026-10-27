# Runtime verification follow-up — 2026-10-27

Resumed verification of the root application layout using the existing local `bams-validation:php82` Docker image. The application was mounted read-only, the container root filesystem was read-only, temporary data lived in a disposable `/tmp` filesystem, and external networking was disabled. No production configuration, database, meeting server, or deployment was used.

Executed and passed:

- PHP syntax lint for all 29 current PHP files, including the configuration example, CLI, and tests.
- `php tests/installer.php`: installer guards, private paths, setup keys, CSRF, overwrite refusal, lockout, and diagnostic redaction.
- `php tests/run.php`: actual PDO SQLite migration, authentication/ACL/session behavior, CRUD, generated/stable server IDs, signed URLs/XML, and audit redaction.
- `python3 tests/http_test.py`: all HTTP assertions passed against the root application and again against the extracted upload archive. These use PHP's development server and a local mock BBB server; they do not verify Apache access rules.
- `php tests/mariadb.php`: all integration assertions passed against a newly provisioned, initially empty `isolated_bams_test` database in the disposable container (MariaDB 11.8.6). This covers installer publication/lockout, password hashing, CRUD, ACL/session invalidation, last-admin rollback, upserts, integer pagination, and interrupted publication recovery. The test client initially failed with a TLS error; disabling TLS for its private Unix-socket administration connection resolved the harness issue before the successful run.
- `node tests/frontend.mjs`, syntax checks for all ten application JavaScript modules and both JavaScript test modules, and Python syntax checks for the HTTP suite/package builder.
- Package build into `/tmp/bams-resume-verification`: both archive names are byte-identical, ZIP integrity/allowlist checks pass, and all 69 packaged application files match the current root source.

PHP remains absent from the host PATH; the runtime results above were obtained in Docker. Firefox/browser rendering, Apache/LiteSpeed access-rule enforcement, MariaDB 10.6 specifically, and an actual cPanel installation remain unverified. The older records below describe what was available at their dates and are superseded by these results where applicable.

---

# Root layout update — 2026-10-26

The current 1.3.2 application has been moved from `src/` to the repository root, replacing the older extracted copy under `dist/bams-cpanel/`. Earlier paths and verification records below describe the historical layout. The current README documents deployment and database setup.

The CLI includes, PHP tests, frontend imports, HTTP/browser test servers, and package allowlist now use root application paths. Apache access rules block development/private folders, hidden paths, and configuration examples when the repository root is served. Private configuration remains outside the public application directory.

Executed checks: frontend helper tests passed; browser harness JavaScript syntax and Python tooling syntax passed; all 35 literal PHP include paths resolved; moved application/assets matched their prior contents except the intentional access-rule/configuration-comment edits. Both package names were built in a private temporary verification directory, checked for ZIP integrity, compared with the root deployment files, and confirmed identical. `git diff --check` passed. No `src/` or `dist/` directory remains in the working tree; an optional package build can recreate `dist/`.

PHP CLI, MariaDB, Apache/LiteSpeed, and Firefox are unavailable in this environment. PHP execution, database integration, Apache access-rule behavior, and an actual cPanel installation have not been verified for this restructuring. Prior source and generated output were preserved in a private temporary backup; temporary files are not a durable deployment backup.

---

# Verification status

Date: 2026-10-24. Current release: MariaDB/cPanel browser installer with optional existing SQLite support. No production migration, cPanel access, real DB connection, service/deployment change, live meeting operation, or Git operation was performed. The original `src/config.php` matches its pre-edit digest; contents and digest were not printed.

## Executed and passed

- `node tests/frontend.mjs`: helper regression tests pass for exact raw-value preservation (including brackets, Persian, whitespace, backslashes and reserved characters), case-insensitive tag lookup, text badge rendering, unsafe link rejection, and unchanged bytes passed to the XML parser. DOM/transport mocks are used; this is not real-browser XML/anchor verification.
- Node syntax checks pass for all ten application JavaScript modules and both `.mjs` test modules, including the new installer script.
- A locally installed Tree-sitter PHP WASM parser parsed all **27 PHP files under src/bin/tests**, excluding the real legacy config, without syntax-tree errors. This is a fallback static check, **not PHP lint/runtime execution**. It covers the new installer/configuration/schema and test files. `bootstrap.example.php` is covered by the documented PHP lint command, which remains unrun.
- Python syntax checks pass for `tests/http_test.py` and `tools/package-cpanel.py`.
- Python SQLite executed the six actual SQLite schema statements extracted from `src/lib/schema.php` in a temporary DB. Default empty ACL permission, unique usernames, foreign-key enforcement and rollback passed. **This is not PHP/PDO testing or MariaDB SQL execution.**
- `python3 tools/package-cpanel.py`: builds `dist/bams-cpanel.zip`; ZIP integrity and source allowlist checks pass. Public pages/assets/new installer are included; operational/legacy config, DB files, tests and CLI are excluded. No real credentials are included.
- Original configuration preservation was rechecked against the pre-MariaDB-change digest.

## Attempted or blocked

- `php tests/installer.php`, `php tests/mariadb.php` and earlier PHP lint/regression attempts: **PHP is not installed/on PATH** (`php: command not found`). PHP syntax lint, installer guard tests, PDO behavior, full join/auth/CRUD/migration regressions and MariaDB integration were not executed.
- No MariaDB test server/client/runtime or authorized disposable test database is available here. The new MariaDB suite requires an explicitly provided private test bootstrap, a dedicated initially empty database ending `_bams_test`, and pdo_mysql. It was not pointed at production.
- `python3 tests/http_test.py` previously skipped with exit 2 because PHP is absent. The updated suite also contains locked-installer GET/POST checks for existing SQLite installations, but none of its PHP/HTTP assertions have run. Private-data HTTP 404 verification remains unexecuted.
- `node tests/browser.mjs` previously attempted but the sandbox denied a localhost listener (`EPERM`). Firefox is installed, but the actual XML/model/anchor harness did not run. Browser smoke tests of the new setup-key download, login, monitoring, administration, mobile layout and Persian RTL remain unperformed.
- Fixture migration is documented and tested by code, **not demonstrated as successfully executed** without PHP. No real migration was run.
- No authorized live BBB join, Adobe integration, or real cPanel installation was verified.

Docker access was denied, rootless Podman could not use its runtime directory, and configured-proxy/direct DNS failed when earlier checked for runtime alternatives. No system packages/images were installed or upgraded and no system services were changed.

The new installer guards test missing/wrong setup keys, input/DSN validation, CSRF, private-file publication, overwrite refusal and durable lockout without connecting a DB. The MariaDB suite exercises actual installer initialization, password hashing, CRUD, session/ACL/status changes, last-admin rollback, upserts, native integer pagination, and recovery after interrupted private-config publication without resetting the administrator. These are **implemented tests pending execution**, not passing results.

Run the README's developer commands on a compatible isolated PHP/MariaDB host and verify the browser wizard before relying on this release. A generated upload archive is not evidence of a successful installation.

## Installer diagnostic update 1.3.1

A concrete reporting bug was corrected: private-directory checks previously threw a generic RuntimeException that hid the safe folder/access guidance. Setup now returns that guidance as a safe InstallerError. Database operations are wrapped with fixed step labels, validated SQLSTATE and numeric codes; raw exception messages/SQL are never shown or logged. Lock-release errors no longer replace an earlier failure. Tests in tests/installer.php include simulated PDO errors/redaction, stage wrapping, and missing-folder guidance. PHP execution remains unavailable; only static parsing and package checks ran for this patch. The user's actual cPanel failure remains undiagnosed pending a retry with the updated reporting.

## Automatic server IDs — 1.3.2

New administrator-created servers now receive backend-generated random IDs. New-server forms and connection tests require no manual ID; editing preserves the stored ID even if a replacement is submitted, and legacy migration preserves explicit original IDs. SQLite, MariaDB and HTTP suites were updated to exercise generated IDs, stability and migration compatibility. PHP remains unavailable, so these runtime assertions have not executed. PHP Tree-sitter/static JavaScript/Python checks and the generated source-package checks are run for this change; they do not substitute for PDO or browser tests.
