<?php
/** Statistika QR skeniranja + admin akcije. Basic Auth — isključivo preko HTTPS-a. */

require_once __DIR__ . '/src/config.php';
require_once __DIR__ . '/src/stats.php';
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/layout.php';

qr_require_auth();

/* ── Veza sa bazom — umesto golog 500 pokaži šta ne valja ─────────────── */

try {
    qr_db();
} catch (Throwable $e) {
    error_log('[qr-tracker dashboard] ' . $e->getMessage());
    qr_db_error_page($e);
    exit;
}

/** Stranica sa uzrokom greške baze. Vidi je samo prijavljen admin; lozinka baze nije u poruci. */
function qr_db_error_page(Throwable $e): void
{
    $msg  = $e->getMessage();
    $hint = match (true) {
        str_contains($msg, 'could not find driver')
            => 'PHP na serveru nema MySQL ekstenziju. cPanel → Select PHP Version → Extensions: uključi <b>pdo_mysql</b>.',
        str_contains($msg, '[1045]')
            => 'Pogrešan korisnik ili lozinka baze. Proveri <code>QR_MYSQL_USER</code> (puno ime, sa prefiksom naloga) i <code>QR_MYSQL_PASS</code> (lozinka <b>korisnika baze</b>, ne cPanel naloga).',
        str_contains($msg, '[1044]')
            => 'Lozinka je dobra, ali korisnik ne može u ovu bazu. Jedno od dvoje: (1) ime baze u <code>QR_MYSQL_NAME</code> nije tačno — mora biti puno ime sa prefiksom; ili (2) korisnik nije dodat u bazu: cPanel → MySQL Databases → <b>Add User To Database</b> → izaberi korisnika i bazu → ALL PRIVILEGES.',
        str_contains($msg, '[1049]')
            => 'Baza sa tim imenom ne postoji. Proveri <code>QR_MYSQL_NAME</code> — puno ime sa prefiksom, tačno kako piše u cPanel → MySQL Databases.',
        str_contains($msg, '[2002]'), str_contains($msg, '[2006]')
            => 'MySQL server nije dostupan na toj adresi. Na cPanelu <code>QR_MYSQL_HOST</code> treba da bude <code>localhost</code>.',
        str_contains($msg, 'unable to open database'), str_contains($msg, 'readonly')
            => 'Na serveru je <code>QR_DB_DRIVER</code> postavljen na <code>sqlite</code>. Za cPanel mora biti <code>mysql</code>.',
        default
            => 'Proveri MySQL podatke u <code>src/config.php</code> na serveru.',
    };
    http_response_code(500);
    ?>
<!doctype html>
<html lang="sr">
<head>
<?php qr_head('Greška baze'); ?>
</head>
<body>
<?php qr_topbar('dashboard.php'); ?>
<main class="page" style="max-width:760px">
  <section class="card">
    <div class="card-head"><h2 style="color:var(--danger)">Dashboard ne može da se poveže sa bazom</h2></div>
    <p style="margin:0 0 14px"><?= $hint ?></p>
    <div class="sub" style="margin-bottom:6px">Poruka baze:</div>
    <pre style="white-space:pre-wrap;word-break:break-word;background:var(--surface-2);border:1px solid var(--line);border-radius:9px;padding:10px 12px;margin:0;font-size:12.5px"><?= h($msg) ?></pre>
    <p class="sub" style="margin:14px 0 0">
      Trenutno podešavanje: drajver <code><?= h(QR_DB_DRIVER) ?></code>
      <?php if (QR_DB_DRIVER === 'mysql'): ?>· host <code><?= h(QR_MYSQL_HOST) ?></code> · baza <code><?= h(QR_MYSQL_NAME) ?></code> · korisnik <code><?= h(QR_MYSQL_USER) ?></code><?php endif; ?>
    </p>
    <p class="sub" style="margin:8px 0 0">Posle izmene <code>src/config.php</code> na serveru samo osveži ovu stranicu. Skeniranja se ne upisuju dok baza ne radi, ali posetioci i dalje stižu na sajt.</p>
  </section>
</main>
</body>
</html>
    <?php
}

$days = (int) ($_GET['days'] ?? 30);
$days = in_array($days, [7, 30, 90, 365], true) ? $days : 30;

