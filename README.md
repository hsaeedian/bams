# BAMS

PHP/Bootstrap monitoring for BigBlueButton and Adobe Connect, with server/user administration, auditing, and English/Persian UI. Upstream: [geraked/bams](https://github.com/geraked/bams). License: [MIT](LICENSE).

## cPanel installation: browser + MariaDB

**No terminal or SQLite is required.** Use [docs/cpanel-install.md](docs/cpanel-install.md) for the full walkthrough. A web-only upload archive is generated at `dist/bams-cpanel.zip`.

1. In cPanel **Database Wizard**, create a **new empty database** and its user. Assign that user privileges on that database. Use the full names including the cPanel prefix. This is done in cPanel, not by PHP; the installer does not need database/user creation privileges. See [cPanel Database Wizard](https://docs.cpanel.net/cpanel/databases/mysql-database-wizard/).
2. Upload/extract `bams-cpanel.zip` into `public_html/bams/` (or your chosen web directory). Its `index.php` and `install.php` go directly in that directory. Delete the uploaded ZIP after extraction.
3. Visit `https://YOUR-DOMAIN/bams/install.php`. An unconfigured application redirects there automatically.
4. Generate/download `setup.key` on that page, then upload it with cPanel File Manager into the **private folder displayed by the installer**, outside `public_html`. Create that folder with permissions 0700 and set the key file to 0600. Enable Show Hidden Files in File Manager. This one-time owner check prevents another visitor claiming your new site; it does not require code/configuration editing.
5. In the browser, enter MariaDB host/port/database/user/password, choose the first administrator's username/display name/password, confirm the application URL, and install.
6. Log in and add BBB/Adobe servers in Administration.

The installer saves database credentials privately, hashes the administrator password, and locks itself after installation. It cannot reset an existing administrator or overwrite an installed database. Its database check rejects unrelated/existing tables. There is no default administrator/password and no public PHP configuration editor. GET requests never run installation; POST requires a session CSRF token and private setup key before any database connection.

The setup key is generated with browser cryptographic randomness and never bundled with source. Passwords are not prefilled or echoed on errors. The setup key and pending file are removed after successful installation. You can additionally remove the public `install.php` after verifying the installation; that is an optional operator action, not a source deletion made here.

If installation reports a generic failure, see the [1.3.1 diagnostic update and File Manager troubleshooting steps](docs/installer-troubleshooting.md). It identifies inaccessible private folders and database failure steps/codes without revealing credentials.

## Requirements and directory layout

- **PHP 8.2+**, PDO MySQL (`pdo_mysql`), cURL, DOM/libxml, SimpleXML, sessions, JSON/hash/password support.
- **MariaDB 10.6+**, InnoDB, utf8mb4. The database/user must be provisioned in cPanel. Shared/nonempty databases are refused by the installer; use a dedicated BAMS database.
- HTTPS for passwords and sessions; outbound access to the configured BBB/Adobe servers.
- A PHP-writable private folder outside all public document roots. Public application files do not require write access.

Typical archive deployment:

```text
/home/ACCOUNT/public_html/bams/              public application: contents of src
/home/ACCOUNT/.bams-<site-id>/                private folder displayed in installer
  bootstrap.php                             MariaDB connection settings, 0600
  installed.lock                            durable setup lock
  .install.lock                             installation concurrency lock
```

The site ID is derived from the public application's absolute path. When moving to another filesystem location, the default private-folder name changes: preserve/restore the private configuration and use the new displayed location, or set an explicit `BAMS_PRIVATE_DIR`/`BAMS_BOOTSTRAP` through trusted hosting configuration. Do not re-run initial setup against an installed database. Paths are never selected by HTTP form fields.

For a full source checkout, serve **only `src/`**, keeping `bin`, tests, docs, and private data outside the document root. cPanel uploads may instead copy just `src`'s contents into a public folder; the archive does exactly that. The complete repository must not be uploaded into a public directory. The packaging script uses an explicit source allowlist and excludes the legacy `src/config.php`.

The cPanel installer automatically selects a private folder outside the ancestor `public_html`; in other layouts, the default is `<project>/config`. Existing private `<project>/config/bootstrap.php` files are recognized for backward compatibility. Advanced deployments may set `BAMS_BOOTSTRAP=/absolute/private/bootstrap.php`, or `BAMS_PRIVATE_DIR=/absolute/private/directory`. The web installer writes only the standard `bootstrap.php` basename. Existing configurations are never overwritten. A private bootstrap returns an array like [bootstrap.example.php](bootstrap.example.php); example database credentials are fictitious. Do not edit the old `src/config.php` to configure this version.

PHP must be allowed to access the private directory. If cPanel `open_basedir` blocks access, ask the provider to allow that private directory; the installer will not put secrets in `public_html` as a fallback. Some hosts disable PHP `link()`, which this installer uses to publish private files atomically without replacing existing files; the installer returns a sanitized error if so. Source trees must never be made broadly writable; do not use `chmod -R 777`.

MariaDB schema creation implicitly commits and cannot be rolled back ([MariaDB reference](https://mariadb.com/docs/server/reference/sql-statements/transactions/sql-statements-that-cause-an-implicit-commit)). The installer uses a private pending token and a database ownership marker, creates only its own initial schema, then inserts administrator/settings/audit in an InnoDB transaction. Same-site filesystem/advisory locks prevent concurrent setup; a database row lock serializes application writes and last-administrator decisions. Empty tables can remain after a failed DDL statement. Retry using the same database/user and private pending state. Never remove data as an automatic recovery action. If the administrator transaction committed before private bootstrap publication failed, retry publishes the configuration **without changing the administrator password**; use the original password.

## Administration and permissions

Administrator navigation provides Monitoring dashboard, Servers, Users, Settings, and Audit log. English/Persian localization and RTL styling remain supported, including installation. Tables scroll on small screens.

- **Servers:** list, create, edit, enable/disable, and explicitly confirm deletion. New server IDs are generated automatically on the backend when saved; no manual ID is needed. IDs remain stable on editing and legacy migration preserves original IDs. Configure display title, BBB/Adobe type, HTTP(S) origin/port, BBB API path/shared secret, or Adobe username/password. Private-network hosts are allowed; URL credentials, paths, fragments, and queries are rejected in server origins. Existing credentials are never prefilled/returned. Empty credential fields preserve stored values; nonempty values explicitly replace them.
- **Connection tests:** administrators only, POST + CSRF; test unsaved values using stored credentials when fields are empty. Three-second connect/ten-second request timeouts, TLS verification, no redirects. BBB reads `getMeetings`; Adobe authenticates and reads `common-info` (up to twenty seconds total). Tests never create, join, or end meetings, and never save configuration. Errors are sanitized.
- **Users:** unique case-sensitive usernames, display names, administrator/viewer roles, enable/disable, explicit all-server permission or selected-server ACLs, password resets, and confirmed deletion. New/reset passwords require 12–72 bytes. Hashes use `password_hash()`/`password_verify()`; plaintext user passwords are never stored in the new database or returned. Empty edit passwords preserve the hash.
- **Administrator access:** administrators always access every **enabled** server. Viewers with an empty selected-server list access none. The last active administrator cannot be deleted, disabled, or demoted. Every user edit invalidates existing sessions, including password/role/ACL changes; your own administrator edits require logging in again.
- **Settings:** application URL drives redirects, asset URLs, and secure-cookie behavior. Include your subdirectory. A wrong URL can break navigation; a trusted operator can restore `settings.app_url` using phpMyAdmin after taking a backup. No public SQL/configuration editor is provided.
- **Audit:** transactional records of actor username, action, target identifier, and UTC timestamp for administrative writes; updates cover credentials, status, roles and ACLs; password resets also have their own action. No passwords, secrets, request bodies, session IDs, or signed URLs are recorded. Paginated log; not a tamper-proof external security log.

Every authenticated request reloads the user's current enabled status, role, ACL, and authentication version. Disabled servers are excluded from monitoring, resource reads, and joins even for administrators. APIs return 401/403 and stop execution. Session cookie names are isolated per installation path; upgrades to this version require signing in again. Login regenerates session IDs; cookies use HttpOnly/SameSite, and Secure for HTTPS (or an HTTPS configured application URL). Forwarded headers are not trusted; use the correct HTTPS URL for a TLS-terminating proxy. Login limits ten failures per address/username per fifteen minutes. Login, logout, administration and installation changes require POST/CSRF; GET logout is rejected.

## BBB join URL correction

Both join URL paths now share `completeUrl()`, which accepts raw arrays and calls `http_build_query(..., PHP_QUERY_RFC3986)` once ([PHP reference](https://www.php.net/manual/en/function.http-build-query.php)). The checksum covers the method, those exact serialized bytes, and the secret; only the checksum is appended afterward. `[0]` becomes `%5B0%5D`; `[12]`, spaces, Persian names, backslashes, and reserved `& + = % ? #` values survive exact decoding. Input values are not HTML-escaped, stripped, or trimmed as a substitute for encoding.

XML uses text nodes. Frontend requests use `DOMParser` in XML mode, case-insensitive tag lookup where needed, and direct anchor assignment without entity repair or double escaping. Dashboard meeting lookup compares attributes instead of interpolating IDs into CSS selectors, so brackets/quotes do not break it.

Both monitoring-generated links and `api/join.php` enforce authentication and ACLs. The standalone endpoint derives moderator credentials from BBB and uses the saved display name, ignoring browser-supplied passwords/names. The previous moderator join behavior is preserved: a BAMS viewer role is an administration permission, not a BBB attendee role. Raw attendee/moderator password fields are removed from monitoring XML. Signed URLs still inherently grant meeting access: never cache/log/share response bodies. Application responses use no-store/no-referrer.

Adobe Connect remains supported, using safe POST encoding, [in-memory cookie sessions](https://helpx.adobe.com/adobe-connect/webservices/login-sessions-user-handle-requests.html), bounded verified TLS, and safe XML parsing. Optional resource monitoring reads the existing `/bams.php` (BBB) or `/bams` (Adobe) helper. No remote helper, BBB server, services, firewall, or web-server configuration was changed.

## Existing SQLite and legacy installations (advanced/optional)

The browser installer is for **fresh MariaDB setup**, not conversion of an existing SQLite/legacy installation. Existing SQLite bootstrap files and databases remain supported, requiring `pdo_sqlite` instead of `pdo_mysql`. Do not delete an installed SQLite database to switch engines; retain it and plan an explicit reviewed data migration. No data-engine conversion has been run here.

The earlier legacy configuration used `SERVERS`/`USERS` constants and plaintext passwords; it is never loaded by HTTP requests. Its optional CLI import remains available for operators who have CLI access. You do not need this path for a new cPanel installation. With a private bootstrap and an empty destination prepared, choose exactly one existing legacy username as initial administrator:

```sh
php bin/bams.php --migrate=/absolute/legacy/src/config.php --admin=chosen-existing-username
```

Run only against an explicitly authorized trusted PHP config (PHP config is executable code). The original file is not changed, output is suppressed, server IDs/connection fields/names/ACLs are imported, and plaintext user passwords become hashes. Only the selected user becomes administrator; others become viewers. Missing/empty legacy ACL means explicit all-server permission; nonempty ACLs remain selected lists. Unknown IDs/duplicate usernames fail instead of granting access. Bare server hosts become HTTPS origins; inspect legacy Adobe HTTP installations. Repeat import into initialized data is refused.

Import data is transactional. SQLite can roll back its initial schema too; MariaDB schema is created before the data transaction and may remain empty after failure. Preserve the source file and destination before retrying. No real legacy migration was performed here.

Fake fixture demonstration for a developer with PDO SQLite (not required on cPanel):

```sh
umask 077
BAMS_DEMO_DIR=$(mktemp -d /tmp/bams-demo.XXXXXXXX)
printf '<?php return ["database" => "%s/demo.sqlite"];\n' "$BAMS_DEMO_DIR" > "$BAMS_DEMO_DIR/bootstrap.php"
BAMS_BOOTSTRAP="$BAMS_DEMO_DIR/bootstrap.php" php bin/bams.php --migrate="$PWD/tests/fixtures/legacy.php" --admin=owner
```

Fresh advanced/CLI initialization is also retained with `--init --admin=NAME --url=https://bams.example.invalid/ --password-stdin`. Supply a 12–72 byte password on stdin, never as a command argument or committed file; `--init` refuses initialized destinations. The web installer replaces this requirement for normal cPanel use.

After an authorized legacy migration and verification, remove the **unused public `src/config.php`** from the new deployment/source synchronization. Preserve a matching private encrypted rollback copy outside the web root. If credentials were previously committed, remove them from your local Git index/history using your own reviewed process and rotate exposed secrets. `.gitignore` does not untrack existing files. The original configuration in this working copy remains unchanged.

## Backup, restoration and upgrades

Recoverable database/BBB/Adobe credentials are not application-encrypted. Their protection depends on private filesystem permissions, host security, HTTPS, and encrypted/private backups. Database readers can recover server credentials; user passwords are one-way hashes. There is **no encryption key** to back up. Encoding or keeping a key beside the data would not provide meaningful protection.

For MariaDB, use cPanel Backup or phpMyAdmin to make a consistent InnoDB export/snapshot, following your hosting provider's backup procedure. Keep SQL exports outside public directories and source synchronization. Back up private bootstrap/lock separately. For SQLite, use its backup facility or stop all writes before copying; include journal/WAL sidecars when using storage snapshots. Never copy only an active SQLite file without a consistency strategy.

Restore matching source, database and private bootstrap/lock with proper ownership and 0700/0600 permissions; verify login/ACLs before reopening writes. Source alone does not restore users, settings, credentials or audit history. Before upgrades retain a consistent backup and matching old source, test an isolated copy, and preserve the private configuration. This release has no automatic startup data/schema migrations. Future schema upgrades require an explicit versioned migration. Rollback restores the corresponding source/database/bootstrap set using your normal deployment procedure; the original legacy application needs its old private config and cannot read this database.

## Developer verification and packaging

Tests create private temporary configuration and fake servers; they do not load real config or contact real BBB/Adobe servers. No packages are installed or upgraded automatically. Supply PHP with the required extensions. Node 20+/Firefox and Python 3 are optional developer tools, not cPanel runtime requirements.

```sh
find src bin tests -name '*.php' ! -path 'src/config.php' -exec php -l {} \;
php -l bootstrap.example.php
php tests/installer.php                # setup-key, input, CSRF, lock, private-file guards
php tests/run.php                      # isolated SQLite CRUD/auth/join/migration regressions
node tests/frontend.mjs                # frontend helper tests with mocks
python3 tests/http_test.py             # temporary PHP/SQLite/mock-BBB HTTP server
node tests/browser.mjs                 # actual Firefox XML/model/anchor test
python3 tools/package-cpanel.py        # build/inspect web-only dist/bams-cpanel.zip
```

MariaDB integration uses a **separate disposable database**, never production. A developer must provision a dedicated empty DB ending in `_bams_test` and put its fictitious/test connection details in a private bootstrap outside source, using the format of `bootstrap.example.php`:

```sh
BAMS_TEST_MYSQL_BOOTSTRAP=/absolute/private/test-bootstrap.php php tests/mariadb.php
```

That suite refuses nonempty/non-test destinations, installs through the real installer service, tests MariaDB CRUD/passwords/sessions/ACLs/last-admin protection/settings/login upserts/native pagination, tests publication recovery without password resets, then drops only its known tables in the explicitly selected test database. Do not point it at a real deployment. The normal SQLite regression suite remains separately available so both engines can be checked.

`PHP_BIN=/path/php python3 tests/http_test.py` and `FIREFOX_BIN=/path/firefox node tests/browser.mjs` select alternate runtimes. Local HTTP listeners must be allowed for HTTP/browser tests. [docs/verification.md](docs/verification.md) distinguishes executed static checks from unperformed PHP/MariaDB/browser tests. No real BBB join or cPanel installation is claimed verified.

## Source download and Git boundaries

Review [docs/change-manifest.md](docs/change-manifest.md) and [docs/mariadb-change-manifest.md](docs/mariadb-change-manifest.md) for project-relative changes. No original files were renamed/deleted. No GitHub authentication/remotes/clone/commits/pushes/PRs were used. Download source for PyCharm review; run the runtime tests on an isolated compatible host before your own commit/deployment.

Exclude **from Git and ordinary source synchronization**: real `src/config.php`/legacy copies; private `bootstrap.php`; `.bams-*` private directories; `setup.key`; `install.pending.php`; installation locks; `var/`; all SQLite data/sidecars; MariaDB SQL exports; backups; logs; sessions; `.env*`; test runtime files. `dist/` is a generated upload artifact, not operational data or committed source. The fake fixtures and bootstrap example are distributable. cPanel backups/exports contain secrets and must never be placed in the public upload ZIP. `.gitignore` is only a convenience; review already tracked files yourself.
