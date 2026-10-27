<?php
// No database connection or production bootstrap is used by these installer guard tests.
if (PHP_SAPI !== 'cli') exit(1);
$dir = sys_get_temp_dir() . '/bams-installer-test-' . bin2hex(random_bytes(8)); mkdir($dir,0700);
putenv('BAMS_BOOTSTRAP=' . $dir . '/bootstrap.php');
register_shutdown_function(function () use ($dir) { foreach (glob($dir . '/*') as $file) unlink($file); foreach (glob($dir . '/.install-*') as $file) unlink($file); rmdir($dir); });
require __DIR__ . '/../lib/installer.php';
function check($condition, string $label): void { if (!$condition) throw new RuntimeException('FAIL: ' . $label); echo 'PASS: ' . $label . "\n"; }
function denied(callable $fn, string $label): void { try { $fn(); } catch (Throwable $e) { check(true,$label); return; } throw new RuntimeException('FAIL: ' . $label); }
foreach (['public_html','private_html','httpdocs','httpsdocs','htdocs'] as $folder) {
    $web = '/home/test/domains/example.invalid/' . $folder . '/bams';
    $expected = '/home/test/domains/example.invalid/.bams-' . substr(hash('sha256',$web),0,12);
    check(default_private_directory($web)===$expected,'private directory outside hosting root: ' . $folder);
    mkdir($dir . '/' . $folder);
    denied(fn()=>assert_private_directory($dir . '/' . $folder),'public hosting folder refused for credentials: ' . $folder);
    rmdir($dir . '/' . $folder);
}
$web = '/srv/site/web/bams';
check(default_private_directory($web,'/srv/site/web')==='/srv/site/.bams-' . substr(hash('sha256',$web),0,12),'custom document root gets an external private directory');
check(default_private_directory('/srv/bams')==='/srv/config','CLI fallback keeps private configuration beside the application');
// Simulated PDO failures must reveal stage/codes but never exception SQL or credentials.
$failure = new PDOException('fake-db-password fake-signed-url fake-private-config');
$failure->errorInfo = ['42000',1142,'fake-db-password SQL contents'];
$message = installer_failure_message($failure,'schema-users');
check(str_contains($message,'creating the users table') && str_contains($message,'1142') && str_contains($message,'42000'),'database diagnostics identify stage and sanitized code');
check(!str_contains($message,'fake-db-password') && !str_contains($message,'fake-signed-url') && !str_contains($message,'fake-private-config'),'database diagnostics never echo exception contents');
try { installer_step('database-lock',static function () use ($failure) { throw $failure; }); }
catch (InstallerError $error) { check(str_contains($error->getMessage(),'database installation lock'),'unexpected failure wrapped with its stage'); }
$badState = new PDOException('fake-secret'); $badState->errorInfo = ['fake-secret', 'not-a-number', 'fake-secret'];
check(!str_contains(installer_failure_message($badState,'untrusted-stage-fake-secret'),'fake-secret'),'unknown diagnostic metadata discarded');
putenv('BAMS_BOOTSTRAP=' . $dir . '/not-created/bootstrap.php');
try { installer_key('irrelevant'); throw new LogicException('Missing folder accepted.'); }
catch (InstallerError $error) { check(str_contains($error->getMessage(),'Private folder:') && str_contains($error->getMessage(),'File Manager'),'missing private folder gets actionable error before DB access'); }
putenv('BAMS_BOOTSTRAP=' . $dir . '/bootstrap.php');
$good = ['db_host'=>'localhost','db_port'=>'3306','db_name'=>'fakeaccount_bams','db_user'=>'fakeaccount_user','db_password'=>' fake;db+=password ','admin_username'=>'owner','admin_name'=>'مدیر','admin_password'=>'fake-admin-password','admin_confirmation'=>'fake-admin-password','app_url'=>'https://bams.example.invalid/sub/'];
$input = installer_input($good);
check($input['options']['driver']==='mysql' && $input['options']['password']===$good['db_password'],'MariaDB options preserve password bytes');
check($input['url']==='https://bams.example.invalid/sub/' && $input['name']==='مدیر','installer preserves Persian name and URL');
foreach (['db_host'=>'localhost;dbname=other','db_port'=>'0','db_name'=>'fake;unix_socket=anything','admin_password'=>'short','admin_confirmation'=>'different','app_url'=>'javascript:unsafe()'] as $key=>$bad) {
    $copy=$good; $copy[$key]=$bad; denied(fn()=>installer_input($copy),'invalid installer field rejected: ' . $key);
}
check(!installation_configured(),'fresh installation has no configuration');
denied(fn()=>installer_key('guess'),'missing private setup key rejected');
$key=bin2hex(random_bytes(32)); file_put_contents($dir . '/setup.key',$key . "\n");
denied(fn()=>installer_key('incorrect'),'wrong setup key rejected');
installer_key($key); check(true,'matching private setup key accepted');
denied(fn()=>assert_private_directory(dirname(__DIR__)),'public source directory refused for secrets');
$_SESSION=[]; $_SERVER['REQUEST_METHOD']='GET'; denied(fn()=>require_post(),'GET installation rejected');
$_SERVER['REQUEST_METHOD']='POST'; $_POST=[]; denied(fn()=>require_post(),'missing installation CSRF rejected');
$_POST=['csrf'=>'wrong']; denied(fn()=>require_post(),'wrong installation CSRF rejected');
$_POST=['csrf'=>csrf_token()]; require_post(); check(true,'valid installation CSRF accepted');
installer_publish('installed.lock',"Installed\n");
check(installation_configured(),'durable lock disables installer without bootstrap');
denied(fn()=>installer_submit($good),'locked installation rejects POST before DB access');
denied(fn()=>installer_publish('installed.lock',"replacement\n"),'existing private files cannot be overwritten');
check(file_get_contents($dir . '/installed.lock')==="Installed\n",'locked file unchanged');
denied(fn()=>installer_publish('../outside.php','unsafe'),'arbitrary file paths rejected');
check(!file_exists($dir . '/bootstrap.php'),'guard tests never write real DB configuration');
echo "All installer guard tests passed. No database connected.\n";
