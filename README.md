# fwwiki-skripte

Skripte zur Erzeugung der Gesamtkarte fuer das FreewarWiki. Quelle der
Wiki-Versionen:

* https://fwwiki.de/index.php/FreewarWiki:Bot/Skripts/maplist.php
* https://fwwiki.de/index.php/FreewarWiki:Bot/Skripts/makemap.php

## Ablauf

```
php maplist.php > maplist.txt     # liest alle Felder:/Karte:-Artikel (ca. 3 Min.)
php makemap.php --verbose \
    --output map.png              # Gesamtkarte, 50x50 px pro Feld
convert map.png -resize 30% 'Gesamtkarte Kontinent (automatisch generiert).jpg'
```

Die Feldbilder (ca. 2500) werden beim ersten Lauf nach `map_cache/` geladen
(ca. 18 Min.), danach dauert ein Lauf nur wenige Sekunden. `map_cache/` ist
nicht im Git. Voraussetzungen: PHP mit gd und curl, ImageMagick (`convert`).

Das fertige Bild liegt in `ausgabe/`. Es wird von Hand im Wiki als neue
Version der gleichen Datei hochgeladen.

## Aenderungen gegenueber der Wiki-Version von makemap.php

* Oberflaeche: nur Felder mit x/y > 10 und x < 170 (`GESAMTKARTE_LORU`).
  Das schliesst Seetangwald (Koordinaten 2..7), die Inseln mit eigenem
  Bild (Belpharia, Itolos, Narubia, ...) und alle Dungeons aus.
* Zustandsvarianten `(Eis)`, `(Feuer)`, `(brennend)` werden fuer die
  Gesamtkarte uebersprungen, sonst ueberdecken sie das Grundgebiet
  (gleiche Koordinaten). Der alte `(brennend)`-Filter griff nie, weil der
  Gebietsname vorher durch "Oberflaeche" ersetzt wurde.
* Rechte und untere Kante schliessen die unbetretbaren Rahmenfelder ein,
  die linke und obere Kante bleiben beim begehbaren Bereich. Damit
  entspricht das Bild `Vorlage:KartenMetaDaten`
  (TopLeft 35/69, BottomRight 155/144, 1845x1170 px bei 15 px/Feld), und
  Hotspots, Fadenkreuze und Inselversaetze im Wiki bleiben gueltig.
* Bilder werden per cURL ueber HTTPS geladen. Schlaegt ein Download fehl,
  bricht das Skript ab, statt eine leere Cache-Datei anzulegen.
