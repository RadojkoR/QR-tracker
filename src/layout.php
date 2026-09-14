<?php
/** Zajednički delovi admin stranica: <head>, gornja traka, prekidač teme. */

function h(?string $s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

function qr_head(string $title): void
{
    ?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= h($title) ?> · QR tracker</title>
<link rel="stylesheet" href="assets/admin.css">
<script>
  // Tema pre iscrtavanja — bez bljeska. 'light' | 'dark' | nije postavljeno = sistem.
  try { var t = localStorage.getItem('qr-theme'); if (t) document.documentElement.dataset.theme = t; } catch (e) {}
</script>
    <?php
}

function qr_topbar(string $active): void
{
    $links = ['dashboard.php' => 'Statistika', 'generator.php' => 'QR generator'];
    ?>
<div class="topbar">
  <div class="topbar-in">
    <a class="brand" href="dashboard.php">
      <span class="brand-mark" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M3 3h8v8H3V3zm2 2v4h4V5H5zm8-2h8v8h-8V3zm2 2v4h4V5h-4zM3 13h8v8H3v-8zm2 2v4h4v-4H5zm8-2h2v2h-2v-2zm4 0h4v2h-2v2h-2v-4zm-4 4h2v2h2v2h-4v-4zm6 2h2v2h-2v-2z"/></svg>
      </span>
      QR tracker
    </a>
    <nav class="nav">
      <?php foreach ($links as $href => $label): ?>
        <a href="<?= $href ?>" <?= $href === $active ? 'aria-current="page"' : '' ?>><?= $label ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="topbar-end">
      <button class="icon-btn" id="themeBtn" type="button" title="Promeni temu" aria-label="Promeni temu">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
      </button>
    </div>
  </div>
</div>
<script>
  document.getElementById('themeBtn').addEventListener('click', function () {
    var root = document.documentElement;
    var dark = root.dataset.theme ? root.dataset.theme === 'dark'
                                  : matchMedia('(prefers-color-scheme: dark)').matches;
    root.dataset.theme = dark ? 'light' : 'dark';
    try { localStorage.setItem('qr-theme', root.dataset.theme); } catch (e) {}
  });
</script>
    <?php
}
