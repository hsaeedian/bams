# Installer diagnostics update — 1.3.1

The earlier installer could show only “Installation could not finish” for an absent/inaccessible private folder or an unexpected PDO failure. That message does not establish the cause on your hosting account.

## Apply using cPanel File Manager

1. Use the current root application files, or build the full cPanel package with `python3 tools/package-cpanel.py`. The current release includes the 1.3.1 diagnostics update and the later automatic server ID changes.
2. Upload it into the existing public BAMS application folder containing `install.php` (for example `public_html/bams/`).
3. Copy the current public application files into that folder, or extract `dist/bams-cpanel.zip` there, allowing replacement of public source files. Include hidden `.htaccess` and preserve the private `.bams-...` folder. If you uploaded a ZIP, remove it afterward. Keep the existing database and private bootstrap/lock; do not rerun initial setup on an installed site.
4. Refresh `install.php`, enter the same setup key and database connection details, and retry. Passwords are not retained after an error. If a previous attempt committed the administrator, its original password stays unchanged.
5. If it fails, report **only the new red error text** and, if useful, BAMS table names from phpMyAdmin. Do not send database contents, configuration files, passwords, the setup key, or full PHP logs.

Do not delete tables, `setup.key`, `install.pending.php`, or private configuration while troubleshooting. The updated complete cPanel package also includes this patch.

## Error guidance

- **Private folder:** In File Manager enable Show Hidden Files and create the exact private folder displayed by the installer, outside public_html. Set folder permissions to 0700 and setup.key to 0600. The PHP process must run with access to that directory. If the folder exists but cannot be accessed, ask the hosting provider to check ownership and `open_basedir`. Do not use 0777.
- **Connecting to MariaDB:** Check the full prefixed database/user names and password. Code 1045 is authentication/assignment; 1049 is a missing/incorrect database name; 2002/2003 is a connection/host/socket problem.
- **Creating tables / saving administrator:** Code 1142/1143/1044/1227 identifies an access/privilege issue; ensure the database user has ALL PRIVILEGES on the dedicated BAMS database only. A host-level restriction may require provider assistance.
- **Database installation lock:** Code 1305/1370 may indicate GET_LOCK/RELEASE_LOCK is unavailable or restricted. Report that step/code to the hosting provider. The installer retains its concurrency protection instead of proceeding without the lock.
- **Table-definition codes:** Report the exact step and numeric code, plus the MariaDB version shown in cPanel/phpMyAdmin, so the incompatible definition can be investigated without revealing data. Do not change/drop tables as a diagnostic experiment.

The update reports a fixed step label, numeric PDO database code and validated SQLSTATE only. It never includes exception messages, SQL, connection names/passwords, private file contents, signed URLs, tokens or stack traces in those diagnostics. PHP diagnostic logging also records only the fixed step/code/state. Known input/setup errors retain their safe user-facing guidance. A failure while releasing a lock is recorded without replacing an earlier failure.

## Verification limits

Tree-sitter parses the changed PHP without syntax-tree errors. Guard tests were extended for simulated PDO errors, secret redaction, stage wrapping and missing private-directory guidance. The complete/patch ZIPs are checked for integrity and an explicit source-file allowlist. PHP CLI and MariaDB remain unavailable in the development environment; real PHP/PDO tests and an actual cPanel retry have not been performed here. This update fixes error reporting; the underlying host-specific failure is not yet established.
