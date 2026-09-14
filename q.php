<?php
/**
 * QR endpoint: zabeleži skeniranje i preusmeri.
 *
 *   /qr/card  →  .htaccess  →  q.php?c=card  →  302 na odredište
 *
 * Logovanje je sekundarno: ako baza pukne, posetilac i dalje stiže na sajt.
 */

require_once __DIR__ . '/src/config.php';
require_once __DIR__ . '/src/track.php';

$raw  = (string) ($_GET['c'] ?? '');
$code = substr((string) preg_replace('/[^A-Za-z0-9_-]/', '', $raw), 0, 40);

if ($code === '' || !array_key_exists($code, QR_DESTINATIONS)) {
    $code = QR_DEFAULT_CODE;
}

// Sopstvena proba sa tajnim tokenom — preusmeri, ali ne upiši.
$noTrack = QR_NOTRACK_TOKEN !== ''
    && hash_equals(QR_NOTRACK_TOKEN, (string) ($_GET['nt'] ?? ''));

if (!$noTrack) {
    try {
        qr_track_scan($code);
    } catch (Throwable $e) {
        error_log('[qr-tracker] ' . $e->getMessage());
    }
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer-when-downgrade');
header('Location: ' . qr_destination($code), true, 302);
exit;
