# fwwiki-skripte

Skripte zur Erzeugung der Gesamtkarte fuer das FreewarWiki. Quelle der
Wiki-Versionen:

* https://fwwiki.de/index.php/FreewarWiki:Bot/Skripts/maplist.php
* https://fwwiki.de/index.php/FreewarWiki:Bot/Skripts/makemap.php

## Ablauf

```
sh gesamtkarte.sh
```

Das Skript fuehrt nacheinander aus:

1. `php maplist.php > maplist.txt` liest alle `Felder:`- und `Karte:`-Artikel
   aus dem Wiki (ca. 3 Min.).
2. `php makemap.php --output map.png` setzt die Gesamtkarte zusammen
   (50x50 px pro Feld).
3. `convert map.png -resize 30%` erzeugt das Wiki-Bild
   `ausgabe/Gesamtkarte Kontinent (automatisch generiert).jpg`
   (15 px pro Feld, 1845x1170).

Die Feldbilder (ca. 2500) werden beim ersten Lauf nach `map_cache/` geladen
(ca. 18 Min.), danach dauert ein Lauf nur wenige Minuten. `map_cache/`,
`maplist.txt` und `map.png` sind nicht im Git. Voraussetzungen: PHP mit
gd, curl und zip, ImageMagick (`convert`); Zugriff auf fwwiki.de und
`*.freewar.de` (HTTPS).

Das fertige Bild wird von Hand im Wiki als neue Version der gleichen
Datei hochgeladen; danach Seiten-Caches leeren.

## Elufia und Pensal

Gebiete mit wechselndem Zustand liegen auf denselben Koordinaten. Die
Gesamtkarte verwendet immer den Grundzustand: Elufia in der Moos-Phase
(`Elufia (Eis)`, `Elufia (Feuer)` werden uebersprungen), Pensal ohne Brand.
Der Zustandswechsel von Elufia (Moos -> Eis -> Feuer -> Moos, alle 40-59
Tage, weltenuebergreifend) beeinflusst die Karte damit nicht.

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
