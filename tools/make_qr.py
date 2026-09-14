#!/usr/bin/env python3
"""
Generisanje trackable QR koda za štampu.

    pip install segno
    python3 tools/make_qr.py card
    python3 tools/make_qr.py flyer --base https://go.webhubstudio.com

SVG ide u dizajn (vektor, oštar na svakoj veličini), PNG samo za brzu proveru.
Kod mora da postoji u QR_DESTINATIONS u src/config.php.
"""

import argparse
import pathlib
import sys

try:
    import segno
except ImportError:
    sys.exit("Nedostaje segno.  pip install segno")

BASE = "https://go.webhubstudio.com"
DARK = "#0F172A"          # near-black; čisto crno izgleda tvrdo na štampi

p = argparse.ArgumentParser()
p.add_argument("code", help="kod kampanje: card, flyer, van, yard")
p.add_argument("--base", default=BASE, help=f"osnovni URL trackera (podrazumevano {BASE})")
p.add_argument("--out", default="output", help="izlazni folder")
p.add_argument("--error", default="q", choices=["l", "m", "q", "h"],
               help="korekcija greške; 'h' obavezno ako ide logo u sredinu")
p.add_argument("--transparent", action="store_true", help="providna pozadina u SVG-u")
a = p.parse_args()

url = f"{a.base.rstrip('/')}/{a.code}"
out = pathlib.Path(a.out)
out.mkdir(parents=True, exist_ok=True)

qr = segno.make(url, error=a.error)
qr.save(out / f"qr-{a.code}.svg", kind="svg", dark=DARK,
        light=None if a.transparent else "#FFFFFF", border=4, scale=10)
qr.save(out / f"qr-{a.code}.png", kind="png", dark=DARK,
        light="#FFFFFF", border=4, scale=16)

print(f"URL:     {url}")
print(f"Verzija: {qr.version}, moduli: {qr.symbol_size(scale=1, border=0)}")
print(f"Fajlovi: {out}/qr-{a.code}.svg  i  {out}/qr-{a.code}.png")
print("\nSkeniraj SVOJIM telefonom pre slanja na štampu, pa proveri da se")
print("skeniranje pojavilo u dashboardu.")