/* ── Admin akcije (POST → redirect → GET) ──────────────────────────────── */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $flash  = '';

    if (!qr_csrf_ok($_POST['csrf'] ?? null)) {
        $flash = 'Zahtev odbijen — neispravan token. Osveži stranicu i probaj ponovo.';
    } else {
        try {
            switch ($action) {
                case 'exclude_scan':
                    $n = qr_set_excluded((int) ($_POST['id'] ?? 0), true);
                    $flash = $n ? 'Skeniranje isključeno iz statistike.' : 'Nema takvog zapisa.';
                    break;

                case 'restore_scan':
                    $n = qr_set_excluded((int) ($_POST['id'] ?? 0), false);
                    $flash = $n ? 'Skeniranje vraćeno u statistiku.' : 'Nema takvog zapisa.';
                    break;

                case 'exclude_day':
                    $d = (string) ($_POST['date'] ?? '');
                    $n = qr_set_excluded_day($d, true);
                    $flash = "Isključeno $n skeniranja za $d.";
                    break;

                case 'restore_day':
                    $d = (string) ($_POST['date'] ?? '');
                    $n = qr_set_excluded_day($d, false);
                    $flash = "Vraćeno $n skeniranja za $d.";
                    break;

                case 'exclude_code':
                    $c = (string) ($_POST['code'] ?? '');
                    $n = qr_set_excluded_code($c, true);
                    $flash = "Isključeno $n skeniranja kampanje „$c\".";
                    break;

                case 'restore_code':
                    $c = (string) ($_POST['code'] ?? '');
                    $n = qr_set_excluded_code($c, false);
                    $flash = "Vraćeno $n skeniranja kampanje „$c\".";
                    break;

                case 'delete_excluded':
                    $n = qr_delete_excluded();
                    $flash = "Trajno obrisano $n isključenih zapisa.";
                    break;

                case 'delete_day':
                    $d = (string) ($_POST['date'] ?? '');
                    $n = qr_delete_day($d);
                    $flash = "Trajno obrisano $n zapisa za $d.";
                    break;

                case 'reset_all':
                    if (($_POST['confirm'] ?? '') !== 'RESET') {
                        $flash = 'Pun reset nije izvršen — nije upisano RESET.';
                    } else {
                        $n = qr_reset_all();
                        $flash = "Sve obrisano: $n zapisa. Statistika kreće od nule.";
                    }
                    break;

                default:
                    $flash = 'Nepoznata akcija.';
            }
        } catch (Throwable $e) {
            error_log('[qr-tracker admin] ' . $e->getMessage());
            $flash = 'Greška pri izvršavanju — detalji su u error logu.';
        }
    }

    // Vrati korisnika tamo gde je kliknuo — poruka se prikaže kao obaveštenje pri dnu ekrana.
    $anchor = str_ends_with($action, '_scan') ? '#skeniranja' : '#upravljanje';
    header('Location: ?days=' . $days . '&msg=' . rawurlencode($flash) . $anchor);
    exit;
}

/* ── CSV izvoz ─────────────────────────────────────────────────────────── */

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="qr-scans-' . gmdate('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['vreme_lokalno', 'kod', 'uredjaj', 'os', 'browser', 'jezik', 'drzava', 'uputni_sajt', 'ponovljeno', 'iskljuceno']);
    foreach (qr_recent(5000) as $r) {
        fputcsv($out, [
            qr_local($r['scanned_at'], 'Y-m-d H:i:s'), $r['code'], $r['device'], $r['os'],
            $r['browser'], $r['lang'], $r['country'], $r['referrer'],
            $r['is_repeat'] ? 'da' : 'ne', $r['excluded'] ? 'da' : 'ne',
        ]);
    }
    exit;
}

/* ── Podaci ────────────────────────────────────────────────────────────── */

$totals   = qr_totals($days);
$previous = qr_totals_previous($days);
$allTime  = qr_all_time();
$byCode   = qr_by_code($days);
$series   = qr_daily_series($days);
$hours    = qr_by_hour($days);
$recent   = qr_recent(30);
$dates    = qr_dates_with_data();
$excluded = qr_excluded_count();
$csrf     = qr_csrf_token();
$msg      = (string) ($_GET['msg'] ?? '');

$periods = [7 => '7 dana', 30 => '30 dana', 90 => '90 dana', 365 => '12 meseci'];

/* ── Pomoćne funkcije za prikaz ────────────────────────────────────────── */

