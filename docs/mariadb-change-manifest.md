# MariaDB/browser-installer change manifest

Incremental project-relative changes after the earlier SQLite/admin implementation. See change-manifest.md for the earlier phase. This inventory does not use Git.

## Modified

- `.gitignore`
- `README.md`
- `bin/bams.php`
- `bootstrap.example.php`
- `docs/verification.md`
- `src/api/login.php`
- `src/js/language.js`
- `src/lib/bootstrap.php`
- `src/lib/migrate.php`
- `src/lib/store.php`
- `src/meta.php`
- `tests/http_test.py`

## Added source

- `docs/cpanel-install.md`
- `docs/mariadb-change-manifest.md`
- `src/install.php`
- `src/js/install.js`
- `src/lib/configuration.php`
- `src/lib/installer.php`
- `src/lib/schema.php`
- `tests/installer.php`
- `tests/mariadb.php`
- `tools/package-cpanel.py`

## Generated upload artifact

- `dist/bams-cpanel.zip` — public app contents, installer and INSTALL.txt; no real config, DBs, CLI or tests. Generated with tools/package-cpanel.py, ignored by Git.

## Deleted / renamed

None. In particular, the original `src/config.php` was not changed, renamed, deleted or packaged. Downloading this update by SFTP does not require deleting/renaming original source files. After verification, optionally remove public install.php from your deployed copy; this is an operator action, not a source deletion made here.

## Deployment and privacy

For a new cPanel install upload the generated ZIP, extract into your web directory, create a dedicated MariaDB database/user in cPanel, and open install.php. The page describes the one-time private setup-key upload and database/admin forms. No terminal or SQLite setup is required. Existing installations are locked and are not converted/reset automatically.

Never synchronize or commit private bootstrap.php, .bams-* directories, setup.key, install.pending.php, installed.lock, .install.lock, real legacy config, database exports/files/sidecars, backups, logs, sessions or environment files. The ZIP uses a source allowlist rather than archiving the workspace. Keep SQL exports and uploaded ZIPs out of the public directory after use.

The private connection bootstrap stores recoverable MariaDB credentials outside public_html. User passwords are hashes. No credentials, token, DB contents, live migration or real server access was used during development. No GitHub account/Git operations were performed. Review source in PyCharm and run the pending PHP/MariaDB/browser suites before your own commit/deployment; see verification.md.

## Installer diagnostic patch 1.3.1

Modified public source: `src/install.php`, `src/lib/installer.php`, `src/meta.php`. Updated `tests/installer.php`, `README.md`, this manifest and `docs/verification.md`; added `docs/installer-troubleshooting.md`. No deletions or renames. Generated `dist/bams-installer-fix-1.3.1.zip` contains exactly the three public source files, at public application-relative paths. Complete `dist/bams-cpanel.zip` is rebuilt with the same changes. Private setup/configuration/database state is excluded and must be preserved during extraction.

## Automatic server IDs — 1.3.2

Modified: `src/admin.php`, `src/lib/store.php`, `src/lib/migrate.php`, `src/js/language.js`, `src/meta.php`, `tests/run.php`, `tests/mariadb.php`, `tests/http_test.py`, `README.md`, `docs/verification.md`, and this manifest. No deletions or renames. Rebuilt the full cPanel ZIP and generated `dist/bams-auto-server-id-1.3.2.zip` with the five changed public source files. No database migration or setup rerun is needed. Existing server IDs, credentials, ACLs and private installation data are unchanged.
