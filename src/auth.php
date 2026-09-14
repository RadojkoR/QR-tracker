<?php
/** Basic Auth za admin stranice (dashboard, generator) — isključivo preko HTTPS-a. */

function qr_require_auth(): void
{
    $user = $_SERVER['PHP_AUTH_USER'] ?? '';
    $pass = $_SERVER['PHP_AUTH_PW']   ?? '';

    // Neki cPanel/CGI serveri ne popune PHP_AUTH_* — pročitaj zaglavlje ručno.
    if ($user === '') {
        $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (stripos($hdr, 'basic ') === 0) {
            $decoded = base64_decode(substr($hdr, 6), true);
            if (is_string($decoded) && str_contains($decoded, ':')) {
                [$user, $pass] = explode(':', $decoded, 2);
            }
        }
    }

    $ok = hash_equals(QR_DASHBOARD_USER, (string) $user)
        && QR_DASHBOARD_PASS_HASH !== ''
        && password_verify((string) $pass, QR_DASHBOARD_PASS_HASH);

    if (!$ok) {
        header('WWW-Authenticate: Basic realm="QR tracker"');
        header('HTTP/1.0 401 Unauthorized');
        exit('401 — pristup odbijen.');
    }
}
