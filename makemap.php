<?php
header('Content-Type: text/plain; charset=utf-8;');

const BERGFELD = 'https://welt1.freewar.de/freewar/images/map/std.jpg';
const ATLAS_TEMPLATE = 'atlas-vorlage.odt';
const GESAMTKARTE_LORU = array(2, 2, 270, 400); 
const MAPLIST = 'maplist.txt';
const MAPCACHE = './map_cache';

/* ----------------------------------------------------------------------------
 * Der Freewar-Kartengenerator
 * 
 * 
 */

function show_help() {
    echo <<<EOF

Befehlszeilenoptionen:

--mode           "gesamt": erzeugt eine Gesamtkarte für die Oberfläche
                 "einzel": erzeugt einzelne Karten für jedes Gebiet
                 "atlas": erzeugt ein Atlas-Dokument (OpenOffice/Libre-
                 Office-Format); benötigt dafür eine Vorlage
--dungeons       auch Dungeons mit ausgeben
--grid           alle X Zeilen/Spalten eine Linie
--cellspacing    Pixel Abstand zwischen Feldern
--gridlabels     Gitternetz mit Koordinaten beschriften
--rotatelabels   Koordinaten an X-Achse rotieren
--gebietlabel    Gebiete beschriften (bei Einzel/Atlas)
--labelfont      TTF-Datei für Beschriftungen
--lighten        nichtbetretbare/gebietsfremde Felder aufhellen
--output         Name der Ausgabedatei/des Ausgabeverzeichnisses
--bgcolor        Bildhintergrund in Hex
--verbose        mehr Meldungen anzeigen

Bei Modus "Atlas" zusätzlich
--dpi            Auflösung für die Bildausgabe (Default 140, größere Auflösung
                 bringt kleinere Bilder)
--pagewidth      druckbare Seitenbreite in cm
--pageheight     druckbare Seitenhöhe in cm


EOF;
}


// --- Einlesen der Befehlszeile ----------------------------------------------

$longopts = array(
    "mode:",           // gesamt (Default), einzel oder atlas
    "dungeons",        // sollen Dungeons mit ausgegeben werden?
    "verbose",         // soll das Programm geschwätzig sein?
    "lighten",         // sollen gebietsfremde Felde aufgehellt werden?
    "bgcolor:",        // Kartenhintergrund in Web-Notation (Default weiss)
    "grid:",           // Gitternetz-Abstand (Default keins)
    "help",            // Hilfe anzeigen
    "gridlabels",      // soll das Gitternetz beschriftet sein? (Default nein)
    "gebietlabel",     // soll die Gebietskarte ein Label haben? (Default nein)
    "labelfont:",      // TTF-Datei für die Beschriftungen
    "rotatelabels",    // soll X-Achesen-Label 90° rotiert sein? (Default nein)
    "cellspacing:",    // Feld-Abstand (Default 0)
    "dpi:",            // dpi für Bilder im Atlas (Default 140)
    "pagewidth:",      // druckbare Seitenbreite (cm, Papier-Rand) im Atlas
    "pageheight:",     // druckbare Seitenhöhe (cm, Papier-Rand) im Atlas
    "output:",         // Ausgabedatei oder -Verzeichnis
);

$o = getopt(NULL, $longopts, $optind);

$o['verbose'] = array_key_exists('verbose', $o);

if (array_key_exists('help', $o)) {
    show_help();
    exit;
}

if (!array_key_exists('mode', $o)) {
    $o['mode'] = 'gesamt';
    echo "Betriebsmodus \"Gesamtkarte\" automatisch gewählt. Programm mit --help\n";
    echo "aufrufen für weitere Funktionen\n";
}

if ($o['mode'] != 'gesamt' && $o['mode'] != 'atlas' && $o['mode'] != 'einzel') {
    echo "mode muss entweder 'atlas', 'einzel' oder 'gesamt' sein\n";
    show_help();
    exit;
}

if (!array_key_exists('bgcolor', $o)) {
    $o['bgcolor'] = 'ffffff';
}

