<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_post();
$_SESSION = []; session_destroy();
setcookie(session_name(),'', ['expires'=>time()-3600,'path'=>'/','httponly'=>true,'secure'=>str_starts_with(URL,'https://'),'samesite'=>'Lax']);
header('Location: ' . URL . 'login.php'); exit;
