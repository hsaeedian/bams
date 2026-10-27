<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_post();
$username = clean_text($_POST,'username',100); $password = clean_text($_POST,'password',4096);
$bucket = hash('sha256',($_SERVER['REMOTE_ADDR'] ?? '') . '|' . $username);
$u = transaction(function () use ($bucket,$username,$password) {
    sql('DELETE FROM login_attempts WHERE expires<?',[time()]);
    $attempt = sql('SELECT attempts FROM login_attempts WHERE bucket=?',[$bucket])->fetchColumn();
    if ($attempt >= 10) throw new HttpError(429,'Too many attempts. Try again later.');
    $u = getUser($username);
    $valid = password_verify($password,$u['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi');
    if (!$u || !$u['enabled'] || !$valid) {
        record_login_failure($bucket); return null;
    }
    sql('DELETE FROM login_attempts WHERE bucket=?',[$bucket]); return $u;
});
if (!$u) { xml_response(['returncode'=>'FAILED'],401); exit; }
session_regenerate_id(true);
$_SESSION = ['uid'=>$u['id'],'auth_version'=>$u['auth_version'],'csrf'=>bin2hex(random_bytes(32))];
xml_response(['returncode'=>'SUCCESS']);