if (!preg_match('/^[a-f0-9]{6}$/i', $o['bgcolor'])) {
    echo "bgcolor muss eine 6stellige Hexadezimalzahl sein, z.B. fafefa - nicht '".$o['bgcolor']."'\n";
    show_help();
    exit;
}

if (!array_key_exists('grid', $o)) {
    $o['grid'] = 0;
}

if (!preg_match('/^\d\d?$/', $o['grid'])) {
    echo "grid muss zwischen 0 und 99 liegen\n";
    show_help();
    exit;
}

$o['gridlabels'] = array_key_exists('gridlabels', $o);
if ($o['gridlabels'] && !$o['grid']) {
    echo "gridlabels kann nur zusammen mit grid verwendet werden\n";
    show_help();
    exit;
}

$o['rotatelabels'] = array_key_exists('rotatelabels', $o);
if ($o['rotatelabels'] && !$o['gridlabels']) {
    echo "rotatelabels kann nur zusammen mit gridlabels verwendet werden\n";
    show_help();
    exit;
}

$o['dungeons'] = array_key_exists('dungeons', $o);

$o['gebietlabel'] = array_key_exists('gebietlabel', $o);
if ($o['gebietlabel'] && $o['mode'] == 'gesamt') {
    echo "--gebietlabel geht nur bei --mode atlas oder --mode einzel\n";
    show_help();
    exit;
}

$o['lighten'] = array_key_exists('lighten', $o);
if ($o['lighten'] && $o['mode'] == 'gesamt') {
    echo "--lighten geht nur bei --mode atlas oder --mode einzel\n";
    show_help();
    exit;
}

if (!array_key_exists('cellspacing', $o)) $o['cellspacing'] = 0;
if (!preg_match('/^\d\d?$/', $o['cellspacing'])) {
    echo "cellspacing muss zwischen 0 und 99 liegen\n";
    show_help();
    exit;
}

if ($o['mode'] == 'atlas') {

    if (!array_key_exists('dpi', $o)) {
        $o['dpi'] = 140;
    }

    if (!preg_match('/^\d\d\d?$/', $o['dpi'])) {
        echo "dpi muss zwischen 10 und 999 liegen\n";
        show_help();
        exit;
    }

    if (!array_key_exists('pagewidth', $o)) $o['pagewidth'] = 12.85;
    $o['pagewidth'] = strtr($o['pagewidth'], ",", ".");

    if (!array_key_exists('pageheight', $o)) $o['pageheight'] = 19;
    $o['pageheight'] = strtr($o['pageheight'], ",", ".");

    if (!preg_match('/^\d\d?(\.\d+)?$/', $o['pageheight'])) {
        echo "pageheight muss zwischen 0 und 99.9 liegen\n";
        show_help();
        exit;
    }

    if (!preg_match('/^\d\d?(\.\d+)?$/', $o['pagewidth'])) {
        echo "pagewidth muss zwischen 0 und 99.9 liegen\n";
        show_help();
        exit;
    }

} else {
    if (array_key_exists('dpi', $o) ||
        array_key_exists('pageheight', $o) ||
        array_key_exists('pagewidth', $o)) {
        echo "--dpi, --pageheight, --pagewidth gehen nur mit --mode atlas\n";
        show_help();
        exit;
    }
}

if (!array_key_exists("labelfont", $o)) {
    $o['labelfont'] = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
}

if ($o['gebietlabel'] || $o['gridlabels']) {
    if (!file_exists($o['labelfont'])) {
        echo "Datei ".$o['labelfont']." (--labelfont) nicht gefunden\n";
        show_help();
        exit;
    }
}

if (!array_key_exists("output", $o)) {
    $o['output'] = './karten';
    if ($o['mode'] == 'atlas') $o['output'] = './atlas.odt';
    if ($o['mode'] == 'gesamt' && !$o['dungeons']) $o['output'] = './Gesamtkarte (automatisch generiert).jpg';
}

