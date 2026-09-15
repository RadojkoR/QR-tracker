<?php
/** QR generator — pravi SVG (za štampu) i PNG za kampanje iz QR_DESTINATIONS. Basic Auth. */

require_once __DIR__ . '/src/config.php';
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/layout.php';

qr_require_auth();

$base     = defined('QR_PUBLIC_BASE') ? QR_PUBLIC_BASE : 'https://go.webhubstudio.com';
$codes    = array_keys(QR_DESTINATIONS);
$selected = in_array($_GET['c'] ?? '', $codes, true) ? $_GET['c'] : QR_DEFAULT_CODE;

$js = json_encode([
    'token' => QR_NOTRACK_TOKEN,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);

header('X-Robots-Tag: noindex, nofollow');
?>
<!doctype html>
<html lang="sr">
<head>
<?php qr_head('QR generator'); ?>
<style>
  .gen{display:grid;gap:16px;grid-template-columns:minmax(0,1fr) minmax(0,420px);align-items:start}
  @media(max-width:860px){.gen{grid-template-columns:1fr}}
  .gen .card h2{margin:0 0 16px;font-size:15px;font-weight:620}
  .group{padding-top:16px;margin-top:4px;border-top:1px solid var(--line)}
  .group-title{font-size:12px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.7px;margin:0 0 12px}
  .inline{display:grid;gap:12px;grid-template-columns:repeat(2,minmax(0,1fr))}
  @media(max-width:460px){.inline{grid-template-columns:1fr}}
  .color{display:flex;align-items:center;gap:10px;border:1px solid var(--line);border-radius:9px;padding:5px 10px 5px 5px;background:var(--surface)}
  .color input[type=color]{width:30px;height:30px;padding:0;border:none;border-radius:7px;background:none;cursor:pointer}
  .color input[type=color]::-webkit-color-swatch-wrapper{padding:0}
  .color input[type=color]::-webkit-color-swatch{border:1px solid var(--line);border-radius:7px}
  .color code{background:none;border:none;padding:0;color:var(--ink-2)}
  .check{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--ink-2);cursor:pointer;margin-top:10px}
  .check input{width:16px;height:16px;accent-color:var(--accent)}
  .segmented.full{display:flex}
  .segmented.full label{flex:1;text-align:center}

  .sticky{position:sticky;top:76px}
  .preview{border-radius:12px;display:flex;align-items:center;justify-content:center;padding:24px;aspect-ratio:1/1;max-width:100%;
    background:repeating-conic-gradient(var(--surface-2) 0 25%,var(--surface) 0 50%) 0 0/16px 16px;border:1px solid var(--line)}
  .preview svg{width:100%;height:auto;display:block;border-radius:4px;box-shadow:0 4px 16px #0f172a1a}
  .preview .hint{color:var(--muted);text-align:center}
  .url{display:flex;align-items:center;gap:8px;margin-top:14px;background:var(--surface-2);border:1px solid var(--line);border-radius:9px;padding:6px 6px 6px 12px}
  .url span{flex:1;min-width:0;font:12.5px ui-monospace,Consolas,monospace;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .warns{display:grid;gap:6px;margin-top:12px}
  .warns div{display:flex;gap:8px;align-items:flex-start;font-size:12.5px;color:var(--danger);background:var(--danger-soft);border-radius:8px;padding:7px 10px}
  .specs{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin-top:12px}
  .specs div{background:var(--surface-2);border-radius:9px;padding:8px 10px}
  .specs dt{font-size:11.5px;color:var(--muted)} .specs dd{margin:0;font-weight:600;font-variant-numeric:tabular-nums}
  .actions{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:14px}
  .actions .btn{justify-content:center}
  .note{color:var(--muted);font-size:12.5px;margin:14px 0 0}

  .drop{display:flex;align-items:center;gap:14px;border:1.5px dashed var(--line);border-radius:11px;padding:14px 16px;
    cursor:pointer;color:var(--muted);background:var(--surface-2);transition:border-color .15s,background .15s;margin-bottom:16px}
  .drop:hover,.drop.over{border-color:var(--accent);background:var(--accent-soft)}
  .drop:has(input:focus-visible){outline:2px solid var(--accent);outline-offset:2px}
  .drop input{position:absolute;opacity:0;width:1px;height:1px}
  .drop svg{width:26px;height:26px;flex:none;color:var(--accent-ink)}
  .drop strong{color:var(--ink)}
  .drop small{font-size:12px}
  .logo-file{display:flex;align-items:center;gap:12px;border:1px solid var(--line);border-radius:11px;padding:8px 8px 8px 8px}
  .logo-file img{width:44px;height:44px;object-fit:contain;border-radius:7px;flex:none;
    background:repeating-conic-gradient(var(--surface-2) 0 25%,var(--surface) 0 50%) 0 0/10px 10px;border:1px solid var(--line)}
  .logo-file span{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:500}
  input[type=range]{width:100%;accent-color:var(--accent)}
  .zoom{display:flex;align-items:center;gap:8px}
  .zoom input{flex:1;min-width:0}
  .zoom .btn{width:32px;justify-content:center;padding:4px 0;font-size:16px;line-height:1}
  .field output{float:right;color:var(--muted);font-weight:500;font-variant-numeric:tabular-nums}

  .scan{display:flex;gap:8px;align-items:center;font-size:12.5px;font-weight:550;border-radius:8px;padding:7px 10px;margin-top:12px}
  .scan.ok{color:var(--good);background:var(--good-soft)}
  .scan.bad{color:var(--danger);background:var(--danger-soft)}
  .scan.wait{color:var(--muted);background:var(--surface-2)}
</style>
</head>
<body>
<?php qr_topbar('generator.php'); ?>

<main class="page">
  <div class="page-head">
    <div>
      <h1>QR generator</h1>
      <div class="sub">SVG za štampu, PNG za brzu proveru</div>
    </div>
  </div>

  <div class="gen">
    <section class="card">
      <h2>Sadržaj</h2>

      <div class="segmented full" role="radiogroup" aria-label="Vrsta koda" style="margin-bottom:16px">
        <label><input type="radio" name="mode" value="campaign" checked>Kampanja · prati se</label>
        <label><input type="radio" name="mode" value="custom">Običan link · ne prati se</label>
      </div>

      <div id="campaignFields">
        <label class="field">
          <span>Kampanja</span>
          <select id="code">
            <?php foreach ($codes as $c): ?>
              <option value="<?= h($c) ?>" <?= $c === $selected ? 'selected' : '' ?>><?= h($c) ?> → <?= h(preg_replace('~^https?://~', '', QR_DESTINATIONS[$c])) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="help">Nova kampanja se dodaje u <code>QR_DESTINATIONS</code> u <code>src/config.php</code>.</div>
        </label>
        <label class="field" style="margin-bottom:0">
          <span>Adresa trackera</span>
          <input type="url" id="base" value="<?= h(rtrim($base, '/')) ?>" spellcheck="false">
        </label>
      </div>

      <div id="customFields" hidden>
        <label class="field" style="margin-bottom:0">
          <span>Link ili tekst</span>
          <input type="text" id="custom" placeholder="https://" spellcheck="false">
          <div class="help">Ide direktno u QR, bez trackera — ova skeniranja se neće videti u statistici.</div>
        </label>
      </div>

      <div class="group" style="margin-top:16px">
        <p class="group-title">Izgled</p>
        <div class="inline">
          <div class="field">
            <span>Boja koda</span>
            <label class="color"><input type="color" id="dark" value="#0f172a"><code id="darkHex">#0f172a</code></label>
          </div>
          <div class="field">
            <span>Pozadina</span>
            <label class="color"><input type="color" id="light" value="#ffffff"><code id="lightHex">#ffffff</code></label>
          </div>
        </div>
        <label class="check" style="margin:-6px 0 16px"><input type="checkbox" id="transparent"> Providna pozadina u SVG-u</label>
      </div>

      <div class="group">
        <p class="group-title">Logo u sredini</p>
        <label class="drop" id="drop">
          <input type="file" id="logoFile" accept="image/png,image/jpeg,image/webp,image/svg+xml">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V4m0 0-4 4m4-4 4 4M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/></svg>
          <span><strong>Izaberi sliku</strong> ili je prevuci ovde<br><small>PNG, JPG, WEBP ili SVG · do 2 MB</small></span>
        </label>
        <div class="logo-file" id="logoInfo" hidden>
          <img id="logoThumb" alt="">
          <span id="logoName"></span>
          <button class="btn sm" type="button" id="logoRemove">Ukloni</button>
        </div>
        <div id="logoOptions" hidden style="margin:14px 0 16px">
          <div class="segmented full" role="radiogroup" aria-label="Položaj logoa" style="margin-bottom:14px">
            <label><input type="radio" name="shape" value="band" checked>Traka preko cele širine</label>
            <label><input type="radio" name="shape" value="square">Kvadrat u sredini</label>
          </div>
          <div class="field" id="bandColorField" style="margin-bottom:14px">
            <span>Boja trake</span>
            <label class="color"><input type="color" id="bandColor" value="#ffffff"><code id="bandColorHex">#ffffff</code></label>
          </div>
          <div class="field" style="margin-bottom:0">
            <span id="logoSizeLabel">Visina trake <output id="logoSizeOut">14%</output></span>
            <input type="range" id="logoSize" min="8" max="18" value="14" step="1">
            <div class="help" id="logoHelp"></div>
          </div>
          <div class="field" style="margin:14px 0 0">
            <span>Zoom logoa <output id="logoZoomOut">100%</output></span>
            <div class="zoom">
              <button class="btn sm" type="button" id="zoomOut" aria-label="Umanji logo">−</button>
              <input type="range" id="logoZoom" min="40" max="300" value="100" step="5" aria-label="Zoom logoa">
              <button class="btn sm" type="button" id="zoomIn" aria-label="Uvećaj logo">+</button>
              <button class="link-btn" type="button" id="zoomReset" title="Vrati na 100%">Vrati</button>
            </div>
            <div class="help">Uvećan logo se odseče na ivici svoje površine — nikad ne prekriva kod.</div>
          </div>
        </div>
        <div class="warns" id="logoErr" hidden style="margin:10px 0 16px"></div>
      </div>

      <div class="group">
        <p class="group-title">Tehnički</p>
        <label class="field">
          <span>Korekcija greške</span>
          <select id="ecc">
            <option value="L">L — 7% · najmanji kod</option>
            <option value="M">M — 15%</option>
            <option value="Q" selected>Q — 25% · preporuka za štampu</option>
            <option value="H">H — 30% · obavezno ako ide logo u sredinu</option>
          </select>
        </label>
        <div class="inline">
          <label class="field" style="margin-bottom:0">
            <span>Tiha zona (moduli)</span>
            <input type="number" id="margin" min="0" max="10" value="4">
          </label>
          <label class="field" style="margin-bottom:0">
            <span>PNG veličina</span>
            <select id="pngSize">
              <option value="512">oko 512 px</option>
              <option value="1024" selected>oko 1024 px</option>
              <option value="2048">oko 2048 px</option>
              <option value="4096">oko 4096 px</option>
            </select>
          </label>
        </div>
      </div>
    </section>

    <section class="card sticky">
      <h2>Pregled</h2>
      <div class="preview" id="preview"></div>

      <div class="url">
        <span id="urlOut">—</span>
        <button class="btn sm" type="button" id="copyBtn">Kopiraj</button>
      </div>

      <div class="scan" id="scan" hidden></div>
      <div class="warns" id="warn" hidden></div>

      <dl class="specs" id="meta" hidden>
        <div><dt>Verzija</dt><dd id="mVer"></dd></div>
        <div><dt>Moduli</dt><dd id="mMod"></dd></div>
        <div><dt>PNG</dt><dd id="mPng"></dd></div>
      </dl>

      <div class="actions">
        <button class="btn primary" type="button" id="dlSvg">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12m0 0-4-4m4 4 4-4M5 21h14"/></svg>
          SVG
        </button>
        <button class="btn" type="button" id="dlPng">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12m0 0-4-4m4 4 4-4M5 21h14"/></svg>
          PNG
        </button>
      </div>

      <p class="note" id="testBox">
        <a id="testLink" href="#" target="_blank" rel="noopener">Proba bez beleženja</a> — preusmeri kao pravo skeniranje, ali ne upiše ništa.
      </p>
      <p class="note">Najmanje <strong>2 cm</strong> na štampi, bez sečenja tihe zone. Skeniraj svojim telefonom pre slanja u štampu.</p>
    </section>
  </div>
</main>

<script src="assets/qrcode.js"></script>
<script src="assets/jsQR.js"></script>
<script>
(function () {
  var CFG = <?= $js ?>;
  var $ = function (id) { return document.getElementById(id); };
  qrcode.stringToBytes = qrcode.stringToBytesFuncs['UTF-8'];

  var current = null;   // { qr, n, name, text }
  var logo = null;      // { url, img, w, h, name }
  var DATA_URL = /^data:image\/(png|jpeg|webp|svg\+xml);base64,[A-Za-z0-9+\/=]+$/;

  function mode() { return document.querySelector('input[name=mode]:checked').value; }

  function luminance(hex) {
    var c = [1, 3, 5].map(function (i) {
      var v = parseInt(hex.substr(i, 2), 16) / 255;
      return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
  }

  function margin() {
    var m = parseInt($('margin').value, 10);
    return isNaN(m) ? 4 : Math.max(0, Math.min(10, m));
  }

  function payload() {
    if (mode() === 'custom') {
      return { text: $('custom').value.trim(), name: 'qr-custom' };
    }
    var code = $('code').value;
    var base = $('base').value.trim().replace(/\/+$/, '');
    return { text: base + '/' + code, name: 'qr-' + code, code: code, base: base };
  }

  /* ── Logo: traka preko cele širine ili kvadrat u sredini ─────────────── */

  function shape() { return document.querySelector('input[name=shape]:checked').value; }

  // Traka seče ceo red modula — na malim kodovima to odnese previše podataka odjednom.
  // Merenjem: od verzije 6 uz korekciju H traka do 18% visine se i dalje čita.
  var BAND_MIN_VERSION = 6;

  // Oblast bez modula, u modulima. Ista parnost kao n, da stoji tačno u centru.
  function logoBox(n) {
    if (!logo) return null;
    var size = Math.round(n * parseInt($('logoSize').value, 10) / 100);
    if ((n - size) % 2) size++;
    var start = (n - size) / 2;
    return shape() === 'band'
      ? { band: true, r0: start, c0: 0, rows: size, cols: n }
      : { band: false, r0: start, c0: start, rows: size, cols: size };
  }

  function makeDark(qr, n) {
    var box = logoBox(n);
    return function (r, c) {
      if (box && r >= box.r0 && r < box.r0 + box.rows && c >= box.c0 && c < box.c0 + box.cols) return false;
      return qr.isDark(r, c);
    };
  }

  // Traka ide od ivice do ivice slike (preko tihe zone); kvadrat nema posebnu pozadinu.
  function bandRect(box, m) {
    return box.band ? { x: 0, y: box.r0 + m, w: box.cols + 2 * m, h: box.rows } : null;
  }

  // Na 100% se logo uklopi (contain) sa pola modula razmaka od koda; zoom ga skalira oko centra.
  function logoRect(box, m) {
    var maxW = box.cols - 1, maxH = box.rows - 1;
    var ratio = logo.w && logo.h ? logo.w / logo.h : 1;
    var zoom = parseInt($('logoZoom').value, 10) / 100;
    var w = Math.min(maxW, maxH * ratio) * zoom, h = w / ratio;
    return { x: box.c0 + m + (box.cols - w) / 2, y: box.r0 + m + (box.rows - h) / 2, w: w, h: h };
  }

  // Površina van koje se logo odseče: oblast bez modula, uvučena pola modula. Uvećan logo tako
  // ne dodiruje module i ne ulazi u tihu zonu — oba su obarala skeniranje pri velikom zoomu.
  function clipRect(box, m) {
    return { x: box.c0 + m + 0.5, y: box.r0 + m + 0.5, w: box.cols - 1, h: box.rows - 1 };
  }

  function buildSvg(qr, n, m, dark, light, transparent) {
    var size = n + 2 * m, d = '', isDark = makeDark(qr, n);
    for (var r = 0; r < n; r++) {
      var c = 0;
      while (c < n) {
        if (!isDark(r, c)) { c++; continue; }
        var start = c;
        while (c < n && isDark(r, c)) c++;
        d += 'M' + (start + m) + ' ' + (r + m) + 'h' + (c - start) + 'v1h-' + (c - start) + 'z';
      }
    }
    var extra = '';
    var box = logoBox(n);
    if (box) {
      var br = bandRect(box, m);
      if (br) extra += '<rect x="' + br.x + '" y="' + br.y + '" width="' + br.w + '" height="' + br.h + '" fill="' + $('bandColor').value + '"/>';
      var lr = logoRect(box, m), cr = clipRect(box, m);
      extra += '<clipPath id="logo-clip"><rect x="' + cr.x + '" y="' + cr.y + '" width="' + cr.w + '" height="' + cr.h + '"/></clipPath>' +
        '<image clip-path="url(#logo-clip)" x="' + lr.x + '" y="' + lr.y + '" width="' + lr.w + '" height="' + lr.h + '" ' +
        'preserveAspectRatio="xMidYMid meet" href="' + logo.url + '" xlink:href="' + logo.url + '"/>';
    }
    return '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 ' + size + ' ' + size + '" ' +
      'width="' + size * 10 + '" height="' + size * 10 + '">' +
      (transparent ? '' : '<rect width="' + size + '" height="' + size + '" fill="' + light + '"/>') +
      '<path fill="' + dark + '" shape-rendering="crispEdges" d="' + d + '"/>' + extra + '</svg>';
  }

  function pngPixels(n, m) {
    var target = parseInt($('pngSize').value, 10);
    return Math.max(1, Math.round(target / (n + 2 * m))) * (n + 2 * m);
  }

  // PNG uvek sa pozadinom — isti crtež služi i za preuzimanje i za proveru skeniranja.
  function drawCanvas(scale) {
    var n = current.n, m = margin(), px = scale * (n + 2 * m), isDark = makeDark(current.qr, n);
    var cv = document.createElement('canvas');
    cv.width = cv.height = px;
    var ctx = cv.getContext('2d');
    ctx.fillStyle = $('light').value;
    ctx.fillRect(0, 0, px, px);
    ctx.fillStyle = $('dark').value;
    for (var r = 0; r < n; r++)
      for (var c = 0; c < n; c++)
        if (isDark(r, c)) ctx.fillRect((c + m) * scale, (r + m) * scale, scale, scale);
    var box = logoBox(n);
    if (box) {
      var br = bandRect(box, m);
      if (br) {
        ctx.fillStyle = $('bandColor').value;
        ctx.fillRect(br.x * scale, br.y * scale, br.w * scale, br.h * scale);
      }
      var lr = logoRect(box, m), cr = clipRect(box, m);
      ctx.save();
      ctx.beginPath();
      ctx.rect(cr.x * scale, cr.y * scale, cr.w * scale, cr.h * scale);
      ctx.clip();
      ctx.imageSmoothingQuality = 'high';
      ctx.drawImage(logo.img, lr.x * scale, lr.y * scale, lr.w * scale, lr.h * scale);
      ctx.restore();
    }
    return cv;
  }

  /* ── Provera: da li se kod i dalje čita ───────────────────────────────── */

  var scanTimer = null;
  function scheduleScan() {
    clearTimeout(scanTimer);
    if (!current || typeof jsQR !== 'function') { $('scan').hidden = true; return; }
    setScan('wait', 'Proveravam da li se kod čita…');
    scanTimer = setTimeout(function () {
      var res = null;
      try {
        var cv = drawCanvas(6);
        var data = cv.getContext('2d').getImageData(0, 0, cv.width, cv.height);
        res = jsQR(data.data, cv.width, cv.height, { inversionAttempts: 'dontInvert' });
      } catch (e) { $('scan').hidden = true; return; }
      if (res && res.data === current.text) {
        setScan('ok', '✓ Provereno: kod se čita');
      } else {
        setScan('bad', '✕ Kod se ne čita' + (logo ? ' — smanji logo ili uključi korekciju H' : ' — proveri boje i tihu zonu'));
      }
    }, 200);
  }
  function setScan(cls, text) {
    $('scan').className = 'scan ' + cls;
    $('scan').textContent = text;
    $('scan').hidden = false;
  }

  /* ── Iscrtavanje ──────────────────────────────────────────────────────── */

  function render() {
    var isCampaign = mode() === 'campaign';
    $('campaignFields').hidden = !isCampaign;
    $('customFields').hidden = isCampaign;

    var p = payload(), warns = [];
    var dark = $('dark').value, light = $('light').value, transparent = $('transparent').checked;
    var m = margin();
    $('darkHex').textContent = dark;
    $('lightHex').textContent = light;
    var isBand = shape() === 'band';
    $('bandColorHex').textContent = $('bandColor').value;
    $('bandColorField').hidden = !isBand;
    $('logoSizeLabel').firstChild.nodeValue = (isBand ? 'Visina trake ' : 'Veličina logoa ');
    $('logoSizeOut').textContent = $('logoSize').value + '%';
    $('logoZoomOut').textContent = $('logoZoom').value + '%';
    $('logoHelp').textContent = isBand
      ? 'Traka seče kod po celoj širini, pa je kod gušći (najmanje verzija ' + BAND_MIN_VERSION + ') — štampaj ga bar 2,5 cm. Slika se ne šalje na server.'
      : 'Slika se obrađuje u browseru i ne šalje se na server. Uz logo koristi korekciju greške H.';

    $('urlOut').textContent = p.text || '—';
    $('urlOut').title = p.text;
    current = null;

    if (!p.text) {
      $('preview').innerHTML = '<span class="hint">Upiši link da se pojavi QR kod.</span>';
    } else {
      try {
        var qr = makeQr(p.text, $('ecc').value, logo && isBand ? BAND_MIN_VERSION : 0);
        var n = qr.getModuleCount();
        current = { qr: qr, n: n, name: p.name, text: p.text };
        $('preview').innerHTML = buildSvg(qr, n, m, dark, light, transparent);
        $('mVer').textContent = (n - 17) / 4;
        $('mMod').textContent = n + '×' + n;
        $('mPng').textContent = pngPixels(n, m) + ' px';
      } catch (e) {
        $('preview').innerHTML = '<span class="hint">Tekst je predugačak za QR kod.</span>';
      }
    }
    $('meta').hidden = !current;

    if (isCampaign && /^https?:\/\/(localhost|127\.|192\.168\.|10\.)/i.test(p.base || '')) {
      warns.push('Adresa je lokalna — telefon sa ovim kodom neće stići na sajt. Za štampu koristi javnu adresu.');
    } else if (isCampaign && !/^https:\/\//i.test(p.base || '')) {
      warns.push('Adresa trackera nije HTTPS.');
    }
    if (luminance(dark) >= luminance(light)) {
      warns.push('Kod je svetliji od pozadine — mnogi telefoni ovakav QR ne čitaju.');
    } else if ((luminance(light) + 0.05) / (luminance(dark) + 0.05) < 4) {
      warns.push('Slab kontrast između koda i pozadine — skeniranje može da ne radi.');
    }
    if (m < 4) warns.push('Tiha zona manja od 4 modula — neki čitači neće prepoznati kod.');
    if (logo && $('ecc').value !== 'H') warns.push('Uz logo koristi korekciju greške H — logo pokriva deo koda.');
    if (logo && isBand) {
      var bc = $('bandColor').value;
      if ((Math.max(luminance(bc), luminance(dark)) + 0.05) / (Math.min(luminance(bc), luminance(dark)) + 0.05) < 3) {
        warns.push('Boja trake je preblizu boji koda — ivica trake se stapa sa modulima.');
      }
    }

    $('warn').textContent = '';
    warns.forEach(function (w) { var d = document.createElement('div'); d.textContent = '⚠ ' + w; $('warn').appendChild(d); });
    $('warn').hidden = warns.length === 0;

    $('dlSvg').disabled = $('dlPng').disabled = $('copyBtn').disabled = !current;

    var showTest = isCampaign && CFG.token;
    $('testBox').hidden = !showTest;
    if (showTest) {
      $('testLink').href = 'q.php?c=' + encodeURIComponent(p.code) + '&nt=' + encodeURIComponent(CFG.token);
    }

    scheduleScan();
  }

  // Najmanja verzija koja staje, ali ne manja od minVersion (qrcode(0) bira sam).
  function makeQr(text, ecc, minVersion) {
    var qr = qrcode(0, ecc);
    qr.addData(text, 'Byte');
    qr.make();
    if (minVersion && (qr.getModuleCount() - 17) / 4 < minVersion) {
      qr = qrcode(minVersion, ecc);
      qr.addData(text, 'Byte');
      qr.make();
    }
    return qr;
  }

  // Svaki oblik pamti svoju veličinu; opseg klizača je izmeren proverom skeniranja.
  var SIZES = { band: { min: 8, max: 18, value: 14 }, square: { min: 12, max: 30, value: 22 } };
  var lastShape = shape();
  function onShapeChange() {
    SIZES[lastShape].value = $('logoSize').value;
    lastShape = shape();
    var s = SIZES[lastShape];
    $('logoSize').min = s.min;
    $('logoSize').max = s.max;
    $('logoSize').value = s.value;
    render();
  }

  /* ── Otpremanje logoa ─────────────────────────────────────────────────── */

  function logoError(msg) {
    $('logoErr').textContent = '';
    if (msg) { var d = document.createElement('div'); d.textContent = '⚠ ' + msg; $('logoErr').appendChild(d); }
    $('logoErr').hidden = !msg;
  }

  function setLogo(file) {
    logoError('');
    if (!file) return;
    if (!/^image\/(png|jpeg|webp|svg\+xml)$/.test(file.type)) { logoError('Podržani su PNG, JPG, WEBP i SVG.'); return; }
    if (file.size > 2 * 1024 * 1024) { logoError('Slika je veća od 2 MB.'); return; }

    var reader = new FileReader();
    reader.onload = function () {
      var url = String(reader.result);
      if (!DATA_URL.test(url)) { logoError('Sliku nije moguće pročitati.'); return; }
      var img = new Image();
      img.onload = function () {
        logo = { url: url, img: img, w: img.naturalWidth, h: img.naturalHeight, name: file.name };
        $('logoThumb').src = url;
        $('logoName').textContent = file.name;
        $('drop').hidden = true;
        $('logoInfo').hidden = $('logoOptions').hidden = false;
        $('ecc').value = 'H';                      // logo pokriva deo koda — treba maksimalna korekcija
        $('logoZoom').value = 100;                 // nov logo kreće od uklopljene veličine
        render();
      };
      img.onerror = function () { logoError('Sliku nije moguće učitati — probaj PNG.'); };
      img.src = url;
    };
    reader.readAsDataURL(file);
  }

  $('logoFile').addEventListener('change', function () { setLogo(this.files[0]); this.value = ''; });
  $('logoRemove').addEventListener('click', function () {
    logo = null;
    $('drop').hidden = false;
    $('logoInfo').hidden = $('logoOptions').hidden = true;
    logoError('');
    render();
  });
  ['dragenter', 'dragover'].forEach(function (ev) {
    $('drop').addEventListener(ev, function (e) { e.preventDefault(); $('drop').classList.add('over'); });
  });
  ['dragleave', 'drop'].forEach(function (ev) {
    $('drop').addEventListener(ev, function (e) { e.preventDefault(); $('drop').classList.remove('over'); });
  });
  $('drop').addEventListener('drop', function (e) { setLogo(e.dataTransfer.files[0]); });

  /* ── Preuzimanje ──────────────────────────────────────────────────────── */

  function download(blob, filename) {
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
  }

  $('dlSvg').addEventListener('click', function () {
    if (!current) return;
    var svg = buildSvg(current.qr, current.n, margin(), $('dark').value, $('light').value, $('transparent').checked);
    download(new Blob(['<?xml version="1.0" encoding="UTF-8"?>\n' + svg], { type: 'image/svg+xml' }), current.name + '.svg');
  });

  $('dlPng').addEventListener('click', function () {
    if (!current) return;
    var n = current.n, m = margin();
    drawCanvas(pngPixels(n, m) / (n + 2 * m)).toBlob(function (b) { download(b, current.name + '.png'); }, 'image/png');
  });

  $('copyBtn').addEventListener('click', function () {
    var btn = this;
    navigator.clipboard.writeText($('urlOut').textContent).then(function () {
      btn.textContent = 'Kopirano';
      setTimeout(function () { btn.textContent = 'Kopiraj'; }, 1500);
    });
  });

  function setZoom(v) {
    $('logoZoom').value = Math.max(40, Math.min(300, v));
    render();
  }
  $('zoomOut').addEventListener('click', function () { setZoom(parseInt($('logoZoom').value, 10) - 10); });
  $('zoomIn').addEventListener('click', function () { setZoom(parseInt($('logoZoom').value, 10) + 10); });
  $('zoomReset').addEventListener('click', function () { setZoom(100); });

  ['code', 'base', 'custom', 'ecc', 'dark', 'light', 'transparent', 'margin', 'pngSize', 'logoSize', 'logoZoom', 'bandColor'].forEach(function (id) {
    $(id).addEventListener('input', render);
    $(id).addEventListener('change', render);
  });
  document.querySelectorAll('input[name=mode]').forEach(function (el) { el.addEventListener('change', render); });
  document.querySelectorAll('input[name=shape]').forEach(function (el) { el.addEventListener('change', onShapeChange); });

  render();
})();
</script>
</body>

</html>
