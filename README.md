# BAMS 1.3.2

Monitor BigBlueButton and Adobe Connect, manage servers and users, and control access through an English/Persian interface with RTL support.

Fork: [hsaeedian/bams](https://github.com/hsaeedian/bams) · Upstream: [geraked/bams](https://github.com/geraked/bams) · [MIT license](LICENSE)

## Hosting-ready project layout

The current application lives directly in the repository root. It replaces the old `src/` layout and the extracted application previously kept in `dist/bams-cpanel/`. You do not need to build a ZIP to use this version.

```text
bams/
  index.php                 Monitoring dashboard
  install.php               Browser installer
  admin.php                 Servers, users, settings, and audit log
  login.php, logout.php     Authentication
  meta.php                  Application version
  .htaccess                 Directory and development-file access rules
  api/, lib/                Application services
  css/, js/, fonts/         Frontend assets
  images/, plugins/         Images and bundled frontend libraries
  bin/, tests/, tools/      Optional development and CLI utilities
  docs/                     Installation and technical documentation
```

`dist/` is optional generated packaging output, not the application source. Database credentials and installed data live outside the public application directory.

## Features

- Browser-based MariaDB installation on cPanel, without a hosting terminal, SQLite, SQL import, or editing PHP credentials.
- Server administration with automatically generated IDs, enable/disable controls, and read-only BBB/Adobe connection tests.
- Administrator/viewer roles, per-server permissions, hashed passwords, session invalidation after user edits, and protection for the last active administrator.
- Application URL settings and a paginated audit log for administrative changes.
- Correct BBB join URL encoding for bracketed IDs, Persian names, spaces, and reserved characters.
- English/Persian installation, administration, and monitoring interfaces.
- Installer diagnostics for private-folder access and database failures without displaying credentials.

## Requirements

- PHP **8.2+** with PDO MySQL (`pdo_mysql`), cURL, DOM/libxml, SimpleXML, sessions, JSON, hash, and password support.
- MariaDB **10.6+**, InnoDB, and utf8mb4.
- HTTPS and outbound access to your BBB/Adobe servers.
- Apache/LiteSpeed with `.htaccess` support and `mod_rewrite` for the supplied access rules. Other servers need equivalent rules before serving the repository root.
- A PHP-writable private folder outside `public_html` and every public document root.

## Choose your hosting environment

BAMS uses the same public files and browser installer on each host. The panel name does not guarantee compatibility: confirm the PHP extensions, MariaDB version, HTTPS, private-folder access, and web-server rules above.

| Environment | Where to create the database | Typical upload location |
| --- | --- | --- |
| cPanel | Database Wizard / Manage My Databases | `/home/ACCOUNT/public_html/bams/` |
| DirectAdmin | User Level → MySQL Management / Databases | `/home/ACCOUNT/domains/DOMAIN/public_html/bams/` |
| Plesk | Websites & Domains → Databases | The domain's document root, often `httpdocs/bams/` |
| Other shared hosting | The provider's database-management page | The public document root shown by the provider |
| VPS / dedicated server | Provision a dedicated MariaDB database and user yourself | A document root configured in Apache or Nginx |

Upload the public application files listed in the cPanel walkthrough below, then open `https://YOUR-DOMAIN/bams/install.php`. All environments use the same database/admin fields. Use the actual database host, port, full database name, and username supplied by the host. A menu labelled “MySQL” can manage MariaDB, but confirm the actual engine/version with your provider. This release's documented database requirement is MariaDB 10.6+; other engines are not claimed tested.

### DirectAdmin

1. Select the domain and choose PHP 8.2+ with the required extensions using the options your provider exposes.
2. At **User Level → MySQL Management**, choose **Create new Database**. Create a dedicated empty database and its user; save the full names and password. Ensure that user has privileges on this database. [DirectAdmin database guidance](https://docs.directadmin.com/other-hosting-services/mariadb-mysql/phpmyadmin.html).
3. Upload the deployment files into the domain's HTTPS document root, for example `domains/DOMAIN/public_html/bams/`. Check whether HTTPS uses `public_html` or a separate `private_html`; do not place the application in the wrong web root. Some DirectAdmin installations link `private_html` to `public_html`. [DirectAdmin private_html behavior](https://docs.directadmin.com/changelog/version-1.650.html).
4. Open `/bams/install.php`. In File Manager, create the **exact private folder shown by the installer**, usually `domains/DOMAIN/.bams-<site-id>/`. Both `public_html` and `private_html` are public web folders; neither is a place for credentials or `setup.key`.
5. Upload `setup.key` into that private folder (folder 0700, key 0600), then enter the database connection and new BAMS administrator details and finish installation.
6. On Apache or compatible LiteSpeed hosting, include `.htaccess`. For Nginx-only or OpenLiteSpeed hosting, ask the provider to configure equivalent access rules; do not assume the supplied Apache directives are enforced.

### Plesk

1. Open **Websites & Domains → Databases**, add a dedicated MariaDB database, and create a database user for that database. Record the connection details and ensure the user can create tables and read/write BAMS data. [Plesk database setup](https://docs.plesk.com/en-US/obsidian/administrator-guide/website-management/website-databases/creating-databases.65157/).
2. Select PHP 8.2+ with the required extensions for the domain. Upload the public application files to its configured document root, for example `httpdocs/bams/`.
3. Open `/bams/install.php`, create the displayed private folder **outside both `httpdocs` and `httpsdocs`**, upload the setup key, and enter the database/admin fields. Ask the provider to permit PHP access to that folder if `open_basedir` blocks it.
4. Confirm which web server handles the site. Apache rules require `.htaccess` support; an Nginx-only setup needs equivalent server configuration.

### Other panels and VPS installations

Create an empty MariaDB database and a separate user with privileges limited to that database. Configure PHP 8.2+ and HTTPS, upload only the runtime files, and use the browser installer. No SQL import is needed. On a VPS, the server administrator provisions the database/user before running the installer; BAMS does not need global database-creation permissions.

The installer recognizes `public_html`, `private_html`, `httpdocs`, `httpsdocs`, and `htdocs`, and selects a private folder beside the public folder. With a custom document root it uses the server's configured `DOCUMENT_ROOT` to select an external folder. Always follow the displayed path. Trusted server/PHP-FPM configuration can override it with `BAMS_PRIVATE_DIR` or `BAMS_BOOTSTRAP`; these are not form fields or public configuration files.

For Nginx or another server that ignores `.htaccess`, configure equivalent blocks for hidden paths, `bin/`, `tests/`, `tools/`, `docs/`, `dist/`, `config/`, `var/`, `backups/`, `sessions/`, and direct requests to `lib/`. Also deny direct requests for configuration examples, setup files, and legacy `config.php`. Continue allowing the public PHP entry points and frontend assets. The PHP built-in server is for isolated local development, not production hosting.

## Install on cPanel and set up the database

### 1. Create a database and database user

In cPanel, open **Database Wizard** (your host may label it **MySQL Database Wizard**). See the [official cPanel database wizard instructions](https://docs.cpanel.net/cpanel/databases/mysql-database-wizard/):

1. Create a **new, empty database**, for example `bams`.
2. Create a database user, for example `bamsuser`, with a strong password. Save that password privately.
3. Add that user to the new database and select **ALL PRIVILEGES for this database only**.
4. Note the complete database name and username displayed by cPanel, including the account prefix.

For example, if the cPanel account is `myaccount`, the full names might be `myaccount_bams` and `myaccount_bamsuser`. Use the actual names cPanel displays. Do not use a database containing WordPress or another application's tables; the installer requires a dedicated database.

### 2. Upload the application

In cPanel **File Manager**, create `public_html/bams/` and upload the application's root PHP files, `.htaccess`, and these folders:

```text
api/  lib/  css/  js/  fonts/  images/  plugins/
```

`index.php` and `install.php` must be directly inside `public_html/bams/`. Enable **Show Hidden Files** so `.htaccess` is included. The application does not need `bin/`, `tests/`, `tools/`, `docs/`, `.git/`, `.idea/`, or `bootstrap.example.php` on the web host. The optional package builder below selects only deployment files for you.

### 3. Prepare the private setup folder

Open `https://YOUR-DOMAIN/bams/install.php`:

1. Click **Generate and download setup.key** and keep the installer page open.
2. In File Manager, create the exact private folder displayed by the installer, usually `/home/YOUR-ACCOUNT/.bams-<site-id>/`, outside `public_html`.
3. Set that folder's permissions to **0700**. Upload `setup.key` into it and set the file's permissions to **0600**.

The private folder must be accessible and writable by PHP. If the provider's `open_basedir` or ownership settings prevent access, ask the provider to allow that private folder. Public application files do not need write access.

### 4. Enter the database settings in the installer

Return to the installer and fill in:

| Field | Example | What to enter |
| --- | --- | --- |
| Database host | `localhost` | Your hosting provider's database hostname. |
| Database port | `3306` | The provider's MariaDB port. |
| Database name | `myaccount_bams` | The full database name from cPanel. |
| Database username | `myaccount_bamsuser` | The full user assigned to that database. |
| Database password | Your saved password | The database user's password from step 1. |
| Administrator username | `owner` | The new BAMS login username. |
| Administrator display name | Your name | The name shown in BAMS. |
| Administrator password | A new strong password | 12–72 bytes; confirm it in the second field. |
| Application URL | `https://YOUR-DOMAIN/bams/` | The public HTTPS address, including the subdirectory. |

The database account connects BAMS to MariaDB. The administrator account is your BAMS login; create its password separately. The values above are examples, not default credentials.

Click **Install BAMS**. The installer creates its own tables and saves the database connection settings in private `bootstrap.php`. There is no SQL file to import and no public `config.php` to edit. It hashes the administrator password, removes the setup key and pending file on success, and locks installation.

### 5. Log in and add your servers

Follow the login link and use the administrator account you just created. In **Administration → Servers**, add your BigBlueButton or Adobe Connect connection details. Server IDs are generated automatically. Add other users and their server permissions under **Users**.

Full walkthrough: [cPanel installation](docs/cpanel-install.md). For installation errors: [installer troubleshooting](docs/installer-troubleshooting.md).

## Database configuration and private files

A typical cPanel deployment uses:

```text
/home/ACCOUNT/public_html/bams/       Public application files
/home/ACCOUNT/.bams-<site-id>/        Private installation directory
  bootstrap.php                      Database settings (0600)
  installed.lock                     Durable installer lock
  .install.lock                      Installation concurrency lock
```

Do not upload credentials, SQL exports, backups, or setup files into the public application directory or Git. The installer never creates the database/user itself; you provision both in cPanel first. No default administrator password is bundled.

The default private-folder name depends on the application's absolute filesystem path. Moving an installed application can change that name. Preserve its private configuration and use the location displayed at the new path, or set `BAMS_PRIVATE_DIR` / `BAMS_BOOTSTRAP` through trusted hosting configuration. Do not rerun setup against an installed database.

On supported hosting layouts the private directory is selected as described above. The CLI fallback is `config/` beside the application directory, not inside it. Existing private bootstrap files at that location remain recognized only when outside the public web roots. Advanced configuration uses the array format in [bootstrap.example.php](bootstrap.example.php); operational values belong only in the private bootstrap. Existing SQLite installations remain supported with `pdo_sqlite` and a private database path, but the browser installer creates fresh MariaDB installations only.

If installation fails, retry with the same database/user and existing private setup state. MariaDB table creation can leave empty tables after a failure. If the administrator was already saved, retry completes configuration publication without resetting its password. Keep the original password. Do not drop tables or delete private locks as a troubleshooting step.

## Upgrade an existing deployment

Back up the database and matching private `bootstrap.php`/lock before replacing public application files. Retain the application's existing public URL and filesystem path where possible. If deploying from an old checkout that served `src/`, map the new root application files to the **same public application directory**. There is no longer a `src/` folder to serve.

Copy only the deployment files listed above, preserving private configuration, database, and server credentials. Do not run the fresh installer again. The structural change does not change the database schema. Sign in again after updating and check server access and permissions.

The old public `src/config.php` is unused by this version and is not included in the new layout. Keep any needed legacy configuration only in a private backup. Existing SQLite or legacy constant-based installations need a planned migration to switch to MariaDB; uploading files does not convert their data.

Optional trusted CLI import, with an empty destination and private bootstrap prepared:

```sh
php bin/bams.php --migrate=/absolute/private/legacy-config.php --admin=existing-username
```

The import hashes legacy user passwords, preserves server IDs and permissions, and selects exactly one existing user as administrator. The input PHP file is executable code; use only a trusted private file. Initialized destinations are refused.

## Administration and backups

Administrators can access every enabled server. Viewers receive explicit all-server access or a selected-server list; an empty selected list grants no access. Disabled servers cannot be monitored or joined. User edits invalidate sessions, including your own when editing your administrator account. The last active administrator cannot be disabled, deleted, or demoted.

Connection tests do not save configuration or create/join/end meetings. Empty credential fields on edits preserve existing secrets. Stored secrets are never prefilled. Signed meeting links grant access; avoid logging or sharing them.

Use cPanel Backup or phpMyAdmin for consistent MariaDB backups, and back up the matching private bootstrap/lock separately. SQL exports contain recoverable server credentials and must stay private. User passwords are hashed; database and BBB/Adobe credentials are protected by filesystem permissions, HTTPS, and private/encrypted backups rather than application encryption. Restore matching source, database, and private settings together. For SQLite, use its backup facility or stop writes before copying the database and any sidecars.

## PyCharm “Unversioned Files”: what to keep

“Unversioned” means a local file has not been added to Git. It does **not** mean the file is unused. After moving files from `src/` to the root, Git shows the old paths as deleted and the new paths as unversioned until you stage them together.

| Files | Keep in the repository? | Needed on the web host? |
| --- | --- | --- |
| Root application PHP files, `.htaccess`, `api/`, `lib/` | Yes | Yes; these contain the installer, authentication, dashboard, and data layer. |
| `css/`, `js/`, `fonts/`, `images/`, `plugins/` | Yes | Yes; these provide the interface, styling, icons, and scripts. |
| `bin/` | Yes, for CLI setup and legacy imports | No, for a fresh browser installation. |
| `tests/`, `tools/` | Yes, for validation and package building | No. |
| `docs/`, `README.md`, `bootstrap.example.php` | Yes, for installation/reference | No, for runtime. Keep required license notices with distributed copies. |
| `.gitignore` | Yes | No. |
| `.idea/`, generated `dist/` | Ignore in Git | No. |
| Real private bootstrap, databases, logs, setup keys, backups | Never commit | Store privately outside the public web root as required. |

Do not delete the unversioned runtime files: the application depends on them. You can leave development-only folders out of your hosting upload without deleting them from your source project. The package builder does that automatically. Removing tests/tools from the repository would also remove the documented testing/build workflow.

To record the new layout in PyCharm, select the intended **Unversioned Files → Add to VCS**, include the old `src/` deletions in the same commit, and review the changes. The terminal equivalent is `git add -A`, followed by reviewing `git diff --cached --stat` before committing. Git can then display matching delete/add pairs as renames. Ignored private/generated files are not added by a normal `git add -A`.

## Optional cPanel ZIP build

The root application files are ready to upload. To produce a deployment-only archive on your development computer:

```sh
python3 tools/package-cpanel.py
```

This creates identical `dist/bams-cpanel-1.3.2.zip` and `dist/bams-cpanel.zip` archives from the root files. Upload and extract **one** archive into your public application folder, then remove the uploaded ZIP. Python is not needed on cPanel. `dist/` remains ignored by Git because it is generated output; publish ZIPs as GitHub Release attachments when needed.

## Developer verification

Tests use isolated temporary data and fake servers. They do not need production credentials or access to real BBB/Adobe servers.

```sh
find api lib bin tests -name '*.php' -exec php -l {} \;
find . -maxdepth 1 -name '*.php' -exec php -l {} \;
php tests/installer.php
php tests/run.php
node tests/frontend.mjs
python3 tests/http_test.py
# Optionally serve an extracted runtime-only package during the HTTP suite:
# BAMS_TEST_APP_ROOT=/absolute/path/to/extracted-package python3 tests/http_test.py
node tests/browser.mjs
python3 tools/package-cpanel.py
```

The SQLite regression/HTTP suites need `pdo_sqlite`. The browser suite needs Node 20+ and Firefox. Local test servers require permission to bind a local port; PHP's development server does not enforce Apache `.htaccess` rules.

MariaDB integration requires an explicitly provisioned disposable empty database ending in `_bams_test` and private test connection settings:

```sh
BAMS_TEST_MYSQL_BOOTSTRAP=/absolute/private/test-bootstrap.php php tests/mariadb.php
```

Never use production for this suite: it creates and removes its test tables. See [verification notes](docs/verification.md) for checks that have actually been run. Building an archive does not verify installation on your cPanel host.