if ($optind < $argc) {
    echo "Ungültige Befehlszeilenoption\n";
    show_help();
    exit;
}

// --- Einlesen der Befehlszeile beendet --------------------------------------

if ($o['mode'] == 'atlas') {

    // Atlas benötigt eine vorbereitete OpenOffice-Datei
    if (!file_exists(ATLAS_TEMPLATE)) {
        echo "Datei ".ATLAS_TEMPLATE." wird für --mode atlas benötigt, fehlt aber.\n";
        echo "Entweder eine leere OpenOffice-Datei erzeugen, die irgendwo den Text\n";
        echo "ADD CONTENT HERE enthält, oder herunterladen von\n";
        echo "http://www.remote-island.org/101912/atlas-vorlage.odt";
        exit;
    }

    if ($o['verbose']) echo ATLAS_TEMPLATE.' nach '.$o['output']." kopieren...\n"; 
    copy(ATLAS_TEMPLATE, $o['output']);

    $zipfile = new ZipArchive;
    $res = $zipfile->open($o['output']);
    if (!$res) {
        echo "Fehler beim Öffnen der Datei ".$o['output']."!\n";
        exit;
    }
} else if ($o['mode'] != 'gesamt' || $o['dungeons']) {
    mkdir($o['output'], 0777, TRUE);
    if (!is_dir($o['output'])) {
        echo "'".$o['output']."' ist kein Verzeichnis bzw. kann nicht angelegt werden!\n";
        exit;
    }
}

$count = 10000;


// ab hier nichts ändern ohne Kenntnisse über Funktionsweise des Skripts

// cacht mapfile
function cache_mapfile($url) {
    $cache_file = MAPCACHE . "/". md5($url);
    if (!file_exists($cache_file) || filesize($cache_file) == 0) {
        $url = preg_replace('#^http://#', 'https://', $url);
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_FAILONERROR => true, CURLOPT_TIMEOUT => 30]);
        $data = curl_exec($ch);
        curl_close($ch);
        if ($data === false || $data === '') {
            echo "Fehler beim Laden von $url\n";
            exit(1);
        }
        file_put_contents($cache_file, $data);
    }
    
    return $cache_file;
}

// trennzeichen für positionsschlüssel
$pos_delimiter = '|';

// Bergfeld cachen
cache_mapfile(BERGFELD);

// maplist holen 
if ($o['verbose']) echo "Felder aus ".MAPLIST." holen und Bilder laden...\n";

$handle = fopen(MAPLIST, "r");
if (!$handle) {
    echo "Kann Feldliste aus ".MAPLIST." nicht laden\n";
    exit;
}
$field_rows = array();
while ($data = fgetcsv($handle, 1000, ";")) {
   array_push($field_rows, $data);
}
fclose($handle);

