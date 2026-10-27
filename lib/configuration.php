<?php
/** Fixed deployment paths only; HTTP form fields never select a file path. */
function site_identifier(): string { return substr(hash('sha256',realpath(dirname(__DIR__))),0,12); }
function hosting_public_folders(): array { return ['public_html','private_html','httpdocs','httpsdocs','htdocs']; }
/** Paths come from the filesystem/server configuration, never HTTP form fields. */
function default_private_directory(string $web, ?string $documentRoot = null): string
{
    for ($dir = $web; $dir !== dirname($dir); $dir = dirname($dir)) {
        if (in_array(basename($dir),hosting_public_folders(),true)) {
            return dirname($dir) . '/.bams-' . substr(hash('sha256',$web),0,12);
        }
    }
    if ($documentRoot && ($web === $documentRoot || str_starts_with($web . '/', $documentRoot . '/'))) {
        return dirname($documentRoot) . '/.bams-' . substr(hash('sha256',$web),0,12);
    }
    return dirname($web) . '/config';
}
function private_directory(): string
{
    if ($file = getenv('BAMS_BOOTSTRAP')) return dirname($file);
    if ($dir = getenv('BAMS_PRIVATE_DIR')) return rtrim($dir, '/');
    $web = realpath(dirname(__DIR__));
    $documentRoot = PHP_SAPI !== 'cli' && !empty($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    return default_private_directory($web, $documentRoot ?: null);
}
function bootstrap_path(): string
{
    if ($file = getenv('BAMS_BOOTSTRAP')) return $file;
    $legacy = dirname(__DIR__, 2) . '/config/bootstrap.php';
    if (is_file($legacy)) {
        try { assert_private_directory(dirname($legacy)); return $legacy; }
        catch (RuntimeException $error) { /* Ignore a legacy path inside a public root. */ }
    }
    return private_directory() . '/bootstrap.php';
}
function assert_private_directory(string $dir): void
{
    $real = realpath($dir);
    if (!$real || !is_dir($real) || $dir[0] !== '/') throw new RuntimeException('Create the private directory shown in the installer using your hosting File Manager.');
    $roots = [realpath(dirname(__DIR__))];
    if (PHP_SAPI !== 'cli' && !empty($_SERVER['DOCUMENT_ROOT'])) $roots[] = realpath($_SERVER['DOCUMENT_ROOT']);
    foreach ($roots as $root) {
        if ($root && ($real === $root || str_starts_with($real . '/', $root . '/'))) throw new RuntimeException('Private configuration must be outside the public document root.');
    }
    foreach (explode('/', $real) as $part) if (in_array($part,hosting_public_folders(),true)) throw new RuntimeException('Private configuration must be outside public hosting folders.');
}
function installation_configured(): bool
{
    return file_exists(bootstrap_path()) || file_exists(private_directory() . '/installed.lock') || (!getenv('BAMS_BOOTSTRAP') && is_file(dirname(__DIR__, 2) . '/var/bams.sqlite'));
}
function database_options(): array
{
    $file = bootstrap_path();
    if (!is_file($file)) return [];
    assert_private_directory(dirname($file));
    if (is_link($file)) throw new RuntimeException('Private bootstrap must be a regular file.');
    $options = require $file;
    if (!is_array($options)) throw new RuntimeException('Invalid private bootstrap.');
    return $options;
}
function mysql_options(array $input): array
{
    $host = $input['host'] ?? 'localhost'; $port = (string)($input['port'] ?? '3306');
    $database = $input['database'] ?? ''; $username = $input['username'] ?? ''; $password = $input['password'] ?? '';
    if (!is_string($host) || !preg_match('/^[A-Za-z0-9._:-]{1,253}$/D', $host) || !ctype_digit($port) || (int)$port < 1 || (int)$port > 65535) throw new InvalidArgumentException('Enter a database hostname and a port between 1 and 65535.');
    // Conservative DSN validation prevents PDO option injection via semicolons.
    if (!is_string($database) || !preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $database)) throw new InvalidArgumentException('Enter the full database name, including any hosting account prefix.');
    if (!is_string($username) || $username === '' || strlen($username)>100 || preg_match('/[\x00-\x1F]/',$username) || !is_string($password) || $password === '' || strlen($password)>4096 || str_contains($password,"\0")) throw new InvalidArgumentException('Database username and password are required.');
    return ['driver'=>'mysql','host'=>$host,'port'=>(int)$port,'database'=>$database,'username'=>$username,'password'=>$password];
}
function connect_database(array $options): PDO
{
    $attributes = [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_TIMEOUT=>5];
    if (($options['driver'] ?? 'sqlite') === 'mysql') {
        $attributes[PDO::ATTR_EMULATE_PREPARES] = false;
        $o = mysql_options($options);
        $pdo = new PDO('mysql:host=' . $o['host'] . ';port=' . $o['port'] . ';dbname=' . $o['database'] . ';charset=utf8mb4', $o['username'], $o['password'], $attributes);
        $pdo->exec("SET time_zone = '+00:00'");
        $pdo->exec('SET SESSION innodb_lock_wait_timeout=5');
        return $pdo;
    }
    if (($options['driver'] ?? 'sqlite') !== 'sqlite') throw new RuntimeException('Unsupported database driver.');
    $path = $options['database'] ?? dirname(__DIR__, 2) . '/var/bams.sqlite';
    assert_private_directory(dirname($path));
    if ($path[0] !== '/' || is_link($path)) throw new RuntimeException('Database must be a private regular file.');
    if (PHP_SAPI !== 'cli' && !is_file($path)) throw new RuntimeException('Database is missing.');
    $pdo = new PDO('sqlite:' . $path, null, null, $attributes);
    $pdo->exec('PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000;');
    return $pdo;
}
