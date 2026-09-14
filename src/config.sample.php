<?php
/**
 * QR tracker — konfiguracija (ŠABLON)
 *
 * Kopiraj ovaj fajl u src/config.php i popuni vrednosti.
 * config.php NE ide u git i mora biti zaštićen .htaccess-om u src/.
 */

// ── Baza ────────────────────────────────────────────────────────────────
// 'sqlite' za lokalni razvoj, 'mysql' na cPanelu
define('QR_DB_DRIVER', 'sqlite');

define('QR_SQLITE_PATH', __DIR__ . '/../data/qr.sqlite');

define('QR_MYSQL_HOST', 'localhost');
define('QR_MYSQL_NAME', 'korisnik_qrtracker');
define('QR_MYSQL_USER', 'korisnik_qruser');
define('QR_MYSQL_PASS', '');

// ── Privatnost ──────────────────────────────────────────────────────────
// 64 heksadecimalna karaktera. Generiši JEDNOM i nikada ne menjaj:
//   php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
// Promena soli = brojanje različitih posetilaca kreće od nule.
define('QR_SALT', '');

// ── Kampanje ────────────────────────────────────────────────────────────
// Jedan kod po štampanom materijalu. /qr/card, /qr/flyer, ...
define('QR_DESTINATIONS', [
    'card'  => 'https://webhubstudio.com/',
    'flyer' => 'https://webhubstudio.com/',
]);
define('QR_DEFAULT_CODE', 'card');
define('QR_APPEND_UTM', true);
// Javna adresa trackera — ovo ide u QR kod iz generatora (generator.php).
define('QR_PUBLIC_BASE', 'https://go.webhubstudio.com');

// Tvoje sopstvene probe: /qr/q.php?c=card&nt=TOKEN preusmeri ali NE upiše.
// Koristi kad testiraš da odredište radi, da ne prljaš statistiku.
define('QR_NOTRACK_TOKEN', '');

// ── Dashboard ───────────────────────────────────────────────────────────
define('QR_DASHBOARD_USER', 'radojko');
// php -r "echo password_hash('LOZINKA', PASSWORD_DEFAULT), PHP_EOL;"
define('QR_DASHBOARD_PASS_HASH', '');

// ── Ostalo ──────────────────────────────────────────────────────────────
define('QR_RETENTION_DAYS', 365);
define('QR_DISPLAY_TZ', 'America/Toronto');   // 'Europe/Belgrade' za Srbiju
