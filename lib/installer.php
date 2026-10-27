<?php
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/util.php';
class InstallerError extends RuntimeException {}
/** Errors contain fixed stage labels/codes only, never exception messages or SQL. */
function installer_failure_message(Throwable $error, string $stage): string
{
    $labels = [
        'request'=>'checking installation prerequisites',
        'database-connection'=>'connecting to MariaDB',
        'database-lock'=>'acquiring the database installation lock',
        'database-inspection'=>'checking the destination database',
        'pending-state'=>'reading private installation state',
        'ownership-marker'=>'checking the installation ownership marker',
        'schema-settings'=>'creating the settings table',
        'schema-servers'=>'creating the servers table',
        'schema-users'=>'creating the users table',
        'schema-acl'=>'creating server permissions',
        'schema-audit'=>'creating the audit log',
        'schema-login'=>'creating login protection',
        'schema-lock'=>'creating the application write lock',
        'schema-lock-row'=>'initializing the application write lock',
        'administrator'=>'saving the administrator and application settings',
        'release-lock'=>'releasing the database installation lock',
    ];
    $label = $labels[$stage] ?? $labels['request'];
    $state = ''; $number = 0;
    if ($error instanceof PDOException) {
        $info = $error->errorInfo;
        if (is_array($info)) {
            if (is_string($info[0] ?? null) && preg_match('/^[A-Z0-9]{5}$/D',$info[0])) $state = $info[0];
            if (is_int($info[1] ?? null) || (is_string($info[1] ?? null) && ctype_digit($info[1]))) $number = (int)$info[1];
        }
    }
    $code = $number > 0 ? ' Database code ' . $number . ($state !== '' ? ' (SQLSTATE ' . $state . ')' : '') . '.' : '';
    $hint = match ($number) {
        1044,1142,1143,1227 => ' In your hosting panel, assign the database user ALL PRIVILEGES on this BAMS database, including CREATE, INSERT, SELECT, UPDATE, DELETE and REFERENCES.',
        1045 => ' Check the full database username and its password, and confirm that user is assigned to the BAMS database.',
        1049 => ' Check the full database name, including any hosting account prefix.',
        2002,2003 => ' Check the database host/port supplied by your hosting provider; localhost may use a Unix socket.',
        1064,1071,1118,1215,1005 => ' The host rejected a table definition. Check the MariaDB version and InnoDB support; report this step and code to the application maintainer.',
        1205,1213 => ' Another database operation is holding a lock. Wait a moment and retry.',
        1305,1370 => ' Ask the hosting provider whether GET_LOCK/RELEASE_LOCK are available to this database user.',
        1295 => ' The database rejected a native prepared statement. Report this step and code to the application maintainer.',
        2006,2013 => ' The database connection was interrupted. Check database availability with your host.',
        1062 => ' Existing installation data conflicts with this operation. It will not be replaced automatically.',
        default => $error instanceof PDOException ? ' Check database privileges and MariaDB/InnoDB compatibility with your host.' : ' Check that PHP can access the private folder outside public web folders (including open_basedir restrictions) and that its permissions are 0700.',
    };
    // Only whitelist-derived labels and numeric codes reach either logs or the browser.
    error_log('BAMS installer: step=' . (isset($labels[$stage]) ? $stage : 'request') . ' state=' . $state . ' code=' . $number);
    return 'Installation failed while ' . $label . '.' . $code . $hint . ' Retry with the same database and keep the existing tables and private installation files.';
}
function installer_step(string $stage, callable $operation)
{
    try { return $operation(); }
    catch (HttpError | InvalidArgumentException | InstallerError $error) { throw $error; }
    catch (Throwable $error) { throw new InstallerError(installer_failure_message($error,$stage)); }
}
function installer_private_directory(): string
{
    $dir = private_directory();
    try { assert_private_directory($dir); }
    catch (RuntimeException $error) {
        // assert_private_directory emits only our fixed deployment guidance, never input/SQL.
        throw new InstallerError('Private folder: ' . $error->getMessage() . ' In File Manager, use the exact private folder shown below and set its permissions to 0700. If it already exists, ask the host to check PHP open_basedir access.');
    }
    return $dir;
}
function installer_requirements(): array
{
    $errors = [];
    if (PHP_VERSION_ID < 80200) $errors[] = 'Select PHP 8.2 or later in your hosting panel.';
    foreach (['pdo_mysql','curl','dom','SimpleXML','session'] as $extension) {
        if (!extension_loaded($extension)) $errors[] = 'Enable the PHP ' . $extension . ' extension in your hosting panel, or ask your hosting provider.';
    }
    if (!function_exists('link')) $errors[] = 'Ask the hosting provider to enable PHP link() for atomic private configuration publication.';
    return $errors;
}
function installer_key(string $submitted): void
{
    $dir = installer_private_directory();
    $file = $dir . '/setup.key';
    if (!is_file($file) || is_link($file)) throw new InvalidArgumentException('Upload setup.key to the private folder shown below using your hosting File Manager.');
    $stored = trim((string)file_get_contents($file, false, null, 0, 1025));
    if (strlen($stored)<24 || strlen($stored)>256 || !hash_equals($stored,$submitted)) throw new InvalidArgumentException('The setup key does not match the private setup.key file.');
}
function installer_input(array $data): array
{
    $options = mysql_options(['host'=>clean_text($data,'db_host',253), 'port'=>clean_text($data,'db_port',5), 'database'=>clean_text($data,'db_name',64), 'username'=>clean_text($data,'db_user',100), 'password'=>clean_text($data,'db_password',4096)]);
    $username = trim(clean_text($data,'admin_username',100)); $name = clean_text($data,'admin_name');
    $password = clean_text($data,'admin_password',72); $confirmation = clean_text($data,'admin_confirmation',72);
    if ($username === '') throw new InvalidArgumentException('Choose an administrator username.');
    if (strlen($password)<12 || strlen($password)>72) throw new InvalidArgumentException('Administrator password must be 12–72 bytes.');
    if (!hash_equals($password,$confirmation)) throw new InvalidArgumentException('Administrator passwords do not match.');
    return ['options'=>$options,'username'=>$username,'name'=>$name !== '' ? $name : $username,'password'=>$password,'url'=>app_url(clean_text($data,'app_url',2048))];
}
/** Publish a complete private file atomically, without ever replacing an existing file. */
function installer_publish(string $name, string $contents): void
{
    if (!in_array($name,['bootstrap.php','install.pending.php','installed.lock'],true)) throw new LogicException('Invalid installer file.');
    $dir = installer_private_directory();
    $target = $dir . '/' . $name;
    if (file_exists($target) || is_link($target)) throw new InstallerError('A private installation file already exists. Nothing was overwritten.');
    $temporary = $dir . '/.install-' . bin2hex(random_bytes(12));
    $handle = @fopen($temporary,'x');
    if (!$handle) throw new InstallerError('Cannot write private configuration. Check the private folder permissions in File Manager.');
    try {
        if (!chmod($temporary,0600) || fwrite($handle,$contents) !== strlen($contents) || !fflush($handle)) throw new InstallerError('Cannot save private configuration.');
        if (function_exists('fsync')) fsync($handle);
        fclose($handle); $handle = null;
        // An atomic hard link fails if another process has already published target.
        if (!@link($temporary,$target)) throw new InstallerError('Cannot publish private configuration. Check folder permissions or ask the host to allow PHP link(). Retry using the same database.');
    } finally {
        if (is_resource($handle)) fclose($handle);
        if (is_file($temporary)) unlink($temporary);
    }
}
function installer_fingerprint(array $options): string
{
    return hash('sha256',json_encode([$options['host'],$options['port'],$options['database'],$options['username']]));
}
/** Called only behind setup-key verification, CSRF, and the local installation lock. */
function install_mariadb(array $input): void
{
    if (installation_configured()) throw new HttpError(403,'Installation is locked.');
    $dir = installer_private_directory();
    // An alternate named BAMS_BOOTSTRAP is a deployment/CLI feature, not a web write target.
    if (bootstrap_path() !== $dir . '/bootstrap.php') throw new InstallerError('Web installation requires the standard bootstrap.php filename in the private folder.');
    $o = $input['options'];
    $connection = installer_step('database-connection', fn()=>connect_database($o));
    db($connection);
    $lockName = 'bams-install-' . substr(hash('sha256',$o['database']),0,48);
    if ((int)installer_step('database-lock', fn()=>sql('SELECT GET_LOCK(?,5)',[$lockName])->fetchColumn()) !== 1) throw new InstallerError('Another installation is in progress. Wait a moment and retry.');
    try {
        $pendingFile = $dir . '/install.pending.php';
        $tables = installer_step('database-inspection', fn()=>sql('SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchAll(PDO::FETCH_COLUMN));
        if (is_file($pendingFile) && !is_link($pendingFile)) {
            $pending = installer_step('pending-state', static function () use ($pendingFile) { return require $pendingFile; });
            if (!is_array($pending) || ($pending['fingerprint'] ?? '') !== installer_fingerprint($o) || !is_string($pending['token'] ?? null)) throw new InstallerError('A previous installation is pending for another database. Use the original database, or review the pending file through File Manager before starting over.');
        } else {
            if ($tables) throw new InstallerError('Use a new empty database created in your hosting panel. Existing tables will not be changed or deleted.');
            $pending = ['fingerprint'=>installer_fingerprint($o),'token'=>bin2hex(random_bytes(32))];
            installer_publish('install.pending.php',"<?php\nreturn " . var_export($pending,true) . ";\n");
        }
        $allowed = ['bams_installation','settings','servers','users','user_servers','audit','login_attempts','app_lock'];
        if (array_diff($tables,$allowed)) throw new InstallerError('The database contains unrelated tables. Nothing was changed. Use a dedicated empty database.');
        if (in_array('bams_installation',$tables,true)) {
            $token = installer_step('ownership-marker', fn()=>sql('SELECT token FROM bams_installation WHERE id=1')->fetchColumn());
            if ($token === false && $tables === ['bams_installation']) {
                installer_step('ownership-marker', fn()=>sql('INSERT INTO bams_installation(id,token) VALUES(1,?)',[$pending['token']]));
                $token = $pending['token'];
            }
            if (!$token || !hash_equals($pending['token'],$token)) throw new InstallerError('This database belongs to another installation. Nothing was changed.');
        } elseif ($tables) {
            throw new InstallerError('The database contains tables without this installation marker. Nothing was changed.');
        } else {
            // MariaDB DDL implicitly commits: create ownership marker before other tables.
            installer_step('ownership-marker', fn()=>db()->exec('CREATE TABLE bams_installation (id INT PRIMARY KEY, token CHAR(64) NOT NULL) ENGINE=InnoDB'));
            installer_step('ownership-marker', fn()=>sql('INSERT INTO bams_installation(id,token) VALUES(1,?)',[$pending['token']]));
        }
        // Schema creation is intentionally OUTSIDE the administrator/settings transaction.
        $stages = ['schema-settings','schema-servers','schema-users','schema-acl','schema-audit','schema-login','schema-lock','schema-lock-row'];
        foreach (schema_statements('mysql') as $index=>$statement) {
            installer_step($stages[$index] ?? 'request', fn()=>db()->exec($statement));
        }
        installer_step('administrator', fn()=>transaction(function () use ($input,$pending) {
            $installed = setting('installation_id');
            if ($installed !== '') {
                if (!hash_equals($pending['token'],$installed)) throw new InstallerError('Database is already installed.');
                assert_admin(); return; // Recover publication only; never reset the existing administrator.
            }
            foreach (['users','servers','settings','audit','user_servers','login_attempts'] as $table) {
                if ((int)sql('SELECT count(*) FROM ' . $table)->fetchColumn()) throw new InstallerError('Database contains configuration. It will not be overwritten.');
            }
            sql("INSERT INTO users(username,password_hash,name,role,all_servers) VALUES(?,?,?,'admin',1)",[$input['username'],password_hash($input['password'],PASSWORD_DEFAULT),$input['name']]);
            put_setting('app_url',$input['url']); put_setting('installation_id',$pending['token']);
            audit($input['username'],'installation.initialize','web');
        }));
        installer_publish('bootstrap.php',"<?php\nreturn " . var_export($o,true) . ";\n");
        // bootstrap.php itself also locks setup, even if cleanup is interrupted.
        installer_publish('installed.lock',"Installed\n");
        foreach (['setup.key','install.pending.php'] as $name) if (is_file($dir . '/' . $name) && !is_link($dir . '/' . $name)) @unlink($dir . '/' . $name);
    } finally {
        try { sql('SELECT RELEASE_LOCK(?)',[$lockName]); }
        catch (Throwable $error) { installer_failure_message($error,'release-lock'); } // Closing the connection releases it; never mask an earlier error.
    }
}
function installer_submit(array $data): void
{
    if (installation_configured()) throw new HttpError(403,'Installation is locked.');
    require_post();
    if (installer_requirements()) throw new InvalidArgumentException('Resolve the PHP requirements shown below before installing.');
    installer_key(clean_text($data,'setup_key',256));
    $input = installer_input($data);
    $dir = private_directory();
    if (!is_writable($dir) || !@chmod($dir,0700)) throw new InstallerError('Set the private folder permissions to 0700 in File Manager, and ensure PHP can write there.');
    umask(0077);
    if (is_link($dir . '/.install.lock')) throw new InstallerError('Invalid installation lock.');
    $lock = @fopen($dir . '/.install.lock','c');
    if (!$lock) throw new InstallerError('Cannot open private installation lock.');
    try {
        if (!flock($lock,LOCK_EX|LOCK_NB)) throw new InstallerError('An installation is already running. Retry shortly.');
        if (installation_configured()) throw new HttpError(403,'Installation is locked.');
        install_mariadb($input);
    } finally { flock($lock,LOCK_UN); fclose($lock); }
}
