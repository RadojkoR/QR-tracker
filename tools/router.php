<?php
/**
 * Router za PHP ugrađeni server — lokalno radi ono što na cPanelu radi .htaccess.
 *
 *   php -S localhost:8080 -t . tools/router.php
 *
 * /card → q.php?c=card, a src/, data/ i tools/ vraćaju 403 kao na produkciji.
 * Na Apache-u se ne koristi: van ugrađenog servera odmah vraća 404.
 */

if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if (preg_match('~^/(src|data|tools)(/|$)|/\.~', $path)) {
    http_response_code(403);
    exit('403 — zabranjeno (kao .htaccess na produkciji).');
}

if ($path !== '/' && is_file($root . $path)) {
    return false;   // postojeći fajl (dashboard.php, assets/...) servira sam server
}

if ($path === '/' || preg_match('~^/([A-Za-z0-9_-]{1,40})/?$~', $path, $m)) {
    $_GET['c'] = $m[1] ?? '';
    require $root . '/q.php';
    exit;
}

http_response_code(404);
echo '404';
