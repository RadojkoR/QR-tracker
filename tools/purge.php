<?php
/**
 * Brisanje pojedinačnih zapisa starijih od QR_RETENTION_DAYS.
 * Dnevni zbir (qr_daily) ostaje, pa istorija preživljava brisanje.
 *
 * cPanel → Cron Jobs, nedeljno:
 *   0 3 * * 0 /usr/local/bin/php /home/KORISNIK/public_html/qr/tools/purge.php >/dev/null 2>&1
 */

if (PHP_SAPI !== 'cli') {
    header('HTTP/1.0 403 Forbidden');
    exit('Samo iz CLI.');
}

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';

$cutoff = gmdate('Y-m-d', strtotime('-' . (int) QR_RETENTION_DAYS . ' days'));

try {
    $st = qr_db()->prepare('DELETE FROM qr_scans WHERE scan_date < ?');
    $st->execute([$cutoff]);
    printf("[%s] obrisano %d zapisa starijih od %s%s", gmdate('c'), $st->rowCount(), $cutoff, PHP_EOL);
} catch (Throwable $e) {
    fwrite(STDERR, '[qr-tracker purge] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