// felder in `position` => `url` format überführen
$fields = [];
foreach ($field_rows as $field) {
    $x = array_key_exists(2, $field) ? $field[2] : NULL;
    $y = array_key_exists(3, $field) ? $field[3] : NULL;
    $g = array_key_exists(0, $field) ? $field[0] : NULL;
    $b = array_key_exists(1, $field) ? $field[1] : NULL;

    if ($o["mode"] == "gesamt") {

        // Für die Gesamtkarte werden die Felder des Kontinents als ein Gebiet
        // behandelt
        if ($x>GESAMTKARTE_LORU[0] && $y>GESAMTKARTE_LORU[1] && 
            $x<GESAMTKARTE_LORU[2] && $y<GESAMTKARTE_LORU[3]) {
            $g="Oberfläche";
        } else {
            // Dungeons (und Außenbereiche des Kontinents wie Narubia)
            // nur, wenn --dungeons gesetzt ist
            if (!$o['dungeons']) continue;
        }

    } else {

        // Für Einzelkarten und Atlas kann man mit $dungeons steuern,
        // ob auch Dungeons ausgegeben werden sollen
        if (!$o['dungeons'] && $x<2) continue;
    }

    $lim = &$gebiet_limits[$g];
    if (!$lim) {
        $lim["minx"] = 99999;
        $lim["miny"] = 99999;
        $lim["maxx"] = -99999;
        $lim["maxy"] = -99999;
    }

    // Hier wird die Größe jedes Gebiets ermittelt; dazu berücksichtigen
    // wir aber nur die begehbaren Teile, sonst haben Karten wie z.B.
    // Düsterfrostinsel einen unnötig großen Platzbedarf. TODO, dies
    // konfigurierbar machen
    if ($b) {
        if ($x < $lim["minx"]) $lim["minx"] = $x;
        if ($y < $lim["miny"]) $lim["miny"] = $y;
        if ($x > $lim["maxx"]) $lim["maxx"] = $x;
        if ($y > $lim["maxy"]) $lim["maxy"] = $y;
    }
        
    // und gleich bilddatein holen
    $cache_file = cache_mapfile(array_key_exists(5, $field) ? $field[5] : NULL);
    
    // kein Feldtitel oder `Feldtitel Pensal (brennend)`
    if (!isset($g) || strpos($g, ' (brennend)') === false) {
        $fields["{$x}$pos_delimiter{$y}"] = [
            'x' => $x,
            'y' => $y,
            'g' => $g,
            'begehbar' => $b,
            'file' => $cache_file
        ];   
    }
    
}

// Randfelder einfügen
foreach ($fields as $field) {
    // Randfelder
    for ($diff_x = -1; $diff_x <= 1; ++$diff_x) {
        for ($diff_y = -1; $diff_y <= 1; ++$diff_y) {
            $edge = ($field['x'] + $diff_x) . $pos_delimiter . ($field['y'] + $diff_y);
            if (!isset($fields[$edge])) {
                $fields[$edge] = [
                    'x' => $field['x'] + $diff_x,
                    'y' => $field['y'] + $diff_y,
                    'file' => MAPCACHE . '/' . md5(BERGFELD)
                ];
            }
        }
    }
}

# Groesse eines Kartenfelds feststellen
list($tilewidth, $tileheight) = getimagesize($cache_file);
if ($o['verbose']) echo "tile size: $tilewidth x $tileheight\n";

// ein weisses Kartenfeld herstellen
if ($o['lighten']) {
    $aufheller = imagecreatetruecolor($tilewidth, $tileheight);
    $farbe = imagecolorallocate($aufheller, 255, 255, 255);
    imagefilledrectangle($aufheller, 0, 0, $tilewidth, $tileheight, $farbe);
}

if ($o["mode"] == 'atlas')
{
   file_put_contents("odt/content.xml", file_get_contents("head.xml"));
   $dungeons = "";
   $oberflaeche = "";
}

