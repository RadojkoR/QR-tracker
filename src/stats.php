<?php
/**
 * Upiti za dashboard i admin akcije.
 *
 * Sva vremena u bazi su UTC; konverzija u lokalno tek pri prikazu.
 * Svi statistički upiti gledaju samo redove sa excluded = 0.
 */

require_once __DIR__ . '/db.php';

/* ── Opseg ─────────────────────────────────────────────────────────────── */

function qr_range_bounds(int $days): array
{
    return [
        gmdate('Y-m-d', strtotime('-' . ($days - 1) . ' days')),
        gmdate('Y-m-d'),
    ];
}

/* ── Statistika ────────────────────────────────────────────────────────── */

function qr_totals(int $days): array
{
    [$from, $to] = qr_range_bounds($days);
    return qr_totals_between($from, $to);
}

/** Isti broj dana neposredno pre tekućeg perioda — za poređenje u KPI karticama. */
function qr_totals_previous(int $days): array
{
    return qr_totals_between(
        gmdate('Y-m-d', strtotime('-' . (2 * $days - 1) . ' days')),
        gmdate('Y-m-d', strtotime('-' . $days . ' days'))
    );
}

function qr_totals_between(string $from, string $to): array
{
    $st = qr_db()->prepare('
        SELECT COUNT(*) AS scans, COUNT(DISTINCT visitor_hash) AS visitors
        FROM qr_scans WHERE excluded = 0 AND scan_date BETWEEN ? AND ?
    ');
    $st->execute([$from, $to]);
    $row = $st->fetch() ?: [];
    return [
        'scans'    => (int) ($row['scans'] ?? 0),
        'visitors' => (int) ($row['visitors'] ?? 0),
    ];
}

function qr_all_time(): array
{
    $row = qr_db()->query('SELECT COALESCE(SUM(scans),0) AS s, COALESCE(SUM(visitors),0) AS v FROM qr_daily')->fetch() ?: [];
    return ['scans' => (int) ($row['s'] ?? 0), 'visitors' => (int) ($row['v'] ?? 0)];
}

function qr_excluded_count(): int
{
    return (int) qr_db()->query('SELECT COUNT(*) FROM qr_scans WHERE excluded = 1')->fetchColumn();
}

function qr_by_code(int $days): array
{
    [$from, $to] = qr_range_bounds($days);
    $st = qr_db()->prepare('
        SELECT code, COUNT(*) AS scans, COUNT(DISTINCT visitor_hash) AS visitors
        FROM qr_scans WHERE excluded = 0 AND scan_date BETWEEN ? AND ?
        GROUP BY code ORDER BY scans DESC
    ');
    $st->execute([$from, $to]);
    return $st->fetchAll();
}

function qr_breakdown(string $column, int $days): array
{
    if (!in_array($column, ['device', 'os', 'browser', 'lang', 'country'], true)) {
        return [];   // nikad korisnički unos u ime kolone
    }
    [$from, $to] = qr_range_bounds($days);
    $st = qr_db()->prepare("
        SELECT COALESCE($column, '—') AS label, COUNT(*) AS n
        FROM qr_scans WHERE excluded = 0 AND scan_date BETWEEN ? AND ?
        GROUP BY label ORDER BY n DESC LIMIT 12
    ");
    $st->execute([$from, $to]);
    return $st->fetchAll();
}

function qr_daily_series(int $days): array
{
    [$from, $to] = qr_range_bounds($days);
    $st = qr_db()->prepare('
        SELECT scan_date, COUNT(*) AS scans, COUNT(DISTINCT visitor_hash) AS visitors
        FROM qr_scans WHERE excluded = 0 AND scan_date BETWEEN ? AND ?
        GROUP BY scan_date ORDER BY scan_date
    ');
    $st->execute([$from, $to]);

    $found = [];
    foreach ($st->fetchAll() as $r) {
        $found[$r['scan_date']] = ['scans' => (int) $r['scans'], 'visitors' => (int) $r['visitors']];
    }

    $out = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $d = gmdate('Y-m-d', strtotime("-$i days"));
        $out[] = ['date' => $d] + ($found[$d] ?? ['scans' => 0, 'visitors' => 0]);
    }
    return $out;
}

/** Sati po lokalnoj zoni — konverzija u PHP-u, radi isto na SQLite i MySQL. */
function qr_by_hour(int $days): array
{
    [$from, $to] = qr_range_bounds($days);
    $st = qr_db()->prepare('SELECT scanned_at FROM qr_scans WHERE excluded = 0 AND scan_date BETWEEN ? AND ?');
    $st->execute([$from, $to]);

    $buckets = array_fill(0, 24, 0);
    $tz      = new DateTimeZone(QR_DISPLAY_TZ);
    foreach ($st->fetchAll() as $r) {
        $dt = new DateTime($r['scanned_at'], new DateTimeZone('UTC'));
        $dt->setTimezone($tz);
        $buckets[(int) $dt->format('G')]++;
    }
    return $buckets;
}

/** Poslednja skeniranja, uključujući isključena (da mogu da se vrate). */
function qr_recent(int $limit = 25, bool $onlyActive = false): array
{
    $limit = max(1, min(5000, $limit));
    $where = $onlyActive ? 'WHERE excluded = 0' : '';
    return qr_db()->query("
        SELECT id, scanned_at, code, device, os, browser, lang, country, referrer, is_repeat, excluded
        FROM qr_scans $where ORDER BY id DESC LIMIT $limit
    ")->fetchAll();
}

/** Dani koji uopšte imaju zapise — za padajuću listu u admin panelu. */
function qr_dates_with_data(int $limit = 60): array
{
    return qr_db()->query("
        SELECT scan_date, COUNT(*) AS n FROM qr_scans
        GROUP BY scan_date ORDER BY scan_date DESC LIMIT $limit
    ")->fetchAll();
}

function qr_local(string $utc, string $fmt = 'd.m.Y H:i'): string
{
    $dt = new DateTime($utc, new DateTimeZone('UTC'));
    $dt->setTimezone(new DateTimeZone(QR_DISPLAY_TZ));
    return $dt->format($fmt);
}

/* ── Održavanje zbira ──────────────────────────────────────────────────── */

/**
 * Preračuna qr_daily iz qr_scans za zadate datume.
 *
 * Poziva se posle svake izmene. Bez ovoga „ukupno ikada" ostaje naduvano
 * pošto se qr_daily puni pri upisu i ne zna ništa o kasnijim isključivanjima.
 */
function qr_rebuild_daily(array $dates): void
{
    if (!$dates) {
        return;
    }
    $pdo = qr_db();

    foreach (array_unique($dates) as $date) {
        $del = $pdo->prepare('DELETE FROM qr_daily WHERE scan_date = ?');
        $del->execute([$date]);

        $st = $pdo->prepare('
            SELECT code, COUNT(*) AS scans, COUNT(DISTINCT visitor_hash) AS visitors
            FROM qr_scans WHERE excluded = 0 AND scan_date = ?
            GROUP BY code
        ');
        $st->execute([$date]);

        $ins = $pdo->prepare('INSERT INTO qr_daily (scan_date, code, scans, visitors) VALUES (?, ?, ?, ?)');
        foreach ($st->fetchAll() as $r) {
            $ins->execute([$date, $r['code'], (int) $r['scans'], (int) $r['visitors']]);
        }
    }
}

/* ── Admin akcije ──────────────────────────────────────────────────────── */

/** Datumi na koje utiče skup id-jeva — potrebni za preračun zbira. */
function qr_dates_for_ids(array $ids): array
{
    if (!$ids) {
        return [];
    }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = qr_db()->prepare("SELECT DISTINCT scan_date FROM qr_scans WHERE id IN ($in)");
    $st->execute($ids);
    return array_column($st->fetchAll(), 'scan_date');
}

/** Isključi ili vrati jedno skeniranje. Podatak ostaje u bazi. */
function qr_set_excluded(int $id, bool $excluded): int
{
    $dates = qr_dates_for_ids([$id]);
    $st = qr_db()->prepare('UPDATE qr_scans SET excluded = ? WHERE id = ?');
    $st->execute([$excluded ? 1 : 0, $id]);
    qr_rebuild_daily($dates);
    return $st->rowCount();
}

/** Isključi ili vrati ceo dan. */
function qr_set_excluded_day(string $date, bool $excluded): int
{
    $st = qr_db()->prepare('UPDATE qr_scans SET excluded = ? WHERE scan_date = ?');
    $st->execute([$excluded ? 1 : 0, $date]);
    qr_rebuild_daily([$date]);
    return $st->rowCount();
}

/** Isključi ili vrati sva skeniranja jedne kampanje. */
function qr_set_excluded_code(string $code, bool $excluded): int
{
    $st = qr_db()->prepare('SELECT DISTINCT scan_date FROM qr_scans WHERE code = ?');
    $st->execute([$code]);
    $dates = array_column($st->fetchAll(), 'scan_date');

    $up = qr_db()->prepare('UPDATE qr_scans SET excluded = ? WHERE code = ?');
    $up->execute([$excluded ? 1 : 0, $code]);
    qr_rebuild_daily($dates);
    return $up->rowCount();
}

/** Trajno obriši sve isključene zapise. Nepovratno. */
function qr_delete_excluded(): int
{
    $st = qr_db()->prepare('SELECT DISTINCT scan_date FROM qr_scans WHERE excluded = 1');
    $st->execute();
    $dates = array_column($st->fetchAll(), 'scan_date');

    $del = qr_db()->prepare('DELETE FROM qr_scans WHERE excluded = 1');
    $del->execute();
    qr_rebuild_daily($dates);
    return $del->rowCount();
}

/** Trajno obriši jedan dan, uključujući njegov red u zbiru. */
function qr_delete_day(string $date): int
{
    $del = qr_db()->prepare('DELETE FROM qr_scans WHERE scan_date = ?');
    $del->execute([$date]);
    qr_db()->prepare('DELETE FROM qr_daily WHERE scan_date = ?')->execute([$date]);
    return $del->rowCount();
}

/** Pun reset: briše sve, i pojedinačne zapise i zbir. Nepovratno. */
function qr_reset_all(): int
{
    $n = (int) qr_db()->query('SELECT COUNT(*) FROM qr_scans')->fetchColumn();
    qr_db()->exec('DELETE FROM qr_scans');
    qr_db()->exec('DELETE FROM qr_daily');
    return $n;
}

/* ── CSRF ──────────────────────────────────────────────────────────────── */

/**
 * Token bez sesije — izveden iz soli i korisničkog imena.
 * Basic Auth ostaje keširan u browseru, pa bez ovoga tuđa stranica može da
 * pošalje POST na dashboard dok si prijavljen.
 */
function qr_csrf_token(): string
{
    return hash_hmac('sha256', 'qr-admin|' . QR_DASHBOARD_USER . '|' . gmdate('Y-m-d'), QR_SALT);
}

function qr_csrf_ok(?string $token): bool
{
    return is_string($token) && hash_equals(qr_csrf_token(), $token);
}
