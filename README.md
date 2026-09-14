# QR tracker — Web Hub Studio

Broji skeniranja sa štampanog materijala i preusmerava posetioca na sajt.

```
QR na kartici
  → https://go.webhubstudio.com/card
      → q.php zabeleži skeniranje (~2 ms)
      → 302 na https://webhubstudio.com/?utm_source=qr&utm_medium=print&utm_campaign=card
```

Posetilac vidi samo da se sajt otvorio.

---

## Pristupni podaci

| | |
|---|---|
| Dashboard | `https://go.webhubstudio.com/dashboard.php` |
| Korisnik | `radojko` |
| Lozinka | `orbit-lumen-8232` — **promeni je**, vidi dole |
| Token za probe | `dfxt_jE7n1-P` — `?nt=` parametar koji ne beleži posetu |

`QR_SALT` u `src/config.php` je već generisan. **Sačuvaj ga u password
menadžeru i nikada ga ne menjaj** — promena znači da brojanje različitih
posetilaca kreće od nule i stari podaci više nisu uporedivi.

Promena lozinke dashboarda:

```bash
php -r "echo password_hash('nova-lozinka', PASSWORD_DEFAULT), PHP_EOL;"
# rezultat zalepi u QR_DASHBOARD_PASS_HASH u src/config.php
# heš počinje sa $2y$ — kopiraj ga ručno, ne kroz sed/preg_replace
```

---

## Kampanje

Jedan kod po štampanom materijalu — jedini način da se kasnije odgovori na
pitanje „da li kartice ili flajeri bolje rade".

| Kod | URL | Materijal |
|---|---|---|
| `card` | `go.webhubstudio.com/card` | vizit karta |
| `flyer` | `go.webhubstudio.com/flyer` | flajer |
| `van` | `go.webhubstudio.com/van` | nalepnica na vozilu |
| `yard` | `go.webhubstudio.com/yard` | yard sign / baner |

Nov kod: dodaj red u `QR_DESTINATIONS` u `src/config.php`, pa generiši QR.
Nepoznat kod ne pravi grešku — preusmeri se na `QR_DEFAULT_CODE`.

## Generisanje QR koda

### Iz browsera — `generator.php`

`https://go.webhubstudio.com/generator.php` (ista prijava kao dashboard, link
je i u zaglavlju dashboarda). Izabereš kampanju, boje, korekciju greške i tihu
zonu, pa preuzmeš SVG (za štampu) ili PNG. Adresa u QR-u je
`QR_PUBLIC_BASE` + `/kod` — `QR_PUBLIC_BASE` je u `src/config.php`.

„Proizvoljan link" pravi običan QR koji **ne ide kroz tracker** i ne vidi se u
statistici. Nova praćena kampanja se i dalje dodaje u `QR_DESTINATIONS`.

QR se crta u browseru bibliotekom `assets/qrcode.js` (qrcode-generator 1.4.4,
Kazuhiko Arase, MIT) — lokalna kopija, bez CDN-a. Folder `assets/` mora da ode
na server zajedno sa ostalim.

Lokalno testiranje: ako polje „Adresa trackera" promeniš na `localhost`,
generator upozori — takav kod telefon ne može da otvori.

### Iz komandne linije

```bash
pip install segno
python3 tools/make_qr.py card
```

Daje `output/qr-card.svg` (za štampu) i `output/qr-card.png` (za proveru).
Minimalna veličina na štampi: **2 cm**. Bela tiha zona oko koda se ne seče.

**Skeniraj svojim telefonom pre slanja na Vista Print**, pa proveri da se
skeniranje pojavilo u dashboardu.

---

## Deploy na cPanel

1. **MySQL baza** — cPanel → MySQL Databases: baza `qrtracker`, korisnik
   `qruser`, ALL PRIVILEGES. Zapiši puna imena (`korisnik_qrtracker`).
   Tabele se prave same pri prvom zahtevu — ne uvozi se `.sql`.