foreach($gebiet_limits as $gebiet => $limits) {

    // Bereich ermitteln

    $max_x_found = $limits['maxx'] + 1;
    $max_y_found = $limits['maxy'] + 1;
    $min_x_found = $limits['minx'] - 1;
    $min_y_found = $limits['miny'] - 1;

    // Leeres Kartenbild erstellen

    $mapwidth = ($max_x_found - $min_x_found + ($o['grid'] ? 3 : 1)) * $tilewidth + 
                ($max_x_found - $min_x_found + 2) * $o['cellspacing'];
    $mapheight = ($max_y_found - $min_y_found + ($o['grid'] ? 3 : 1)) * $tileheight + 
                 ($max_y_found - $min_y_found + 2) * $o['cellspacing'];

    if ($o['verbose']) {
        echo "Karte für '$gebiet': x_min: $min_x_found; x_max: $max_x_found; y_min: $min_y_found; y_max: $max_y_found; ";
        echo "Bildgröße: $mapwidth x $mapheight\n";
    }

    $mapimage = imagecreatetruecolor($mapwidth, $mapheight);
    if (!$mapimage) {
        echo "Fehler bei der Erstellung des Kartenbilds für '$gebiet'\n";
        continue;
    }

    // Hintergrundfarbe setzen

    $bgarray = sscanf($o['bgcolor'], "%02X%02X%02X");
    $bgindex = imagecolorallocate($mapimage, $bgarray[0], $bgarray[1], $bgarray[2]);
    imagefill($mapimage, 0, 0, $bgindex);

    // Gitternetz einzeichnen und ggf. beschriften

    if ($o['grid']) {
        for ($x = $min_x_found; $x <= $max_x_found; $x++) {
            if ($x%$o['grid'] == 0) {
                $mpx = ($x - $min_x_found + 1) * ($tilewidth + $o['cellspacing']) + $o['cellspacing'] + $tilewidth/2;
                imageline($mapimage, $mpx, $o['rotatelabels'] ? 0 : 40, $mpx, $o['rotatelabels'] ? $mapheight : $mapheight - 40, 0);
                if (!$o['gridlabels']) continue;
                $t = $x;
                if (strlen($t) > 4) $t=substr($t,0,2)."\n".substr($t,3);

                // X-Achsen-Labels können entweder gerade stehen oder
                // entlang der Achse (rotatelabels)
                if ($o['rotatelabels']) {
                    imagettftext($mapimage, 11, 270, $mpx + 3, 5, 0, $o['labelfont'], $t);
                    $ar = imagettfbbox (11, 270, $o['labelfont'], $t);
                    imagettftext($mapimage, 11, 270, $mpx + 3, $mapheight - $ar[3] - 5, 0, $o['labelfont'], $t);
                } else {
                    $ar = imagettfbbox (11, 0, $o['labelfont'], $t);
                    imagettftext($mapimage, 11, 0, $mpx + 3 - $ar[4]/2, 22 - $ar[5], 0, $o['labelfont'], $t);
                    imagettftext($mapimage, 11, 0, $mpx + 3 - $ar[4]/2, $mapheight + $ar[5] - 10, 0, $o['labelfont'], $t);
                }
            }
        }
        for ($y = $min_y_found; $y <= $max_y_found; $y++) {
            if ($y%$o['grid'] == 0) {
                $mpy = ($y - $min_y_found + 1) * ($tileheight + $o['cellspacing']) + $o['cellspacing'] + $tileheight/2;
                imageline($mapimage, 0, $mpy, $mapwidth, $mpy, 0);
                if (!$o['gridlabels']) continue;
                $t = $y;
                if (strlen($t) > 4) $t=substr($t,0,2)."\n".substr($t,3);
                imagettftext($mapimage, 11, 0, 5, $mpy - 2, 0, $o['labelfont'], $t);
                $ar = imagettfbbox (11, 0, $o['labelfont'], $t);
                imagettftext($mapimage, 11, 0, $mapwidth - 5 - $ar[4], $mpy - 2, 0, $o['labelfont'], $t);
            }
        }
    }

    // Label für Gebiet einzeichnen; schwarze Box, darin weisse Box, 
    // darin Text

    if ($o['gebietlabel']) {
        $ar = imagettfbbox (16, 0, $o['labelfont'], $gebiet);
        // FIXME die Zahlen hier sind etwas magisch durch Ausprobieren
        // gewählt
        imagefilledrectangle($mapimage, $mapwidth - $ar[4] - 55, 
            $mapheight + $ar[7] - 26, $mapwidth-51, $mapheight-21, 0);
        imagefilledrectangle($mapimage, $mapwidth - $ar[4] - 54, 
            $mapheight + $ar[7] - 25, $mapwidth-52, $mapheight-22, $bgindex);
        imagettftext($mapimage, 16, 0, $mapwidth - $ar[4] - 52, 
            $mapheight + $ar[7] - 7, 0, $o['labelfont'], $gebiet);
    }

    // Feldbilder an die richtige Stelle im Gebiet einzeichnen.
    // Diese Schleife geht alle Felder durch, die im Rechteck liegen, das
    // das Gebiet umgibt.
    foreach ($fields as $key => $data) {

        // weiter, wenn das Feld ausserhalb ist
        if ($data['x'] < $min_x_found) continue;
        if ($data['x'] > $max_x_found) continue;
        if ($data['y'] < $min_y_found) continue;
        if ($data['y'] > $max_y_found) continue;

        // Feld kopieren
        $offset = ($o['grid']) ? 1 : 0;
        imagecopy($mapimage, 
            imagecreatefromjpeg($data['file']), 
            ($data['x'] - $min_x_found + $offset) * 
                ($tilewidth + $o['cellspacing']) + $o['cellspacing'], 
            ($data['y'] - $min_y_found + $offset) * 
                ($tileheight + $o['cellspacing']) + $o['cellspacing'], 
            0, 0,
            $tilewidth, $tileheight);

        // Feld aufhellen, wenn es nicht zum Gebiet gehört
        if ($o['lighten'] && (!array_key_exists('begehbar', $data) || !$data['begehbar'] || $gebiet != $data['g'])) {
            imagecopymerge($mapimage,
                $aufheller,
                ($data['x'] - $min_x_found + $offset) * 
                    ($tilewidth + $o['cellspacing']) + $o['cellspacing'],
                ($data['y'] - $min_y_found + $offset) * 
                    ($tileheight + $o['cellspacing']) + $o['cellspacing'],
                0, 0,
                $tilewidth, $tileheight,
                50);
        }

    }

    // Ausgabe des Bildes für ein Gebiet. Im Gesamtkartenmodus gibt es nur 
    // ein Gebiet, also auch nur eine Ausgabe; in den anderen Modi passiert
    // das hier öfter.

    if ($o["mode"] == 'atlas') {

        // Für die Atlas-Ausgabe werden Bilder, die nicht auf die Seite
        // passen, eventuell gedreht oder zweigeteilt oder beides. Mehr
        // ist aber nicht drin - wenn das Bild auch gedreht und zweigeteilt
        // nicht passt, fliegt es raus.

        $px_per_cm = $o['dpi'] / 2.54;

        // Bildgröße in cm
        $w = $mapwidth / $px_per_cm;
        $h = $mapheight / $px_per_cm;

        // Bild-Pixel-Spalte, an der wir durchschneiden, wenn nötig
        // (wird gerundet auf ganze Felder, damit wir nicht mitten in 
        // einem Feld schneiden)
        $cutoff = floor($o['pagewidth'] * $px_per_cm / ($tilewidth + $o['cellspacing'])) 
           * ($tilewidth + $o['cellspacing']) - $o['cellspacing']/2 + 2;

        // Leeres Array initialisieren; eventuell haben wir mehr als
        // ein Ausgabebild
        $images = array();

        if ($w <= $o['pagewidth'] && $h <= $o['pageheight']) {

            // alles super, Bild passt auf Seite
            array_push($images, $mapimage);

        } elseif ($h <= $o['pagewidth'] && $w <= $o['pageheight']) {

            // Bild passt, wenn wir es drehen
            array_push($images, imagerotate($mapimage, 90, $bgindex));
            imagedestroy($mapimage);

        } elseif ($w <= 2* $o['pagewidth'] && $h <= $o['pageheight']) {

            // Bild passt, wenn wir es durchschneiden - also schneiden
            // und zwei Teile in Ausgabe-Array stecken
            // TODO: Formel 2*pagewidth nicht ganz korrekt, da cutoff
            // bedeutet, dass wir die Seite nicht 100% ausnutzen
            array_push($images, imagecrop($mapimage, 
                [ 'x' => 0, 'y' => 0, 'height' => $mapheight, 'width' => $cutoff ]));
            array_push($images, imagecrop($mapimage, 
                [ 'x' => $cutoff, 'y' => 0, 'height' => $mapheight, 
                    'width' => $mapwidth - $cutoff]));
            imagedestroy($mapimage);

        } elseif ($w <= $o['pageheight'] && $h <= 2 * $o['pagewidth']) {

            // Bild passt rotiert und geschnitten
            $m = imagerotate($mapimage, 90, $bgindex);
            imagedestroy($mapimage);
            $mapimage = $m;
            $temp = $w; $w = $h; $h = $temp;
            array_push($images, imagecrop($m, [ 'x' => 0, 'y' => 0, 
                'height' => $mapwidth, 'width' => $cutoff ]));
            array_push($images, imagecrop($m, [ 'x' => $cutoff, 'y' => 0, 
                'height' => $mapwidth, 'width' => $mapheight - $cutoff]));
            imagedestroy($m);

        } else {

             echo "Bild für '$gebiet' zu groß. Wähle höhere DPI oder größeres Papier\n";

        }

        // Wir führen zwei Ausgabedokumente, eins mit den oberirdischen
        // Gebieten und eins mit den Dungeons:
        if ($min_x_found < 0) {
            $xml = & $dungeons;
        } else {
            $xml = & $oberflaeche;
        }

        // OpenOffice-XML an das passende Dokument anfügen
        $xml .= '<text:h text:style-name="Heading_20_2" text:outline-level="2">'.
            $gebiet."</text:h>";
        foreach ($images as $image) {
            $count++;
            // TODO: Dieser Dateiname muss eigentlich eine Prüfsumme des Inhalts
            // sein. Ist er es nicht, meldet LibreOffice immer ein "defektes 
            // Dokument", bietet aber eine Reparatur an.
            ob_start();
            imagepng($image);
            $imagestring = ob_get_clean();
            $imagename = sprintf("Pictures/10000000%08X%08XDEADBEEF%08X.png", 
                imagesx($image), imagesy($image), $count);
            $zipfile->addFromString($imagename, $imagestring);
            $h = imagesy($image) / $px_per_cm;
            $w = imagesx($image) / $px_per_cm;
            $xml .= <<<EOF
          <text:p text:style-name="P1">
            <draw:frame draw:style-name="fr1" draw:name="Image$count" text:anchor-type="as-char" svg:width="${w}cm" svg:height="${h}cm" draw:z-index="0">
              <draw:image xlink:href="$imagename" xlink:type="simple" xlink:show="embed" xlink:actuate="onLoad" loext:mime-type="image/png"/>
            </draw:frame>
          </text:p>
EOF;
            imagedestroy($image);
        }

    } elseif ($o['mode'] == 'einzel' || ($o['mode'] == 'gesamt' && $o['dungeons'])) {

        // Bei Einzelbildausgabe einfach PNG schreiben
        imagepng($mapimage, $o['output']."/".$gebiet.".png");
        imagedestroy($mapimage);

    } else {

        // Bei Gesamtbild traditionell JPG
        // TODO was wenn ein .png als Ausgabename gewählt wurde?
        imagejpeg($mapimage, $o['output']);
        imagedestroy($mapimage);

    }
}
if ($o["mode"] == 'atlas')
{
    $old_contents = $zipfile->getFromName('content.xml');
    if (!$old_contents) {
        echo ATLAS_TEMPLATE." enthält keine Datei content.xml - kein .odt-Dokument?\n";
    }
    if (!preg_match('/(.*)<text:p[^<]*<text:span[^<]*ADD_CONTENT_HERE[^<]*<\/text:span>[^<]*<\/text:p>(.*)/', $old_contents, $matches)) {
        if (!preg_match('/(.*)<text:p[^<]*ADD CONTENT HERE[^<]*<\/text:p>(.*)/', $old_contents, $matches)) {
            echo "Die contents.xml im Atlas-Template entspricht nicht der erwarteten Form.\n";
            echo $old_contents;
            exit;
        }
    }
    $contents = $matches[1] . $oberflaeche . $dungeons . $matches[2];
    $zipfile->addFromString('content.xml', $contents);
    $zipfile->close();
    echo "Atlas erstellt. Die fertige Datei muss nun im OpenOffice/LibreOffice\n";
    echo "geöffnet und \"repariert\" werden.\n";
}

