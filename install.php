<?php
// Deliberately independent from application bootstrap: setup must work without a DB.
ini_set('display_errors','0');
require_once __DIR__ . '/lib/installer.php';
header('Cache-Control: no-store'); header('Referrer-Policy: no-referrer'); header('X-Content-Type-Options: nosniff'); header('X-Frame-Options: DENY');
ini_set('session.use_strict_mode','1'); ini_set('session.use_only_cookies','1');
session_name('bams_setup_' . site_identifier());
session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off','samesite'=>'Strict','path'=>'/']);
session_start();
$error = ''; $success = false; $locked = installation_configured();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_SESSION['retry_after']) && $_SESSION['retry_after']>time()) throw new HttpError(429,'Please wait a few seconds before retrying setup.');
        $_SESSION['retry_after'] = time()+3;
        installer_step('request',fn()=>installer_submit($_POST)); $success = true; $locked = true;
        $_SESSION = []; session_regenerate_id(true);
    } catch (HttpError $e) { http_response_code($e->status); $error = $e->getMessage(); }
    catch (InvalidArgumentException | InstallerError $e) { http_response_code(400); $error = $e->getMessage(); }
    catch (Throwable $e) { http_response_code(500); $error = installer_failure_message($e,'request'); }
}
$locked = installation_configured();
$requirements = installer_requirements();
$folder = private_directory();
?>
<!doctype html><html lang="en" dir="ltr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>BAMS installation</title>
<link id="boot-ltr" rel="stylesheet" href="plugins/bootstrap/css/bootstrap.min.css"><link id="boot-rtl" rel="stylesheet" href="plugins/bootstrap/css/bootstrap.rtl.min.css">
</head><body class="bg-light"><main class="container py-4" style="max-width:800px">
<div class="d-flex justify-content-between align-items-center mb-3"><h1 data-setup="installTitle">Install BAMS</h1><select id="setup-language" class="form-select w-auto" aria-label="Language"><option value="en">English</option><option value="fa">فارسی</option></select></div>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?php echo h($error); ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success" data-setup="installSuccess">Installation completed. Sign in with your administrator account. If this resumed a previous attempt, use the password from that attempt.</div><a class="btn btn-primary" href="login.php" data-setup="logIn">Login</a>
<?php elseif ($locked): ?><div class="alert alert-info" data-setup="installLocked">Installation is locked. Existing configuration will not be changed.</div><a class="btn btn-primary" href="login.php" data-setup="logIn">Login</a>
<?php else: ?>
<p data-setup="installIntro">Create a MariaDB database and user in your hosting panel (cPanel, DirectAdmin, or Plesk), and grant that user privileges on the database. Enter the full names, including any account prefixes. No terminal or SQLite is needed.</p>
<?php foreach ($requirements as $issue): ?><div class="alert alert-warning"><?php echo h($issue); ?></div><?php endforeach; ?>
<div class="card card-body mb-3"><h2 class="h5" data-setup="installOwnership">1. Confirm you own this hosting account</h2>
<p data-setup="installKeyHelp">Generate a setup-key file below. In your hosting File Manager, enable Show Hidden Files, create the private folder shown below outside all public web folders, set its permissions to 0700, and upload setup.key into it. This prevents another visitor from installing your site first.</p>
<code class="d-block mb-3" dir="ltr"><?php echo h($folder); ?></code>
<button id="download-setup-key" type="button" class="btn btn-outline-primary align-self-start" data-setup="installKeyDownload">Generate and download setup.key</button><p class="mt-2 mb-0 small" data-setup="installKeyExisting">Already uploaded a key? Enter its contents below. Never put setup.key in a public web folder.</p>
</div>
<form method="post" action="install.php" autocomplete="off" class="card card-body">
<input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
<?php
function setup_field(string $name, string $key, string $label, string $type = 'text', string $value = '', bool $required = true): void {
    echo '<div class="mb-3"><label class="form-label" data-setup="' . h($key) . '" for="' . h($name) . '">' . h($label) . '</label><input class="form-control" id="' . h($name) . '" name="' . h($name) . '" type="' . h($type) . '" value="' . h($value) . '" ' . ($required?'required':'') . ($type==='password'?' autocomplete="new-password"':'') . '></div>';
}
setup_field('setup_key','installKey','Setup key','password');
?>
<h2 class="h5" data-setup="installDatabase">2. Database connection</h2>
<?php
setup_field('db_host','installHost','Database host','text','localhost');
setup_field('db_port','installPort','Database port','number','3306');
setup_field('db_name','installDbName','Database name (including any account prefix)');
setup_field('db_user','installDbUser','Database username (including any account prefix)');
setup_field('db_password','installDbPassword','Database password','password');
?>
<h2 class="h5" data-setup="installAdmin">3. First administrator</h2>
<?php
setup_field('admin_username','installAdminUsername','Administrator username');
setup_field('admin_name','name','Display name','text','',false);
setup_field('admin_password','installAdminPassword','Administrator password (12–72 bytes)','password');
setup_field('admin_confirmation','installConfirm','Confirm administrator password','password');
setup_field('app_url','app_url','Application URL (including subdirectory)','url');
?>
<button class="btn btn-primary" <?php echo $requirements?'disabled':''; ?> data-setup="installButton">Install BAMS</button>
<p class="small mt-3 mb-0" data-setup="installPrivacy">Passwords are never prefilled. Existing databases with data are refused. Setup locks automatically after installation. Use HTTPS before submitting passwords.</p>
</form>
<?php endif; ?>
</main><script type="module" src="js/install.js?v=1.3.0"></script></body></html>
