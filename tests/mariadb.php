<?php
/** Run ONLY with a dedicated disposable test DB specified by BAMS_TEST_MYSQL_BOOTSTRAP. */
if (PHP_SAPI !== 'cli') exit(1);
$file = getenv('BAMS_TEST_MYSQL_BOOTSTRAP');
if (!$file || !extension_loaded('pdo_mysql')) { fwrite(STDERR,"SKIP: Provide pdo_mysql and BAMS_TEST_MYSQL_BOOTSTRAP for a disposable MariaDB database.\n"); exit(2); }
$options = require $file;
require __DIR__ . '/../lib/installer.php';
if (($options['driver'] ?? '') !== 'mysql' || !str_ends_with($options['database'] ?? '', '_bams_test')) { fwrite(STDERR,"Refused: use a dedicated database whose name ends in _bams_test.\n"); exit(2); }
$options = mysql_options($options);
$connection = connect_database($options);
if ($connection->query('SHOW TABLES')->fetch()) { fwrite(STDERR,"Refused: test database must be empty.\n"); exit(2); }
$dir = sys_get_temp_dir() . '/bams-mariadb-test-' . bin2hex(random_bytes(8)); mkdir($dir,0700);
putenv('BAMS_BOOTSTRAP=' . $dir . '/bootstrap.php');
function check($value,string $label): void { if (!$value) throw new RuntimeException('FAIL: ' . $label); echo 'PASS: ' . $label . "\n"; }
function denied(callable $fn,string $label): void { try { $fn(); } catch (Throwable $e) { check(true,$label); return; } throw new RuntimeException('FAIL: ' . $label); }
try {
    $key=bin2hex(random_bytes(32)); file_put_contents($dir . '/setup.key',$key);
    $_SESSION=[]; $_SERVER['REQUEST_METHOD']='POST';
    $data=['csrf'=>csrf_token(),'setup_key'=>$key,'db_host'=>$options['host'],'db_port'=>(string)$options['port'],'db_name'=>$options['database'],'db_user'=>$options['username'],'db_password'=>$options['password'],'admin_username'=>'test-owner','admin_name'=>'مدیر آزمایشی','admin_password'=>'fake-owner-password','admin_confirmation'=>'fake-owner-password','app_url'=>'https://test.example.invalid/'];
    $_POST=$data; installer_submit($data);
    check(is_file($dir . '/bootstrap.php') && is_file($dir . '/installed.lock'),'web installer publishes private configuration and locks setup');
    check(!is_file($dir . '/setup.key') && !is_file($dir . '/install.pending.php'),'setup key and pending state removed');
    $owner=getUser('test-owner');
    check($owner['role']==='admin' && password_verify('fake-owner-password',$owner['password_hash']),'first administrator password hashed');
    denied(fn()=>installer_submit($data),'repeat installation refused');
    $saved=require $dir . '/bootstrap.php';
    check(connect_database($saved) instanceof PDO,'saved connection configuration reconnects');
    $id=save_server(['id'=>'test-node','type'=>'B','title'=>'Fake','domain'=>'https://10.0.0.1','secret'=>'fake-secret','enabled'=>1],'test-owner');
    check((bool)preg_match('/^srv-[a-f0-9]{32}$/D',$id) && $id!=='test-node','MariaDB server creation generates ID');
    check(save_server(['edit_id'=>$id,'id'=>'forged-replacement','title'=>'Changed','type'=>'B','domain'=>'https://10.0.0.1','secret'=>'','enabled'=>1],'test-owner')===$id,'MariaDB ID remains stable on edit');
    check(sql('SELECT secret FROM servers WHERE id=?',[$id])->fetchColumn()==='fake-secret','MariaDB blank secret preservation');
    $user=['username'=>'viewer','name'=>'کاربر','role'=>'viewer','enabled'=>1,'all_servers'=>0,'acl'=>[],'password'=>'fake-viewer-password'];
    $uid=save_user($user,'test-owner'); $viewer=getUser('viewer');
    $_SESSION=['uid'=>$uid,'auth_version'=>$viewer['auth_version']];
    denied(fn()=>server_for(require_user(),$id),'MariaDB empty ACL denied');
    $user['id']=$uid; $user['acl']=[$id]; $user['password']=''; save_user($user,'test-owner');
    check(current_user()===null,'MariaDB ACL edit invalidates session');
    $viewer=getUser('viewer'); $_SESSION=['uid'=>$uid,'auth_version'=>$viewer['auth_version']];
    check(server_for(require_user(),$id)['id']===$id,'MariaDB selected ACL accepted');
    denied(fn()=>require_user(true),'MariaDB viewer cannot administer');
    $user['password']='fake-reset-password'; save_user($user,'test-owner');
    check(password_verify('fake-reset-password',getUser('viewer')['password_hash']),'MariaDB password reset');
    $viewer=getUser('viewer'); $_SESSION=['uid'=>$uid,'auth_version'=>$viewer['auth_version']];
    $user['password']=''; $user['enabled']=0; save_user($user,'test-owner'); check(current_user()===null,'MariaDB disabled session rejected');
    $ownerData=['id'=>$owner['id'],'username'=>'test-owner','name'=>'Owner','role'=>'viewer','enabled'=>1,'all_servers'=>1,'password'=>''];
    denied(fn()=>save_user($ownerData,'test-owner'),'MariaDB last administrator demotion rolled back');
    $ownerData['role']='admin'; $ownerData['enabled']=0; denied(fn()=>save_user($ownerData,'test-owner'),'MariaDB last administrator disabling rolled back');
    denied(fn()=>delete_record('user',(string)$owner['id'],'test-owner',true),'MariaDB last administrator deletion rolled back');
    save_settings(['app_url'=>'https://test.example.invalid/sub/'],'test-owner'); save_settings(['app_url'=>'https://test.example.invalid/again/'],'test-owner');
    check(setting('app_url')==='https://test.example.invalid/again/','MariaDB settings upsert');
    transaction(function(){record_login_failure('fake-bucket');record_login_failure('fake-bucket');});
    check((int)sql('SELECT attempts FROM login_attempts WHERE bucket=?',['fake-bucket'])->fetchColumn()===2,'MariaDB login attempt upsert');
    check(count(sql('SELECT actor,action,target FROM audit ORDER BY id DESC LIMIT 100 OFFSET ?',[0])->fetchAll())>0,'native prepared integer pagination and audit');
    delete_record('user',(string)$uid,'test-owner',true); delete_record('server',$id,'test-owner',true);
    check(!getUser('viewer') && !sql('SELECT id FROM servers WHERE id=?',[$id])->fetch(),'MariaDB user/server deletion');
    // Simulate interrupted publication: DB stays installed; retry must not reset its administrator.
    unlink($dir . '/bootstrap.php'); unlink($dir . '/installed.lock');
    file_put_contents($dir . '/setup.key',$key);
    $token=sql('SELECT token FROM bams_installation WHERE id=1')->fetchColumn();
    installer_publish('install.pending.php',"<?php return " . var_export(['token'=>$token,'fingerprint'=>installer_fingerprint($options)],true) . ';');
    // Earlier session invalidation tests clear the original CSRF token. Model a
    // reloaded installer form before retrying publication with the current token.
    $data['csrf']=csrf_token();
    $data['admin_password']='fake-different-password'; $data['admin_confirmation']='fake-different-password'; $_POST=$data;
    installer_submit($data);
    check(password_verify('fake-owner-password',getUser('test-owner')['password_hash']),'interrupted publication recovers without password reset');
    echo "All disposable MariaDB integration tests passed.\n";
} finally {
    // Only the initially empty, explicitly selected _bams_test database is ever cleaned up.
    foreach (['user_servers','login_attempts','audit','users','servers','settings','app_lock','bams_installation'] as $table) $connection->exec('DROP TABLE IF EXISTS ' . $table);
    foreach (glob($dir . '/*') as $path) unlink($path);
    foreach (glob($dir . '/.install*') as $path) unlink($path);
    rmdir($dir);
}
