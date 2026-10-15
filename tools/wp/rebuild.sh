#!/bin/sh
# A téma telepítő-adatainak újragenerálása az előnézetből: adatok + blokk-tartalom → kanonikus markup a valódi
# blokkszerkesztőben (futó teszt-WordPress kell: tools/wp/dev-wp.sh). Két kör: a második már semmit nem módosíthat.
set -e
cd "$(dirname "$0")/../.."
python3 tools/wp/build_data.py
node tools/wp/canon.mjs
node tools/wp/canon.mjs
