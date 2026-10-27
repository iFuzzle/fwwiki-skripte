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

## Docker

Das Image enthaelt PHP 8.3 (gd, zip, curl), ImageMagick und die DejaVu-Schrift
sowie die drei Skripte. Der Standardbefehl fuehrt `gesamtkarte.sh` aus. Das
Image wird bei jedem Push auf `main` (und auf den Arbeitsbranch) per GitHub
Actions gebaut und nach `ghcr.io/ifuzzle/fwwiki-skripte` veroeffentlicht
(Tags: `latest` fuer main, Branchname, `vX.Y.Z` bei Git-Tags `v*`, Commit-SHA).

```
docker pull ghcr.io/ifuzzle/fwwiki-skripte:latest
mkdir -p map_cache ausgabe
docker run --rm \
  -v ./map_cache:/app/map_cache \
  -v ./ausgabe:/app/ausgabe \
  ghcr.io/ifuzzle/fwwiki-skripte:latest
```

* `map_cache/` (Feldbild-Cache) muss gemountet werden, sonst dauert jeder
  Lauf ca. 20 Min. Nur der erste Lauf mit leerem Cache ist so langsam.
* `ausgabe/` enthaelt danach `Gesamtkarte Kontinent (automatisch generiert).jpg`.
  Das Bild wird von Hand ins Wiki hochgeladen.
* Der Container laeuft als root; bei gemounteten Verzeichnissen gehoeren die
  Dateien daher root. `--user "$(id -u):$(id -g)"` geht nur, wenn die
  Verzeichnisse dem Nutzer gehoeren und `/app` beschreibbar ist (nicht
  getestet).
* Lokal bauen: `docker build -t fwwiki-skripte .`

Regelmaessig ausfuehren, z. B. per cron alle 3 Tage um 4 Uhr:

```
0 4 */3 * * cd /pfad/zu/karte && docker pull -q ghcr.io/ifuzzle/fwwiki-skripte:latest && docker run --rm -v ./map_cache:/app/map_cache -v ./ausgabe:/app/ausgabe ghcr.io/ifuzzle/fwwiki-skripte:latest
```

Hinweis: Neue Packages auf ghcr.io sind zunaechst privat. Fuer `docker pull`
ohne Anmeldung nach dem ersten erfolgreichen Build unter GitHub ->
Profil/Organisation -> Packages -> `fwwiki-skripte` -> Package settings ->
Change visibility auf "Public" stellen (oder mit `docker login ghcr.io` und
einem Token mit `read:packages` anmelden).