function fmt(int $n): string { return number_format($n, 0, ',', '.'); }

/** Promena u odnosu na prethodni period: [klasa, strelica, tekst] ili null. */
function delta(int $now, int $prev): ?array
{
    if ($prev === 0) {
        return $now > 0 ? ['up', '▲', 'novo'] : null;
    }
    $pct = (int) round(($now - $prev) / $prev * 100);
    if ($pct === 0) {
        return ['flat', '•', '0%'];
    }
    return $pct > 0 ? ['up', '▲', '+' . $pct . '%'] : ['down', '▼', $pct . '%'];
}

/** Okrugli korak ose (1, 2, 5 × 10ⁿ) tako da 4 podeoka pokriju maksimum. */
function axis_step(int $max): int
{
    for ($k = 1; ; $k *= 10) {
        foreach ([1, 2, 5] as $m) {
            if ($m * $k * 4 >= $max) {
                return $m * $k;
            }
        }
    }
}

function short_date(string $ymd): string
{
    [$y, $m, $d] = explode('-', $ymd);
    return (int) $d . '.' . (int) $m . '.';
}

$deviceNames = ['mobile' => 'Mobilni', 'desktop' => 'Računar', 'tablet' => 'Tablet'];

/* Dnevna serija; za 12 meseci se sabira po nedeljama da stubići ostanu čitljivi. */
$points = [];
if ($days === 365) {
    foreach (array_reverse(array_chunk(array_reverse($series), 7)) as $week) {
        $week = array_reverse($week);
        $points[] = [
            'label'    => short_date($week[0]['date']),
            'title'    => 'Nedelja od ' . short_date($week[0]['date']) . ' do ' . short_date(end($week)['date']),
            'scans'    => array_sum(array_column($week, 'scans')),
            'visitors' => array_sum(array_column($week, 'visitors')),
        ];
    }
} else {
    foreach ($series as $p) {
        $points[] = [
            'label'    => short_date($p['date']),
            'title'    => short_date($p['date']) . substr($p['date'], 0, 4) . '.',
            'scans'    => $p['scans'],
            'visitors' => $p['visitors'],
        ];
    }
}
$dayStep  = axis_step(max(array_column($points, 'scans')));
$hourStep = axis_step(max($hours));

/* Kampanje: sve iz konfiguracije (i one sa nula skeniranja) + stare iz baze. */
$campaigns = [];
foreach (QR_DESTINATIONS as $code => $url) {
    $campaigns[$code] = ['code' => $code, 'url' => $url, 'scans' => 0, 'visitors' => 0];
}
foreach ($byCode as $r) {
    $campaigns[$r['code']] = [
        'code' => $r['code'], 'url' => QR_DESTINATIONS[$r['code']] ?? null,
        'scans' => (int) $r['scans'], 'visitors' => (int) $r['visitors'],
    ];
}
uasort($campaigns, fn ($a, $b) => $b['scans'] <=> $a['scans']);
$topCampaign = $totals['scans'] > 0 ? reset($campaigns) : null;

$isNew = $allTime['scans'] === 0 && $excluded === 0 && !$recent;

/** Stubičasti grafikon: server ga iscrta, JS dodaje samo tooltip. */
function column_chart(array $points, int $step, int $height, int $labelEvery, string $unit): void
{
    $top   = $step * 4;
    $total = array_sum(array_column($points, 'scans'));
    ?>
    <div class="chart" style="--h:<?= $height ?>px">
      <div class="y-axis" aria-hidden="true">
        <?php for ($i = 4; $i >= 0; $i--): ?><span><?= fmt($step * $i) ?></span><?php endfor; ?>
      </div>
      <div class="plot">
        <div class="gridlines" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></div>
        <div class="bars">
          <?php foreach ($points as $p): ?>
            <div class="bar" tabindex="0"
                 data-title="<?= h($p['title']) ?>" data-value="<?= fmt($p['scans']) ?> <?= h($unit) ?>"
                 data-extra="<?= isset($p['visitors']) ? fmt($p['visitors']) . ' različitih' : '' ?>"
                 aria-label="<?= h($p['title']) ?>: <?= fmt($p['scans']) ?> <?= h($unit) ?>">
              <span style="height:<?= $p['scans'] ? max(1.5, round($p['scans'] / $top * 100, 2)) : 0 ?>%"></span>
            </div>
          <?php endforeach; ?>
        </div>
        <?php if ($total === 0): ?><div class="chart-empty">Nema skeniranja u ovom periodu</div><?php endif; ?>
      </div>
      <div class="x-axis" aria-hidden="true">
        <?php $last = count($points) - 1; foreach ($points as $i => $p): ?>
          <span><?= ($i % $labelEvery === 0 && $last - $i >= $labelEvery / 2) || $i === $last ? h($p['label']) : '' ?></span>
        <?php endforeach; ?>
      </div>
    </div>
    <?php
}

