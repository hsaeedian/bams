<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors','0'); umask(0077);
require_once __DIR__ . '/../meta.php';
require_once __DIR__ . '/../lib/store.php';
require_once __DIR__ . '/../lib/util.php';
require_once __DIR__ . '/../lib/migrate.php';
$options = getopt('', ['init','migrate:','admin:','url:','password-stdin']);
try {
    if (isset($options['migrate']) && !isset($options['init'])) {
        $path = realpath($options['migrate']);
        if (!$path || !isset($options['admin'])) throw new InvalidArgumentException('Use --migrate=/absolute/legacy/config.php --admin=existing-username');
        // Legacy PHP is executable code: only a local, trusted administrator-supplied file.
        // Relative meta.php includes are resolved in its original directory.
        chdir(dirname($path)); ob_start();
        try { require $path; } finally { ob_end_clean(); }
        if (!defined('SERVERS') || !defined('USERS') || !defined('URL')) throw new InvalidArgumentException('Missing legacy constants.');
        migrate_legacy(SERVERS,USERS,URL,$options['admin']);
        fwrite(STDOUT,"Legacy configuration imported. Original file was not changed.\n");
    } elseif (isset($options['init']) && !isset($options['migrate'])) {
        if (!isset($options['admin'],$options['url'],$options['password-stdin'])) throw new InvalidArgumentException('Use --init --admin=username --url=https://bams.example.invalid/ --password-stdin');
        $password = rtrim(stream_get_contents(STDIN),"\r\n");
        if (database_driver() === 'mysql') schema();
        transaction(function () use ($options,$password) {
            if (database_driver() !== 'mysql') schema();
            if ((int)sql('SELECT count(*) FROM users')->fetchColumn() || (int)sql('SELECT count(*) FROM servers')->fetchColumn() || (int)sql('SELECT count(*) FROM settings')->fetchColumn()) throw new InvalidArgumentException('Already initialized.');
            // save_user opens its own transaction, so initialization inserts the first user here.
            $username = $options['admin']; $url = app_url($options['url']);
            if (!is_string($username) || $username === '' || strlen($username)>100 || strlen($password)<12 || strlen($password)>72) throw new InvalidArgumentException('Username required; password must be 12–72 bytes.');
            sql("INSERT INTO users(username,password_hash,name,role,all_servers) VALUES(?,?,?,'admin',1)",[$username,password_hash($password,PASSWORD_DEFAULT),$username]);
            sql("INSERT INTO settings(`key`,value) VALUES('app_url',?)",[$url]); audit('CLI','installation.initialize',$username);
        });
        fwrite(STDOUT,"Administrator created.\n");
    } else throw new InvalidArgumentException('Choose --init or --migrate. See README.md.');
} catch (Throwable $e) {
    // PDO/config exceptions may contain sensitive values. Never print exception details.
    fwrite(STDERR,$e instanceof InvalidArgumentException ? $e->getMessage() . "\n" : "Setup failed. Check PHP extensions, private paths, permissions, and trusted configuration format. No partial import was committed.\n"); exit(1);
}
