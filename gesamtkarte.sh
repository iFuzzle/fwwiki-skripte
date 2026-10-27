#!/bin/sh
# Erzeugt die Gesamtkarte fuer das FreewarWiki:
#   1. maplist.php liest alle Felder aus dem Wiki   -> maplist.txt
#   2. makemap.php setzt die Karte zusammen         -> map.png (50 px/Feld)
#   3. convert verkleinert auf 30 % (15 px/Feld)    -> ausgabe/*.jpg
# Aufruf:  sh gesamtkarte.sh [--ohne-maplist]
# (--ohne-maplist verwendet die vorhandene maplist.txt weiter)
set -e
cd "$(dirname "$0")"

NAME='Gesamtkarte Kontinent (automatisch generiert).jpg'

if [ "$1" != "--ohne-maplist" ]; then
    echo "Felder aus dem Wiki lesen (ca. 3 Min.) ..."
    php maplist.php > maplist.txt 2> maplist.log
fi

mkdir -p map_cache ausgabe
echo "Karte erzeugen (erster Lauf ca. 20 Min. wegen Bilddownload) ..."
php makemap.php --verbose --output map.png

convert map.png -resize 30% "ausgabe/$NAME"
rm -f map.png
echo "Fertig: ausgabe/$NAME"