2. **Poddomen** — cPanel → Domains → *Create A New Domain*:
   `go.webhubstudio.com`, document root **van** `public_html`:
   `/home/KORISNIK/qrtracker`. Odčekiraj „Share document root".
   Tako Apache za poddomen čita samo `.htaccess` iz tog foldera — `.htaccess`
   glavnog sajta se ne primenjuje i SPA rutiranje ostaje netaknuto.
3. **Upload** — sadržaj foldera `qr/` u `/home/KORISNIK/qrtracker/`
   (dakle `q.php` ide u koren tog foldera, ne u podfolder `qr/`).
   U File Manageru uključi *Settings → Show Hidden Files* i proveri da su sva
   tri `.htaccess` fajla stigla (u korenu, u `src/`, u `data/`). Ovo je
   najčešći razlog zašto lepi URL ne radi.
4. **config.php** — prebaci na MySQL:
   ```php
   define('QR_DB_DRIVER', 'mysql');
   define('QR_MYSQL_NAME', 'korisnik_qrtracker');
   define('QR_MYSQL_USER', 'korisnik_qruser');
   define('QR_MYSQL_PASS', 'lozinka-iz-koraka-1');
   ```
   `QR_SALT` i heš lozinke ostaju kakvi jesu.
5. **PHP 8.1+** — cPanel → MultiPHP Manager. Kod koristi `match` i
   `str_contains`, na 7.x puca.
6. **HTTPS** — SSL/TLS Status → Run AutoSSL. Basic Auth bez HTTPS-a šalje
   lozinku u čistom tekstu.
7. **Cron za brisanje** — nedeljno:
   ```
   0 3 * * 0 /usr/local/bin/php /home/KORISNIK/qrtracker/tools/purge.php >/dev/null 2>&1
   ```

### Provera posle deploya

| Adresa | Očekivano |
|---|---|
| `go.webhubstudio.com/card` | 302 na sajt sa `?utm_source=qr` |
| `go.webhubstudio.com/q.php?c=card` | isto |
| `go.webhubstudio.com/dashboard.php` | traži korisničko ime i lozinku |
| `go.webhubstudio.com/src/config.php` | **403** |
| `go.webhubstudio.com/data/qr.sqlite` | **403** |

Ako `src/config.php` prikaže sadržaj — lozinka baze je javna. Stani i
popravi `.htaccess` u `src/` pre svega ostalog.

Ako `/card` vraća 404 a `q.php?c=card` radi, `mod_rewrite` ne hvata:
proveri da `.htaccess` postoji u korenu poddomena i da je `RewriteBase /`.

### Zašto poddomen a ne folder na glavnom domenu

`webhubstudio.com` je React/Vite SPA sa catch-all pravilom — **svaka**
nepostojeća putanja vraća 200 i prikaže početnu stranicu, uključujući
`/qr/card` i `/nepostojeca-stranica-12345`. Folder `/qr/` na glavnom domenu bi
značio diranje `.htaccess` koji drži rutiranje živog sajta. Poddomen sa svojim
document rootom nema taj problem: Apache za njega čita samo `.htaccess` iz tog
foldera.

---

## Čišćenje i reset podataka

Sve je u dashboardu, na dnu, u panelu **Čišćenje podataka**.

### Isključi, ne briši

`Isključi` ostavlja zapis u bazi ali ga vadi iz svih brojeva — i može da se
vrati jednim klikom. To je podrazumevana akcija, jer je pogrešan klik na
brisanje nepovratan. Trajno brisanje postoji odvojeno, za kad si siguran.

| Akcija | Šta radi | Povratno |
|---|---|---|
| `isključi` / `vrati` pored reda | jedno skeniranje | da |
| Isključi / Vrati dan | sva skeniranja tog datuma (UTC) | da |
| Isključi / Vrati kampanju | sva skeniranja jednog koda | da |
| Obriši isključene trajno | briše sve što je isključeno | **ne** |
| Obriši dan trajno | briše datum i njegov red u zbiru | **ne** |
| Pun reset | briše sve, uključujući istorijski zbir | **ne** |

