<?php
/** Call only after explicitly loading a trusted legacy config in the CLI process. */
function migrate_legacy(array $servers, array $users, string $url, string $administrator): void
{
    if (database_driver() === 'mysql') schema();
    transaction(function () use ($servers,$users,$url,$administrator) {
        if (database_driver() !== 'mysql') schema();
        if ((int)sql('SELECT count(*) FROM users')->fetchColumn() || (int)sql('SELECT count(*) FROM servers')->fetchColumn() || (int)sql('SELECT count(*) FROM settings')->fetchColumn()) throw new InvalidArgumentException('Destination is already initialized; migration refused.');
        if (!in_array($administrator,array_column($users,'username'),true)) throw new InvalidArgumentException('Select an existing legacy username as initial administrator.');
        foreach ($servers as $s) {
            if (!isset($s['id']) || (!is_string($s['id']) && !is_int($s['id'])) || (string)$s['id'] === '') throw new InvalidArgumentException('Legacy servers must have their original nonempty IDs.');
            $s['id'] = (string)$s['id']; $s['enabled'] = 1;
            $clean = server_data($s);
            sql('INSERT INTO servers(id,type,title,domain,api_path,enabled,secret,login,password) VALUES(:id,:type,:title,:domain,:api_path,:enabled,:secret,:login,:password)',$clean);
        }
        foreach ($users as $u) {
            $username = clean_text($u,'username',100); $name = clean_text($u,'name'); if ($name === '') $name = $username;
            if ($username === '' || !is_string($u['password'] ?? null) || $u['password'] === '') throw new InvalidArgumentException('Legacy user has invalid credentials.');
            $all = empty($u['acl']);
            sql('INSERT INTO users(username,password_hash,name,role,enabled,all_servers) VALUES(?,?,?,?,1,?)',[$username,password_hash($u['password'],PASSWORD_DEFAULT),$name,$username === $administrator ? 'admin':'viewer',$all?1:0]);
            $id = (int)db()->lastInsertId();
            if (!$all) foreach (array_unique($u['acl']) as $sid) sql('INSERT INTO user_servers(user_id,server_id) VALUES(?,?)',[$id,(string)$sid]);
        }
        sql("INSERT INTO settings(`key`,value) VALUES('app_url',?)",[app_url($url)]);
        assert_admin(); audit('CLI','configuration.migrate','legacy');
    });
}
