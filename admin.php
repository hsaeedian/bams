<?php
require_once __DIR__ . '/lib/bootstrap.php';
$actor = require_user(true);
$tabs = ['servers'=>'Servers','users'=>'Users','settings'=>'Settings','audit'=>'Audit log'];
$tab = $_GET['tab'] ?? 'servers'; if (!isset($tabs[$tab])) throw new HttpError(404,'Page not found.');
$message = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']); $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    try {
        $action = clean_text($_POST,'action');
        switch ($action) {
            case 'server.save': save_server($_POST,$actor['username']); break;
            case 'server.test':
                $id = clean_text($_POST,'edit_id',80);
                $old = $id !== '' ? sql('SELECT * FROM servers WHERE id=?',[$id])->fetch() : null;
                if ($id !== '' && !$old) throw new InvalidArgumentException('Server no longer exists.');
                $candidate = server_data($_POST,$old ?: null);
                try { $message = connection_test($candidate); }
                catch (RuntimeException $e) { $error = $e->getMessage(); }
                break;
            case 'server.delete': delete_record('server',clean_text($_POST,'id',80),$actor['username'],($_POST['confirm'] ?? '') === 'yes'); break;
            case 'user.save': save_user($_POST,$actor['username']); break;
            case 'user.delete': delete_record('user',clean_text($_POST,'id',80),$actor['username'],($_POST['confirm'] ?? '') === 'yes'); break;
            case 'settings.save': save_settings($_POST,$actor['username']); break;
            default: throw new InvalidArgumentException('Unknown action.');
        }
        if ($action !== 'server.test') {
            $_SESSION['flash'] = 'Saved successfully.';
            header('Location: admin.php?tab=' . rawurlencode($tab),true,303); exit;
        }
    } catch (InvalidArgumentException $e) { $error = $e->getMessage(); }
    catch (Throwable $e) { error_log('BAMS administrative operation failed [' . get_class($e) . ']'); $error = 'Unable to save. Check unique identifiers and try again.'; }
}
$servers = sql('SELECT id,type,title,domain,api_path,enabled FROM servers ORDER BY id')->fetchAll();
function field(string $name, string $label, $value = '', string $type = 'text', string $extra = '', string $labelKey = ''): void {
    echo '<div class="mb-3"><label class="form-label" for="f-' . h($name) . '" data-admin-label="' . h($labelKey ?: ($type === 'password' ? 'credential_' . $name : $name)) . '">' . h($label) . '</label><input class="form-control" id="f-' . h($name) . '" name="' . h($name) . '" type="' . h($type) . '" value="' . h($value) . '" ' . $extra . '></div>';
}
function token_field(): void { echo '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">'; }
function check_field(string $name, string $label, bool $checked): void {
    echo '<label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="' . h($name) . '" value="1" ' . ($checked ? 'checked' : '') . '><span class="form-check-label" data-admin-label="' . h($name) . '">' . h($label) . '</span></label>';
}
function deletion(string $kind, $id): void {
    echo '<form method="post" class="d-inline">'; token_field();
    echo '<input type="hidden" name="action" value="' . h($kind) . '.delete"><input type="hidden" name="id" value="' . h($id) . '"><label class="me-2"><input type="checkbox" name="confirm" value="yes" required> <span data-admin-label="confirmDelete">Confirm deletion</span></label><button class="btn btn-sm btn-outline-danger" data-admin-label="delete">Delete</button></form>';
}
include __DIR__ . '/header.php';
?>
<div id="admin-page" class="container py-4" style="margin-top:1rem">
<nav class="navbar navbar-expand bg-light rounded mb-4"><div class="container-fluid flex-wrap gap-2">
<a class="navbar-brand" href="index.php" data-admin-label="dashboard">Monitoring dashboard</a>
<?php foreach ($tabs as $key=>$label): ?><a class="btn <?php echo $key===$tab?'btn-primary':'btn-outline-primary'; ?>" href="?tab=<?php echo h($key); ?>" data-admin-label="<?php echo h($key); ?>"><?php echo h($label); ?></a><?php endforeach; ?>
<form method="post" action="logout.php"><?php token_field(); ?><button class="btn btn-outline-secondary" data-admin-label="logOut">Log Out</button></form>
</div></nav>
<div class="mb-3"><label for="admin-language" data-admin-label="language">Language</label> <select id="admin-language" class="form-select w-auto d-inline"><option value="en">English</option><option value="fa">فارسی</option></select></div>
<?php if ($message): ?><div class="alert alert-success" role="status"><?php echo h($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?php echo h($error); ?></div><?php endif; ?>
<h1 data-admin-label="<?php echo h($tab); ?>"><?php echo h($tabs[$tab]); ?></h1>
<?php if ($tab === 'servers'):
$edit = null;
foreach ($servers as $s) if ($s['id'] === ($_GET['edit'] ?? '')) $edit = $s;
// A failed save retains only non-secret input. Connection tests never write configuration.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && str_starts_with($_POST['action'] ?? '', 'server.') && ($_POST['action'] ?? '') !== 'server.delete') {
    $edit = array_intersect_key($_POST,array_flip(['edit_id','type','title','domain','api_path','enabled']));
    $edit['enabled'] = !empty($_POST['enabled']);
}
?>
<div class="table-responsive"><table class="table align-middle"><thead><tr><th>ID</th><th data-admin-label="title">Title</th><th data-admin-label="domain">Server origin</th><th data-admin-label="enabled">Enabled</th><th></th></tr></thead><tbody>
<?php foreach ($servers as $s): ?><tr><td><?php echo h($s['id']); ?></td><td><?php echo h($s['title']); ?></td><td><?php echo h($s['type'] . ' ' . $s['domain']); ?></td><td><?php echo $s['enabled']?'✓':'—'; ?></td><td><a class="btn btn-sm btn-outline-primary" href="?tab=servers&amp;edit=<?php echo rawurlencode($s['id']); ?>" data-admin-label="edit">Edit</a> <?php deletion('server',$s['id']); ?></td></tr><?php endforeach; ?></tbody></table></div>
<a class="btn btn-secondary mb-3" href="?tab=servers" data-admin-label="add">Add new</a>
<form method="post" class="card card-body" autocomplete="off">
<?php token_field(); $editing = $edit['edit_id'] ?? $edit['id'] ?? ''; ?>
<input type="hidden" name="edit_id" value="<?php echo h($editing); ?>">
<?php if ($editing !== ''): ?>
<div class="mb-3"><label class="form-label" for="server-id" data-admin-label="serverId">Server ID</label><input id="server-id" class="form-control" value="<?php echo h($editing); ?>" readonly></div>
<?php else: ?><p class="text-muted" data-admin-label="automaticServerId">Server ID will be generated automatically when saved.</p><?php endif; ?>
<?php field('title','Display title',$edit['title'] ?? ''); ?>
<label for="f-type" class="form-label" data-admin-label="type">Server type</label><select id="f-type" name="type" class="form-select mb-3"><option value="B" <?php echo ($edit['type']??'B')==='B'?'selected':''; ?>>BigBlueButton</option><option value="A" <?php echo ($edit['type']??'')==='A'?'selected':''; ?>>Adobe Connect</option></select>
<?php field('domain','Server origin (https://host:port)',$edit['domain'] ?? '','text','required'); field('api_path','BBB API path',$edit['api_path'] ?? '/bigbluebutton/api/'); field('secret','BBB shared secret — empty preserves stored value','','password','autocomplete="new-password"'); field('login','Adobe username — empty preserves stored value','','password','autocomplete="new-password"'); field('password','Adobe password — empty preserves stored value','','password','autocomplete="new-password"','adobePassword'); check_field('enabled','Enabled',(bool)($edit['enabled'] ?? true)); ?>
<div class="d-flex gap-2 flex-wrap"><button name="action" value="server.save" class="btn btn-primary" data-admin-label="save">Save</button><button name="action" value="server.test" class="btn btn-outline-primary" data-admin-label="testConnection">Test unsaved connection</button></div>
</form>
<?php elseif ($tab === 'users'):
$users = sql('SELECT id,username,name,role,enabled,all_servers FROM users ORDER BY username')->fetchAll();
$edit = null; foreach ($users as $u) if ((string)$u['id'] === ($_GET['edit'] ?? '')) $edit = $u;
$acl = $edit ? array_column(sql('SELECT server_id FROM user_servers WHERE user_id=?',[$edit['id']])->fetchAll(),'server_id') : [];
if ($error && ($_POST['action'] ?? '') === 'user.save') { $edit = array_intersect_key($_POST,array_flip(['id','username','name','role','enabled','all_servers'])); $edit['enabled']=!empty($_POST['enabled']); $edit['all_servers']=!empty($_POST['all_servers']); $acl = is_array($_POST['acl'] ?? null)?$_POST['acl']:[]; }
?>
<div class="table-responsive"><table class="table align-middle"><thead><tr><th data-admin-label="username">Username</th><th data-admin-label="name">Display name</th><th data-admin-label="role">Role</th><th data-admin-label="enabled">Enabled</th><th></th></tr></thead><tbody>
<?php foreach ($users as $u): ?><tr><td><?php echo h($u['username']); ?></td><td><?php echo h($u['name']); ?></td><td><?php echo h($u['role']); ?></td><td><?php echo $u['enabled']?'✓':'—'; ?></td><td><a class="btn btn-sm btn-outline-primary" href="?tab=users&amp;edit=<?php echo (int)$u['id']; ?>" data-admin-label="edit">Edit</a> <?php deletion('user',$u['id']); ?></td></tr><?php endforeach; ?></tbody></table></div>
<a class="btn btn-secondary mb-3" href="?tab=users" data-admin-label="add">Add new</a>
<p data-admin-label="adminAccess">Administrators can access every enabled server. Editing a user invalidates their existing sessions.</p>
<form method="post" class="card card-body" autocomplete="off"><?php token_field(); ?><input type="hidden" name="action" value="user.save"><input type="hidden" name="id" value="<?php echo (int)($edit['id'] ?? 0); ?>">
<?php field('username','Username',$edit['username'] ?? '','text','required'); field('name','Display name',$edit['name'] ?? ''); field('password','New password (12–72 bytes); empty preserves an existing password','','password','autocomplete="new-password"','newPassword'); ?>
<label class="form-label" for="f-role" data-admin-label="role">Role</label><select class="form-select mb-3" id="f-role" name="role"><option value="viewer" <?php echo ($edit['role']??'viewer')==='viewer'?'selected':''; ?>>Viewer</option><option value="admin" <?php echo ($edit['role']??'')==='admin'?'selected':''; ?>>Administrator</option></select>
<?php check_field('enabled','Enabled',(bool)($edit['enabled']??true)); check_field('all_servers','Access all servers (viewers)',(bool)($edit['all_servers']??false)); ?>
<fieldset class="mb-3"><legend class="fs-6" data-admin-label="selectedServers">Selected servers (empty means no access)</legend>
<?php foreach ($servers as $s): ?><label class="form-check"><input class="form-check-input" type="checkbox" name="acl[]" value="<?php echo h($s['id']); ?>" <?php echo in_array($s['id'],$acl,true)?'checked':''; ?>><span class="form-check-label"><?php echo h($s['title'] . ' (' . $s['id'] . ')'); ?></span></label><?php endforeach; ?></fieldset>
<button class="btn btn-primary" data-admin-label="save">Save</button></form>
<?php elseif ($tab === 'settings'): ?>
<form method="post" class="card card-body"><?php token_field(); ?><input type="hidden" name="action" value="settings.save"><?php field('app_url','Application URL (including subdirectory)',setting('app_url'),'url','required'); ?><button class="btn btn-primary" data-admin-label="save">Save</button></form>
<?php else:
$page = max(1,(int)($_GET['page'] ?? 1)); $offset = ($page-1)*100;
$rows = sql('SELECT actor,action,target,created_at FROM audit ORDER BY id DESC LIMIT 100 OFFSET ?',[$offset])->fetchAll(); ?>
<div class="table-responsive"><table class="table"><thead><tr><th>UTC</th><th data-admin-label="actor">Actor</th><th data-admin-label="action">Action</th><th data-admin-label="target">Target</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><?php foreach ($row as $value): ?><td><?php echo h($value); ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div>
<?php if ($page>1): ?><a class="btn btn-outline-primary" href="?tab=audit&amp;page=<?php echo $page-1; ?>">←</a><?php endif; ?> <a class="btn btn-outline-primary" href="?tab=audit&amp;page=<?php echo $page+1; ?>">→</a>
<?php endif; ?>
</div>
<script type="module">
import Lang from './js/language.js';
const select = document.getElementById('admin-language'); select.value = localStorage.getItem('lang') || 'en';
select.addEventListener('change', () => { Lang.lang = select.value; });
</script>
<?php include __DIR__ . '/footer.php'; ?>
