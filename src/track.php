<?php
/**
 * Beleženje skeniranja. Sirova IP adresa postoji samo u memoriji dok traje
 * zahtev i nikada ne dodiruje disk — vidi references/privacy.md.
 */

require_once __DIR__ . '/db.php';

/* ── Identifikacija posetioca ──────────────────────────────────────────── */

function qr_daily_salt(): string
{
    return hash('sha256', QR_SALT . '|' . gmdate('Y-m-d'));
}

function qr_client_ip(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP'] as $h) {
        if (!empty($_SERVER[$h])) {
            return (string) $_SERVER[$h];
        }
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    }
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function qr_visitor_hash(string $ua): string
{
    return hash('sha256', qr_daily_salt() . '|' . qr_client_ip() . '|' . $ua);
}

/* ── Filter botova ─────────────────────────────────────────────────────── */

function qr_is_bot(string $ua): bool
{
    if ($ua === '') {
        return true;   // pravi telefon uvek šalje User-Agent
    }
    $needles = [
        'bot', 'crawl', 'spider', 'slurp', 'curl', 'wget', 'python-requests',
        'headlesschrome', 'phantomjs', 'facebookexternalhit', 'whatsapp',
        'telegrambot', 'slackbot', 'discordbot', 'twitterbot', 'linkedinbot',
        'skypeuripreview', 'embedly', 'pinterest', 'preview', 'monitor',
        'uptime', 'lighthouse', 'pagespeed', 'gtmetrix', 'ahrefs', 'semrush',
    ];
    $lower = strtolower($ua);
    foreach ($needles as $n) {
        if (str_contains($lower, $n)) {
            return true;
        }
    }
    return false;
}

/* ── Parsiranje User-Agent-a ───────────────────────────────────────────── */

function qr_device(string $ua): string
{
    $l = strtolower($ua);
    if (str_contains($l, 'ipad') || (str_contains($l, 'android') && !str_contains($l, 'mobile'))) {
        return 'tablet';
    }
    if (str_contains($l, 'mobi') || str_contains($l, 'iphone') || str_contains($l, 'android')) {
        return 'mobile';
    }
    return 'desktop';
}

function qr_os(string $ua): string
{
    $l = strtolower($ua);
    return match (true) {
        str_contains($l, 'iphone'), str_contains($l, 'ipad'), str_contains($l, 'ipod') => 'iOS',
        str_contains($l, 'android')                     => 'Android',
        str_contains($l, 'windows')                     => 'Windows',
        str_contains($l, 'mac os x'), str_contains($l, 'macintosh') => 'macOS',
        str_contains($l, 'cros')                        => 'ChromeOS',
        str_contains($l, 'linux')                       => 'Linux',
        default                                         => 'Other',
    };
}

// Redosled je bitan: Edge i Samsung se predstavljaju i kao Chrome.
function qr_browser(string $ua): string
{
    $l = strtolower($ua);
    return match (true) {
        str_contains($l, 'edg/')           => 'Edge',
        str_contains($l, 'samsungbrowser') => 'Samsung',
        str_contains($l, 'opr/'), str_contains($l, 'opera') => 'Opera',
        str_contains($l, 'firefox'), str_contains($l, 'fxios') => 'Firefox',
        str_contains($l, 'crios')          => 'Chrome',
        str_contains($l, 'chrome')         => 'Chrome',
        str_contains($l, 'safari')         => 'Safari',
        default                            => 'Other',
    };
}

function qr_lang(): ?string
{
    $raw = (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
    if ($raw === '') {
        return null;
    }
    $first = strtolower(trim(explode(',', $raw)[0]));
    $first = trim(explode(';', $first)[0]);
    $first = preg_replace('/[^a-z\-]/', '', $first) ?? '';
    return $first === '' ? null : substr($first, 0, 16);
}

function qr_country(): ?string
{
    $c = (string) ($_SERVER['HTTP_CF_IPCOUNTRY'] ?? '');   // samo iza Cloudflare-a
    $c = strtoupper(preg_replace('/[^A-Za-z]/', '', $c) ?? '');
    return strlen($c) === 2 ? $c : null;
}

// Samo domen uputnog sajta — bez putanje i parametara.
function qr_referrer_host(): ?string
{
    $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    if ($ref === '') {
        return null;
    }
    $host = parse_url($ref, PHP_URL_HOST);
    return is_string($host) && $host !== '' ? substr($host, 0, 190) : null;
}

/* ── Odredište ─────────────────────────────────────────────────────────── */

function qr_destination(string $code): string
{
    $url = QR_DESTINATIONS[$code] ?? QR_DESTINATIONS[QR_DEFAULT_CODE];

    if (!QR_APPEND_UTM) {
        return $url;
    }

    $utm = http_build_query([
        'utm_source'   => 'qr',
        'utm_medium'   => 'print',
        'utm_campaign' => $code,
    ]);

    $sep = str_contains($url, '?') ? '&' : '?';
    return $url . $sep . $utm;
}

/* ── Upis ──────────────────────────────────────────────────────────────── */

function qr_track_scan(string $code): void
{
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    if (qr_is_bot($ua)) {
        return;   // bot i dalje dobija preusmeravanje, samo se ne beleži
    }

    $pdo  = qr_db();
    $hash = qr_visitor_hash($ua);
    $date = gmdate('Y-m-d');
    $now  = gmdate('Y-m-d H:i:s');

    // Ponovljena poseta tog dana — bilo koji kod.
    $seen = $pdo->prepare('SELECT 1 FROM qr_scans WHERE visitor_hash = ? AND scan_date = ? LIMIT 1');
    $seen->execute([$hash, $date]);
    $isRepeat = $seen->fetchColumn() ? 1 : 0;

    // Za dnevni zbir se broji po kodu: isti čovek koji skenira i karticu i
    // flajer isti dan je nov posetilac za oba materijala.
    $seenCode = $pdo->prepare('SELECT 1 FROM qr_scans WHERE visitor_hash = ? AND scan_date = ? AND code = ? LIMIT 1');
    $seenCode->execute([$hash, $date, $code]);
    $isNewForCode = $seenCode->fetchColumn() ? 0 : 1;

    $ins = $pdo->prepare('
        INSERT INTO qr_scans
            (code, scanned_at, scan_date, visitor_hash, is_repeat, device, os, browser, lang, country, referrer)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $ins->execute([
        $code, $now, $date, $hash, $isRepeat,
        qr_device($ua), qr_os($ua), qr_browser($ua),
        qr_lang(), qr_country(), qr_referrer_host(),
    ]);

    qr_bump_daily($pdo, $date, $code, $isNewForCode);
}

// Dnevni zbir preživljava brisanje po roku čuvanja.
function qr_bump_daily(PDO $pdo, string $date, string $code, int $newVisitor): void
{
    if (QR_DB_DRIVER === 'mysql') {
        $sql = 'INSERT INTO qr_daily (scan_date, code, scans, visitors) VALUES (?, ?, 1, ?)
                ON DUPLICATE KEY UPDATE scans = scans + 1, visitors = visitors + VALUES(visitors)';
    } else {
        $sql = 'INSERT INTO qr_daily (scan_date, code, scans, visitors) VALUES (?, ?, 1, ?)
                ON CONFLICT(scan_date, code) DO UPDATE SET
                    scans    = scans + 1,
                    visitors = visitors + excluded.visitors';
    }

    $pdo->prepare($sql)->execute([$date, $code, $newVisitor]);
}
