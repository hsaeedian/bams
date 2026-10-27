<?php
require_once __DIR__ . '/../meta.php';
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/util.php';
ini_set('display_errors','0');
// Never log exception messages, request URLs, credentials or POST bodies.
set_exception_handler(function (Throwable $e) {
    error_log('BAMS request failed [' . get_class($e) . ']');
    $status = $e instanceof HttpError ? $e->status : 500;
    if (str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/')) xml_response(['returncode'=>'FAILED','message'=>$e instanceof HttpError ? $e->getMessage() : 'Request failed. Contact the administrator.'],$status);
    else { http_response_code($status); echo h($e instanceof HttpError ? $e->getMessage() : 'Application unavailable. Check private configuration. For a new installation, open install.php.'); }
});
if (PHP_SAPI !== 'cli') {
    if (!installation_configured()) {
        if (str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/')) {
            xml_response(['returncode'=>'FAILED','message'=>'Application is not installed. Open install.php.'],503);
        } else { header('Location: install.php'); }
        exit;
    }
    ini_set('session.use_strict_mode','1'); ini_set('session.use_only_cookies','1');
    session_name('bams_session_' . site_identifier());
    session_set_cookie_params(['httponly'=>true,'secure'=>(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || str_starts_with(setting('app_url'),'https://'),'samesite'=>'Lax','path'=>'/']);
    session_start();
    header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: no-referrer'); header('X-Frame-Options: DENY');
    define('URL',app_url(setting('app_url')));
}