/** Lista sa horizontalnim trakama — udeo u ukupnom broju. */
function bar_list(array $rows, int $total, int $limit = 6): void
{
    if (!$rows) {
        echo '<div class="mini-empty">Nema podataka</div>';
        return;
    }
    $rows = array_slice($rows, 0, $limit);
    echo '<ul class="hlist">';
    foreach ($rows as $r) {
        $pct = $total ? (int) round($r['n'] / $total * 100) : 0;
        printf(
            '<li class="hrow"><span class="name">%s</span><span class="val">%s<small>%d%%</small></span>'
            . '<span class="track"><i style="width:%s%%"></i></span></li>',
            h($r['label']), fmt((int) $r['n']), $pct, $total ? round($r['n'] / $total * 100, 1) : 0
        );
    }
    echo '</ul>';
}
?>
<!doctype html>
<html lang="sr">
<head>
<?php qr_head('Statistika'); ?>
</head>
<body>
<?php qr_topbar('dashboard.php'); ?>

<main class="page">

  <div class="page-head">
    <div>
      <h1>Statistika</h1>
      <div class="sub">Vremena u zoni <?= h(QR_DISPLAY_TZ) ?> · pojedinačni zapisi se brišu posle <?= (int) QR_RETENTION_DAYS ?> dana</div>
    </div>
    <nav class="segmented" aria-label="Period">
      <?php foreach ($periods as $d => $label): ?>
        <a href="?days=<?= $d ?>" <?= $d === $days ? 'aria-current="true"' : '' ?>><?= $label ?></a>
      <?php endforeach; ?>
    </nav>
  </div>

  <div class="stack">

    <?php if ($isNew): ?>
      <section class="card onboard">
        <div>
          <h2>Još nema nijednog skeniranja</h2>
          <div class="sub">Tri koraka do prvog broja na ovoj stranici:</div>
          <ol>
            <li>Napravi QR kod za kampanju u generatoru.</li>
            <li>Skeniraj ga svojim telefonom — treba da se otvori sajt.</li>
            <li>Osveži ovu stranicu. Probno skeniranje posle isključiš jednim klikom.</li>
          </ol>
        </div>
        <a class="btn primary" href="generator.php">Napravi QR kod</a>
      </section>
    <?php endif; ?>

    <section class="kpis" aria-label="Pregled">
      <?php $dS = delta($totals['scans'], $previous['scans']); $dV = delta($totals['visitors'], $previous['visitors']); ?>
      <div class="card">
        <div class="kpi-label">Skeniranja</div>
        <div class="kpi-value"><?= fmt($totals['scans']) ?></div>
        <div class="kpi-foot">
          <?php if ($dS): ?><span class="delta <?= $dS[0] ?>"><span aria-hidden="true"><?= $dS[1] ?></span><?= $dS[2] ?></span><?php endif; ?>
          <span>naspram prethodnih <?= $days === 365 ? '12 meseci' : $days . ' dana' ?> (<?= fmt($previous['scans']) ?>)</span>
        </div>
      </div>
      <div class="card">
        <div class="kpi-label">Različiti ljudi</div>
        <div class="kpi-value"><?= fmt($totals['visitors']) ?></div>
        <div class="kpi-foot">
          <?php if ($dV): ?><span class="delta <?= $dV[0] ?>"><span aria-hidden="true"><?= $dV[1] ?></span><?= $dV[2] ?></span><?php endif; ?>
          <span title="Heš posetioca se menja svaka 24 časa, pa se isti čovek u dva dana broji dvaput.">brojano po danu</span>
        </div>
      </div>
      <div class="card">
        <div class="kpi-label">Ukupno ikada</div>
        <div class="kpi-value"><?= fmt($allTime['scans']) ?></div>
        <div class="kpi-foot">
          <span><?= fmt($allTime['visitors']) ?> različitih</span>
          <?php if ($excluded): ?><span class="pill"><?= fmt($excluded) ?> isključeno</span><?php endif; ?>
        </div>
      </div>
      <div class="card">
        <div class="kpi-label">Najbolji materijal</div>
        <?php if ($topCampaign): ?>
          <div class="kpi-value text"><span class="tag" style="font-size:18px;padding:2px 10px"><?= h($topCampaign['code']) ?></span></div>
          <div class="kpi-foot"><span><?= fmt($topCampaign['scans']) ?> skeniranja · <?= (int) round($topCampaign['scans'] / $totals['scans'] * 100) ?>% ukupno</span></div>
        <?php else: ?>
          <div class="kpi-value text" style="color:var(--muted)">—</div>
          <div class="kpi-foot"><span>nema skeniranja u periodu</span></div>
        <?php endif; ?>
      </div>
    </section>

    <section class="card">
      <div class="card-head">
        <h2>Skeniranja <?= $days === 365 ? 'po nedelji' : 'po danu' ?></h2>
        <span class="sub">Pređi mišem preko stubića za detalje</span>
      </div>
      <?php column_chart($points, $dayStep, 220, match ($days) { 7 => 1, 30 => 5, 90 => 14, 365 => 8 }, 'skeniranja'); ?>
      <details class="table-view">
        <summary>Prikaži kao tabelu</summary>
        <div class="scroll">
          <table>
            <thead><tr><th><?= $days === 365 ? 'Nedelja od' : 'Dan' ?></th><th class="num">Skeniranja</th><th class="num">Različiti</th></tr></thead>
            <tbody>
              <?php foreach (array_reverse($points) as $p): ?>
                <tr><td><?= h($p['title']) ?></td><td class="num"><?= fmt($p['scans']) ?></td><td class="num"><?= fmt($p['visitors']) ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </details>
    </section>

    <div class="cols-3-2">
      <section class="card">
        <div class="card-head">
          <h2>Po materijalu</h2>
          <span class="sub">Koji štampani materijal donosi skeniranja</span>
        </div>
        <ul class="hlist">
          <?php $maxCode = max(1, max(array_column($campaigns, 'scans'))); ?>
          <?php foreach ($campaigns as $c): ?>
            <li class="hrow">
              <span class="name">
                <span class="tag"><?= h($c['code']) ?></span>
                <span class="dim"><?= $c['url'] ? h(preg_replace('~^https?://~', '', rtrim($c['url'], '/'))) : 'nije više u konfiguraciji' ?></span>
              </span>
              <span class="val">
                <?= fmt($c['scans']) ?><small><?= fmt($c['visitors']) ?> različitih</small>
                <?php if ($c['url']): ?>
                  <a class="link-btn" href="generator.php?c=<?= rawurlencode($c['code']) ?>" title="Napravi QR kod za <?= h($c['code']) ?>">QR</a>
                <?php endif; ?>
              </span>
              <span class="track"><i class="<?= $c['scans'] ? '' : 'zero' ?>" style="width:<?= round($c['scans'] / $maxCode * 100, 1) ?>%"></i></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>

      <section class="card">
        <div class="card-head">
          <h2>Doba dana</h2>
          <span class="sub">lokalno vreme</span>
        </div>
        <?php
          $hourPoints = [];
          foreach ($hours as $hr => $n) {
              $hourPoints[] = ['label' => sprintf('%02d', $hr), 'title' => sprintf('%02d:00–%02d:59', $hr, $hr), 'scans' => $n];
          }
          column_chart($hourPoints, $hourStep, 170, 6, 'skeniranja');
        ?>
      </section>
    </div>

    <div class="cols-2">
      <?php foreach (['device' => 'Uređaj', 'os' => 'Operativni sistem', 'browser' => 'Pregledač', 'lang' => 'Jezik'] as $col => $title): ?>
        <section class="card">
          <div class="card-head"><h2><?= $title ?></h2></div>
          <?php
            $rows = qr_breakdown($col, $days);
            if ($col === 'device') {
                foreach ($rows as &$r) { $r['label'] = $deviceNames[$r['label']] ?? $r['label']; }
                unset($r);
            }
            bar_list($rows, $totals['scans']);
          ?>
        </section>
      <?php endforeach; ?>
    </div>

    <section class="card" id="skeniranja">
      <div class="card-head">
        <h2>Poslednja skeniranja</h2>
        <a class="btn sm" href="?days=<?= $days ?>&export=csv">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12m0 0-4-4m4 4 4-4M5 21h14"/></svg>
          Izvezi CSV
        </a>
      </div>
      <?php if (!$recent): ?>
        <div class="mini-empty">Ovde će se pojaviti svako skeniranje — vreme, materijal i uređaj.</div>
      <?php else: ?>
        <div class="scroll">
          <table>
            <thead><tr><th>Vreme</th><th>Materijal</th><th>Uređaj</th><th>Sistem</th><th>Pregledač</th><th>Jezik</th><th></th><th class="num"><span class="sr">Akcija</span></th></tr></thead>
            <tbody>
            <?php foreach ($recent as $r): ?>
              <tr class="<?= $r['excluded'] ? 'off' : '' ?>">
                <td><?= h(qr_local($r['scanned_at'])) ?></td>
                <td><span class="tag"><?= h($r['code']) ?></span></td>
                <td><?= h($deviceNames[$r['device']] ?? $r['device']) ?></td>
                <td><?= h($r['os']) ?></td>
                <td><?= h($r['browser']) ?></td>
                <td><?= h($r['lang'] ?: '—') ?></td>
                <td>
                  <?php if ($r['excluded']): ?><span class="pill">isključeno</span>
                  <?php elseif ($r['is_repeat']): ?><span class="pill" title="Ista osoba je već skenirala danas">ponovljeno</span><?php endif; ?>
                </td>
                <td class="num">
                  <form method="post" style="display:inline">
                    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <input type="hidden" name="action" value="<?= $r['excluded'] ? 'restore_scan' : 'exclude_scan' ?>">
                    <button class="link-btn" type="submit"><?= $r['excluded'] ? 'Vrati' : 'Isključi' ?></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <details class="card manage" id="upravljanje">
      <summary>
        Upravljanje podacima
        <span class="sub">isključivanje, brisanje, reset</span>
        <svg class="chev" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
      </summary>
      <div class="manage-body">

        <div class="manage-row">
          <div>
            <h3>Sopstvene probe</h3>
            <p>Najbolje je da se uopšte ne upišu.</p>
          </div>
          <p style="margin:0;align-self:center">Dodaj <code>&amp;nt=<?= h(QR_NOTRACK_TOKEN ?: 'TOKEN') ?></code> na <code>q.php?c=kod</code> — preusmeri, ali ne beleži ništa. „Isključi" ostavlja zapis u bazi i može da se vrati.</p>
        </div>

        <div class="manage-row">
          <div>
            <h3>Ceo dan</h3>
            <p>Datumi su u UTC.</p>
          </div>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <select name="date" aria-label="Dan" <?= $dates ? '' : 'disabled' ?>>
              <?php foreach ($dates as $d): ?>
                <option value="<?= h($d['scan_date']) ?>"><?= h($d['scan_date']) ?> (<?= (int) $d['n'] ?>)</option>
              <?php endforeach; ?>
              <?php if (!$dates): ?><option value="">nema podataka</option><?php endif; ?>
            </select>
            <button class="btn" type="submit" name="action" value="exclude_day" <?= $dates ? '' : 'disabled' ?>>Isključi</button>
            <button class="btn" type="submit" name="action" value="restore_day" <?= $dates ? '' : 'disabled' ?>>Vrati</button>
          </form>
        </div>

        <div class="manage-row">
          <div>
            <h3>Cela kampanja</h3>
            <p>Sva skeniranja jednog materijala.</p>
          </div>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <select name="code" aria-label="Kampanja">
              <?php foreach (array_keys(QR_DESTINATIONS) as $c): ?>
                <option value="<?= h($c) ?>"><?= h($c) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn" type="submit" name="action" value="exclude_code">Isključi</button>
            <button class="btn" type="submit" name="action" value="restore_code">Vrati</button>
          </form>
        </div>

        <div class="manage-row danger">
          <div>
            <h3>Trajno brisanje</h3>
            <p>Nepovratno.</p>
          </div>
          <div style="display:grid;gap:10px">
            <form method="post" onsubmit="return confirm('Trajno obrisati sve isključene zapise?')">
              <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
              <button class="btn danger" type="submit" name="action" value="delete_excluded" <?= $excluded ? '' : 'disabled' ?>>
                Obriši isključene (<?= fmt($excluded) ?>)
              </button>
            </form>
            <form method="post" onsubmit="return confirm('Trajno obrisati sve zapise za izabrani dan?')">
              <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
              <select name="date" aria-label="Dan za brisanje" <?= $dates ? '' : 'disabled' ?>>
                <?php foreach ($dates as $d): ?>
                  <option value="<?= h($d['scan_date']) ?>"><?= h($d['scan_date']) ?> (<?= (int) $d['n'] ?>)</option>
                <?php endforeach; ?>
                <?php if (!$dates): ?><option value="">nema podataka</option><?php endif; ?>
              </select>
              <button class="btn danger" type="submit" name="action" value="delete_day" <?= $dates ? '' : 'disabled' ?>>Obriši dan</button>
            </form>
          </div>
        </div>

        <div class="manage-row danger">
          <div>
            <h3>Pun reset</h3>
            <p>Briše sve, i istorijski zbir — „ukupno ikada" postaje nula.</p>
          </div>
          <form method="post" onsubmit="return confirm('Briše SVE — i pojedinačne zapise i istorijski zbir. Nepovratno.')">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="action" value="reset_all">
            <input type="text" name="confirm" id="resetConfirm" placeholder="upiši RESET" autocomplete="off" aria-label="Potvrda: upiši RESET">
            <button class="btn danger" type="submit" id="resetBtn" disabled>Obriši sve</button>
          </form>
        </div>

      </div>
    </details>

  </div>

  <p class="foot">Ne beleže se IP adrese i ne postavljaju se kolačići. Posetilac se razlikuje preko nepovratnog heša koji se menja svaka 24 časa.</p>
