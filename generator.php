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
<script>
(function () {
  var CFG = <?= $js ?>;
  var $ = function (id) { return document.getElementById(id); };
  qrcode.stringToBytes = qrcode.stringToBytesFuncs['UTF-8'];

  var current = null; // { qr, n, name }

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

  function buildSvg(qr, n, m, dark, light, transparent) {
    var size = n + 2 * m, d = '';
    for (var r = 0; r < n; r++) {
      var c = 0;
      while (c < n) {
        if (!qr.isDark(r, c)) { c++; continue; }
        var start = c;
        while (c < n && qr.isDark(r, c)) c++;
        d += 'M' + (start + m) + ' ' + (r + m) + 'h' + (c - start) + 'v1h-' + (c - start) + 'z';
      }
    }
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + size + ' ' + size + '" ' +
      'width="' + size * 10 + '" height="' + size * 10 + '" shape-rendering="crispEdges">' +
      (transparent ? '' : '<rect width="' + size + '" height="' + size + '" fill="' + light + '"/>') +
      '<path fill="' + dark + '" d="' + d + '"/></svg>';
  }

  function pngPixels(n, m) {
    var target = parseInt($('pngSize').value, 10);
    return Math.max(1, Math.round(target / (n + 2 * m))) * (n + 2 * m);
  }

  function render() {
    var isCampaign = mode() === 'campaign';
    $('campaignFields').hidden = !isCampaign;
    $('customFields').hidden = isCampaign;

    var p = payload(), warns = [];
    var dark = $('dark').value, light = $('light').value, transparent = $('transparent').checked;
    var m = margin();
    $('darkHex').textContent = dark;
    $('lightHex').textContent = light;

    $('urlOut').textContent = p.text || '—';
    $('urlOut').title = p.text;
    current = null;

    if (!p.text) {
      $('preview').innerHTML = '<span class="hint">Upiši link da se pojavi QR kod.</span>';
    } else {
      try {
        var qr = qrcode(0, $('ecc').value);
        qr.addData(p.text, 'Byte');
        qr.make();
        var n = qr.getModuleCount();
        current = { qr: qr, n: n, name: p.name };
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

    $('warn').textContent = '';
    warns.forEach(function (w) { var d = document.createElement('div'); d.textContent = '⚠ ' + w; $('warn').appendChild(d); });
    $('warn').hidden = warns.length === 0;

    $('dlSvg').disabled = $('dlPng').disabled = $('copyBtn').disabled = !current;

    var showTest = isCampaign && CFG.token;
    $('testBox').hidden = !showTest;
    if (showTest) {
      $('testLink').href = 'q.php?c=' + encodeURIComponent(p.code) + '&nt=' + encodeURIComponent(CFG.token);
    }
  }

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
    var n = current.n, m = margin(), px = pngPixels(n, m), s = px / (n + 2 * m);
    var cv = document.createElement('canvas');
    cv.width = cv.height = px;
    var ctx = cv.getContext('2d');
    ctx.fillStyle = $('light').value;               // PNG uvek sa pozadinom
    ctx.fillRect(0, 0, px, px);
    ctx.fillStyle = $('dark').value;
    for (var r = 0; r < n; r++)
      for (var c = 0; c < n; c++)
        if (current.qr.isDark(r, c)) ctx.fillRect((c + m) * s, (r + m) * s, s, s);
    cv.toBlob(function (b) { download(b, current.name + '.png'); }, 'image/png');
  });

  $('copyBtn').addEventListener('click', function () {
    var btn = this;
    navigator.clipboard.writeText($('urlOut').textContent).then(function () {
      btn.textContent = 'Kopirano';
      setTimeout(function () { btn.textContent = 'Kopiraj'; }, 1500);
    });
  });

  ['code', 'base', 'custom', 'ecc', 'dark', 'light', 'transparent', 'margin', 'pngSize'].forEach(function (id) {
    $(id).addEventListener('input', render);
    $(id).addEventListener('change', render);
  });
  document.querySelectorAll('input[name=mode]').forEach(function (el) { el.addEventListener('change', render); });

  render();
})();
</script>
</body>
</html>