Pun reset traži da se u polje upiše `RESET` — potvrda u browseru nije dovoljna.

Posle svake izmene `qr_daily` se preračunava iz `qr_scans`. Bez toga bi
„ukupno ikada" ostalo naduvano, pošto se zbir puni pri upisu i ne zna ništa o
kasnijim isključivanjima.

### Sopstvene probe — bolje nego brisati posle

```
https://go.webhubstudio.com/q.php?c=card&nt=dfxt_jE7n1-P
```

Sa tačnim tokenom (`QR_NOTRACK_TOKEN` u `src/config.php`) tracker preusmeri ali
**ne upiše ništa**. Za proveru da odredište radi koristi ovo umesto skeniranja —
nema šta posle da se čisti.

Token ne pomaže kad skeniraš pravi QR sa kartice telefonom, jer QR vodi na
`/card` bez parametra. Takve probe se čiste dugmetom `isključi`.

### Zašto ne postoji trajno „ignoriši moj telefon"

Zato što bi za to trebao kolačić ili trajni identifikator uređaja — tačno ono
što ovaj tracker namerno nema, i razlog zašto ne traži cookie baner. Heš
posetioca se menja svaka 24 časa, pa „moj telefon" sutra ne liči na sebe od
juče. Zamena za to su token iznad i dugme `isključi`.

### CSRF

Admin akcije idu kroz POST sa tokenom izvedenim iz `QR_SALT`. Bez toga bi tuđa
stranica mogla da pošalje POST na dashboard dok ti je Basic Auth još keširan u
browseru. Token se menja svakog dana — ako ostaviš tab otvoren preko noći,
osveži stranicu pre nego što klikneš.

---

## Šta se prati, a šta ne

Prati se: broj skeniranja, vreme, kod kampanje, tip uređaja, OS, pregledač,
jezik, domen uputnog sajta, i država (samo iza Cloudflare-a).

**Ne prati se:** IP adresa se nigde ne upisuje — postoji samo u memoriji dok
traje zahtev. Nema kolačića, nema `localStorage`, nema fingerprintinga, nema
GeoIP po gradovima. Zato ovaj tracker ne traži cookie baner.

Posetioci se razlikuju preko `sha256(so + datum + IP + User-Agent)` gde se so
menja svaka 24 časa. Posledica: „različiti ljudi" znači **različiti ljudi po
danu** — isti čovek u utorak i sredu broji se kao dva. To je namerna zamena za
mogućnost praćenja pojedinca kroz vreme.

Botovi (`facebookexternalhit`, `WhatsApp`, `Slackbot`, crawleri) dobijaju
preusmeravanje ali se ne beleže — bez toga bi jedno deljenje linka u WhatsApp
grupi ubacilo lažno skeniranje.

Pojedinačni zapisi se brišu posle `QR_RETENTION_DAYS` (365). Dnevni zbir
(`qr_daily`) ostaje, pa poređenje „mart ove naspram marta prošle godine" i
dalje radi.

---

## Lokalni razvoj

```bash
php -S localhost:8080 -t .
curl -s -o /dev/null -w "%{http_code} %{redirect_url}\n" \
  -A "Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) Safari/604.1" \
  "http://localhost:8080/q.php?c=card"
```

Lepi URL `/card` **ne radi** na PHP ugrađenom serveru — nema `mod_rewrite`.
Lokalno se testira preko `q.php?c=card`; to nije greška.

Pre prelaska na produkciju obriši `data/qr.sqlite` da test podaci ne uđu u
statistiku.

---

## Za klijente (reseller)

Isti folder se kopira na klijentski nalog kao **zasebna instalacija sa svojom
bazom i svojom soli**. Ne deli se jedna baza među klijentima — podaci jednog
klijenta ne smeju da završe u dashboardu drugog.

Menja se: `QR_DESTINATIONS`, `QR_DASHBOARD_USER`, lozinka, `QR_SALT`, i
`QR_DISPLAY_TZ` ako je klijent van Ontarija. U `.htaccess` `RewriteBase` ako
instalacija nije u korenu poddomena.
