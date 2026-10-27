# Source change manifest

Project-relative paths for SFTP download and local PyCharm review. This manifest compares against the files present before this task, without using Git. No original files were renamed or deleted.

## Modified

- `README.md`
- `src/api/initial.php`
- `src/api/join.php`
- `src/api/login.php`
- `src/api/resource.php`
- `src/api/server-item.php`
- `src/api/server-list.php`
- `src/footer.php`
- `src/header.php`
- `src/index.php`
- `src/js/ameeting.js`
- `src/js/attendee.js`
- `src/js/bmeeting.js`
- `src/js/index.js`
- `src/js/language.js`
- `src/js/login.js`
- `src/js/node.js`
- `src/js/server.js`
- `src/lib/adobeconnect.php`
- `src/lib/util.php`
- `src/login.php`
- `src/logout.php`
- `src/meta.php`

## Added

- `.gitignore`
- `bin/bams.php`
- `bootstrap.example.php`
- `docs/change-manifest.md`
- `docs/verification.md`
- `src/admin.php`
- `src/lib/bootstrap.php`
- `src/lib/migrate.php`
- `src/lib/store.php`
- `tests/browser.html`
- `tests/browser.mjs`
- `tests/fixtures/legacy.php`
- `tests/frontend.mjs`
- `tests/http_test.py`
- `tests/run.php`

## Renamed

None.

## Deleted

None. In particular, `src/config.php` remains unchanged to preserve the original configuration. It is unused by the new HTTP application. After an explicitly authorized migration and verification, remove that obsolete file from your new deployed version and ordinary source synchronization as described in the README; retain only a private rollback backup. That later operator action has not been performed here.

## Exclude from source download/synchronization and Git

- Real `src/config.php` and all other legacy secret-bearing configuration copies.
- `config/bootstrap.php`, alternate private bootstraps, `var/`, all SQLite data/journal/WAL/SHM files, and operational backups.
- Logs, PHP session files, `.env*`, test runtime output, `__pycache__/`, and real server/API credentials.

The fake fixture `tests/fixtures/legacy.php` and `bootstrap.example.php` contain only fictitious/example values and belong in source. No production database, bootstrap, or private runtime data was created. `.gitignore` does not exclude already-tracked files by itself: review your local Git index before committing.

No GitHub authentication, cloning, remotes, commits, pushes, or pull requests were used. Download the listed source changes, review locally, and run the documented tests on a compatible PHP runtime before your own commit/deployment.
