<?php
require_once __DIR__ . '/configuration.php';
require_once __DIR__ . '/schema.php';
/** Connection injection is internal to the installer/test harness, never an HTTP parameter. */
function db(?PDO $connection = null): PDO
{
    static $db;
    if ($connection !== null) $db = $connection;
    if (!$db) { umask(0077); $db = connect_database(database_options()); }
    return $db;
}
function database_driver(): string { return db()->getAttribute(PDO::ATTR_DRIVER_NAME); }
function schema(): void
{
    foreach (schema_statements(database_driver()) as $statement) db()->exec($statement);
}
function sql(string $query, array $params = []): PDOStatement
{
    $s = db()->prepare($query);
    foreach (array_values($params) as $i=>$value) {
        // Named statements preserve their keys; numeric LIMIT/OFFSET must use integer bindings.
        if (!array_is_list($params)) break;
        $s->bindValue($i+1,$value,is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    if (array_is_list($params)) $s->execute(); else $s->execute($params);
    return $s;
}
function transaction(callable $fn)
{
    $mysql = database_driver() === 'mysql';
    if ($mysql) db()->beginTransaction(); else db()->exec('BEGIN IMMEDIATE');
    try {
        // Serialize writes across all PHP workers, including last-admin decisions.
        if ($mysql && !sql('SELECT id FROM app_lock WHERE id=1 FOR UPDATE')->fetch()) throw new RuntimeException('Database write lock is missing.');
        $result = $fn();
        if ($mysql) db()->commit(); else db()->exec('COMMIT');
        return $result;
    } catch (Throwable $e) {
        if ($mysql) { if (db()->inTransaction()) db()->rollBack(); } else db()->exec('ROLLBACK');
        throw $e;
    }
}
function put_setting(string $key, string $value): void
{
    $suffix = database_driver() === 'mysql' ? 'ON DUPLICATE KEY UPDATE value=VALUES(value)' : 'ON CONFLICT(`key`) DO UPDATE SET value=excluded.value';
    sql('INSERT INTO settings(`key`,value) VALUES(?,?) ' . $suffix,[$key,$value]);
}
function record_login_failure(string $bucket): void
{
    $suffix = database_driver() === 'mysql' ? 'ON DUPLICATE KEY UPDATE attempts=attempts+1' : 'ON CONFLICT(bucket) DO UPDATE SET attempts=attempts+1';
    sql('INSERT INTO login_attempts(bucket,attempts,expires) VALUES(?,1,?) ' . $suffix,[$bucket,time()+900]);
}
function audit(string $actor, string $action, string $target): void
{
    sql('INSERT INTO audit(actor,action,target) VALUES(?,?,?)', [$actor, $action, $target]);
}
function setting(string $key, string $default = ''): string
{
    $row = sql('SELECT value FROM settings WHERE `key`=?', [$key])->fetch();
    return $row ? $row['value'] : $default;
}
function current_user(): ?array
{
    if (!isset($_SESSION['uid'])) return null;
    $u = sql('SELECT * FROM users WHERE id=? AND enabled=1', [$_SESSION['uid']])->fetch();
    if (!$u || (int)$u['auth_version'] !== (int)($_SESSION['auth_version'] ?? 0)) {
        unset($_SESSION['uid'], $_SESSION['auth_version']); return null;
    }
    $u['acl'] = array_column(sql('SELECT server_id FROM user_servers WHERE user_id=?', [$u['id']])->fetchAll(), 'server_id');
    return $u;
}
function can_access(array $user, array $server): bool
{
    return (bool)$server['enabled'] && ($user['role'] === 'admin' || (bool)$user['all_servers'] || in_array((string)$server['id'], $user['acl'], true));
}
function server_for(array $user, string $id): array
{
    $s = sql('SELECT * FROM servers WHERE id=?', [$id])->fetch();
    if (!$s || !can_access($user, $s)) throw new HttpError(403, 'Server access denied.');
    return $s;
}
function address(string $value): string
{
    $value = trim($value);
    if (!str_contains($value, '://')) $value = 'https://' . $value;
    $p = parse_url($value);
    if (!$p || !in_array($p['scheme'] ?? '', ['http','https'], true) || empty($p['host']) || isset($p['user']) || isset($p['pass']) || isset($p['query']) || isset($p['fragment']) || !in_array($p['path'] ?? '', ['', '/'], true) || !filter_var($value, FILTER_VALIDATE_URL)) {
        throw new InvalidArgumentException('Enter an http(s) server origin, optionally with a port, without credentials or a path.');
    }
    return rtrim($value, '/');
}
function app_url(string $url): string
{
    $p = parse_url($url);
    if (!$p || !filter_var($url, FILTER_VALIDATE_URL) || !in_array($p['scheme'] ?? '', ['http','https'], true) || isset($p['user']) || isset($p['pass']) || isset($p['query']) || isset($p['fragment']) || preg_match('/[\x00-\x20<>"\']/', $url)) throw new InvalidArgumentException('Enter a full http(s) application URL.');
    return rtrim($url, '/') . '/';
}
function clean_text(array $data, string $key, int $max = 200): string
{
    $s = $data[$key] ?? '';
    if (!is_string($s) || strlen($s) > $max || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $s)) throw new InvalidArgumentException('Invalid ' . $key . '.');
    return $s;
}
function server_data(array $d, ?array $old = null): array
{
    $id = $old['id'] ?? clean_text($d, 'id', 80);
    if ($id === '') $id = 'srv-' . bin2hex(random_bytes(16));
    if ($id === '' || strlen($id)>80 || preg_match('/[\x00-\x1F\x7F]/',$id)) throw new InvalidArgumentException('A stable server ID of up to 80 bytes is required.');
    $s = ['id'=>$id, 'type'=>clean_text($d,'type'), 'title'=>trim(clean_text($d,'title')), 'domain'=>address(clean_text($d,'domain',2048)), 'api_path'=>clean_text($d,'api_path',256) ?: '/bigbluebutton/api/', 'enabled'=>!empty($d['enabled']) ? 1 : 0];
    if (!in_array($s['type'], ['A','B'], true)) throw new InvalidArgumentException('Select a server type.');
    if (!preg_match('~^/(?:[A-Za-z0-9_-]+/)*$~D', $s['api_path'])) throw new InvalidArgumentException('API path must start and end with / and contain path segments only.');
    foreach (['secret','login','password'] as $k) {
        $value = clean_text($d,$k,4096);
        $s[$k] = $value !== '' ? $value : ($old[$k] ?? '');
    }
    if (($s['type'] === 'B' && $s['secret'] === '') || ($s['type'] === 'A' && ($s['login'] === '' || $s['password'] === ''))) throw new InvalidArgumentException('Connection credentials are required.');
    return $s;
}
function save_server(array $d, string $actor): string
{
    return transaction(function () use ($d,$actor) {
        $editing = clean_text($d,'edit_id',80);
        $old = $editing !== '' ? sql('SELECT * FROM servers WHERE id=?',[$editing])->fetch() : null;
        if ($editing !== '' && !$old) throw new InvalidArgumentException('Server no longer exists.');
        // New administrator-created servers always use a backend-generated ID.
        // Legacy migration passes its original IDs directly to server_data().
        if (!$old) unset($d['id']);
        $s = server_data($d,$old ?: null);
        if ($old) {
            sql('UPDATE servers SET type=:type,title=:title,domain=:domain,api_path=:api_path,enabled=:enabled,secret=:secret,login=:login,password=:password WHERE id=:id',$s);
        } else {
            sql('INSERT INTO servers(id,type,title,domain,api_path,enabled,secret,login,password) VALUES(:id,:type,:title,:domain,:api_path,:enabled,:secret,:login,:password)',$s);
        }
        audit($actor,$old ? 'server.update' : 'server.create',$s['id']); return $s['id'];
    });
}
function save_user(array $d, string $actor): int
{
    return transaction(function () use ($d,$actor) {
        $id = (int)($d['id'] ?? 0);
        $old = $id ? sql('SELECT * FROM users WHERE id=?',[$id])->fetch() : null;
        if ($id && !$old) throw new InvalidArgumentException('User no longer exists.');
        $username = trim(clean_text($d,'username',100)); $name = clean_text($d,'name'); if ($name === '') $name = $username; $role = clean_text($d,'role');
        if ($username === '' || !in_array($role,['admin','viewer'],true)) throw new InvalidArgumentException('Username and a valid role are required.');
        $enabled = !empty($d['enabled']) ? 1 : 0; $all = !empty($d['all_servers']) ? 1 : 0;
        $password = clean_text($d,'password',4096);
        if ((!$old || $password !== '') && (strlen($password)<12 || strlen($password)>72)) throw new InvalidArgumentException('New passwords must be between 12 and 72 bytes.');
        $hash = $password !== '' ? password_hash($password,PASSWORD_DEFAULT) : $old['password_hash'];
        $acl = $d['acl'] ?? [];
        if (!is_array($acl)) throw new InvalidArgumentException('Invalid server selection.');
        $acl = array_unique(array_map('strval',$acl));
        foreach ($acl as $sid) if (!sql('SELECT id FROM servers WHERE id=?',[$sid])->fetch()) throw new InvalidArgumentException('Unknown server selection.');
        if ($old) {
            sql('UPDATE users SET username=?,name=?,role=?,enabled=?,all_servers=?,password_hash=?,auth_version=auth_version+1 WHERE id=?',[$username,$name,$role,$enabled,$all,$hash,$id]);
            sql('DELETE FROM user_servers WHERE user_id=?',[$id]);
        } else {
            sql('INSERT INTO users(username,name,role,enabled,all_servers,password_hash) VALUES(?,?,?,?,?,?)',[$username,$name,$role,$enabled,$all,$hash]); $id = (int)db()->lastInsertId();
        }
        foreach ($acl as $sid) sql('INSERT INTO user_servers VALUES(?,?)',[$id,$sid]);
        assert_admin();
        audit($actor,$old ? 'user.update-role-acl-status' : 'user.create',(string)$id);
        if ($old && $password !== '') audit($actor,'user.password-reset',(string)$id);
        return $id;
    });
}
function assert_admin(): void
{
    if (!(int)sql("SELECT count(*) FROM users WHERE role='admin' AND enabled=1")->fetchColumn()) throw new InvalidArgumentException('At least one active administrator is required.');
}
function delete_record(string $kind, string $id, string $actor, bool $confirmed): void
{
    if (!$confirmed || !in_array($kind,['server','user'],true)) throw new InvalidArgumentException('Explicit deletion confirmation is required.');
    transaction(function () use ($kind,$id,$actor) {
        sql('DELETE FROM ' . ($kind === 'server' ? 'servers' : 'users') . ' WHERE id=?',[$id]);
        assert_admin(); audit($actor,$kind . '.delete',$id);
    });
}
function save_settings(array $d, string $actor): void
{
    $url = app_url(clean_text($d,'app_url',2048));
    transaction(function () use ($url,$actor) {
        put_setting('app_url',$url);
        audit($actor,'settings.update','app_url');
    });
}