</main>

<?php if ($msg !== ''): ?>
  <div class="flash toast" role="status" id="toast">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v4h1"/></svg>
    <span class="grow"><?= h($msg) ?></span>
    <button class="link-btn" type="button" onclick="this.parentNode.remove()" aria-label="Zatvori">✕</button>
  </div>
<?php endif; ?>

<div class="tip" id="tip" hidden></div>

<script>
(function () {
  // Tooltip za stubiće — ista informacija i na fokus tastaturom.
  var tip = document.getElementById('tip');
  function show(bar) {
    tip.textContent = '';
    var v = document.createElement('b'); v.textContent = bar.dataset.value;
    var t = document.createElement('small'); t.textContent = bar.dataset.title;
    tip.appendChild(v);
    if (bar.dataset.extra) { var e = document.createElement('small'); e.textContent = bar.dataset.extra; tip.appendChild(e); }
    tip.appendChild(t);
    var r = bar.getBoundingClientRect();
    var x = Math.min(Math.max(r.left + r.width / 2, 80), window.innerWidth - 80);
    var span = bar.firstElementChild.getBoundingClientRect();
    tip.style.left = x + 'px';
    tip.style.top = Math.min(span.top, r.bottom - 8) + 'px';
    tip.hidden = false;
  }
  document.querySelectorAll('.bar').forEach(function (bar) {
    bar.addEventListener('pointerenter', function () { show(bar); });
    bar.addEventListener('focus', function () { show(bar); });
    bar.addEventListener('pointerleave', function () { tip.hidden = true; });
    bar.addEventListener('blur', function () { tip.hidden = true; });
  });
  window.addEventListener('scroll', function () { tip.hidden = true; }, { passive: true });

  // Pun reset se otključava tek kad je upisano RESET.
  var rc = document.getElementById('resetConfirm'), rb = document.getElementById('resetBtn');
  rc.addEventListener('input', function () { rb.disabled = rc.value !== 'RESET'; });

  // Posle admin akcije otvori panel u kom je korisnik radio.
  if (location.hash === '#upravljanje') document.getElementById('upravljanje').open = true;

  // Poruka nestane sama, a URL se očisti da se ne ponovi pri osvežavanju.
  var toast = document.getElementById('toast');
  if (toast) {
    setTimeout(function () { toast.remove(); }, 7000);
    var u = new URL(location.href); u.searchParams.delete('msg');
    history.replaceState(null, '', u.pathname + u.search + u.hash);
  }
})();
</script>
</body>
</html>
