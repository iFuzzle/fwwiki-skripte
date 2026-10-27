<?php

// Ausgabeformat: Gebiet;Betretbar;X-Position;Y-Position;Slash/separierte/NPC-Liste;Bild-URL;Passage
// wobei Passage Komma-separierte X,Y Koordinaten sind bzw. ein Komma ohne Koordinaten zeigt eine Zufallspassage an (ohne festen Zielort)

header('Content-Type: text/plain; charset=utf-8;');
error_reporting(E_ALL ^ E_NOTICE);

define('TEMPLATE_GET_KEY', 1);
define('TEMPLATE_GET_VALUE', 2);
define('WIKIURL', 'https://fwwiki.de');
define("BERGFELD", 'http://welt1.freewar.de/freewar/images/map/std.jpg');

function is_obsolete($wiki_text)
{
    // Veraltetes Feld=none liefert true!
    return (bool)preg_match('/Veraltetes Feld=[^}]+/', $wiki_text);
}

// Zerlegt eine Seite so, dass jedes Resultat mit der angegebenen
// Vorlage beginnt (kann danach aber noch Müll enthalten)

function get_templates($template, $wiki_text)
{
    $pattern = '/\{\{(Vorlage:)?' . preg_quote($template, '/') . '/';
    $templates = preg_split($pattern, $wiki_text);
    return array_slice($templates, 1);
}

// Liest zu einem angegebenen Gebiet die Felder aus dem "Felder:XXX"-Artikel.
// Die unbetretbaren Felder um das Gebiet herum sind nicht mit dabei, dafür
// aber Details zu NPCs usw.

function parse_field_article($area)
{

    $fields = array();

    // Artikel fetchen
    $field_url = WIKIURL . "/index.php/Felder:" . rawurlencode($area) . "?action=raw";
    $html = file_get_contents($field_url);

    if (is_obsolete($html) === true) { // veraltet
        return array();
    } else {
        // Layout Vorlagen matchen
        $field_templates = get_templates('Feldzusammenfassung/Layout', $html);

        // Layout Vorlagen durchlaufen
        foreach ($field_templates as $field_template) {
            // init und parsen
            $field = array_merge(array('area' => $area), parse_field_template($field_template));

            // push
            $fields[] = $field;
        }

        return $fields;
    }
}

// Liest zu einem angegebenen Gebiet die unbetretbaren Felder aus dem
// "Karte:XXX"-Artikel.

function parse_map_article($area)
{
    // Artikel fetchen
    $field_url = WIKIURL . "/index.php/Karte:" . rawurlencode($area) . "?action=raw";
    echo "$field_url\n";
    $raw = file_get_contents($field_url);
    return array_map(function ($template_text) use ($area) {
        if (!preg_match("/^\|(-?\d+)\|(-?\d+)(\|([^}|]+))?/", $template_text, $values)) return [];
        return [
            'area' => $area,
            'accessible' => 0,
            'pos_x' => $values[1],
            'pos_y' => $values[2],
            'npc' => NULL,
            'url' => isset($values[4]) ? $values[4] : BERGFELD,
            'passages' => NULL
        ];
    }, array_merge(get_templates("Karte/Unbetretbar", $raw), get_templates("Karte/Rahmenfeld", $raw)));
}

// Verarbeitet Feld-Templates

function parse_field_template($wiki_text)
{

    // Standard-Werte
    $field = array(
        'accessible' => 1,
        'pos_x' => -10,
        'pos_y' => -9,
        'npc' => array(),
        'url' => '',
        'passages' => array()
    );

    // Vorlage als Array: Parameter => Wert
    $template = parse_template($wiki_text);

    // Vorlagewerte maschinenlesbar machen
    $field['pos_x'] = (int)$template['X'];
    $field['pos_y'] = (int)$template['Y'];
    $field['url'] = $template['Bild'];

    // Passagen lesen
    $passage_templates = get_templates('Feldzusammenfassung/Passage', $wiki_text);

    // Passagen durchlaufen
    foreach ($passage_templates as $passage_template) {
        $passage = parse_template($passage_template);

        // keine Koordinaten gesetzt
        if (strcasecmp($passage['Nach'], 'zufall') && !isset($passage['X'], $passage['Y'])) {
            /* Warnung deaktiviert - Es gibt einige Passagen in denen das beabsichtigt ist (Buran), solange nicht speziell gefiltert wird stört die Meldung nur
                trigger_error('Keine Koordinatenangabe in Passage von '.
                    $template['Name'] . ' nach ' . $passage['Nach'], E_USER_WARNING);
            */
        } else {
            $field['passages'][] = $passage['X'] . ',' . $passage['Y'];
        }
    }

    // npcs lesen
    preg_match_all('/\[\[([^]]+)]]/', $template['NPC'], $npc_matches, PREG_PATTERN_ORDER);
    $field['npc'] = $npc_matches[1];

    return $field;
}

function parse_template($text)
{
    $template = array();

    /* nicht kompatibel mit verschachtelten Vorlagen
    // Key-Value Paare spliten
    $lines = array_filter(explode('|', $template_text));

    foreach ($lines as $line) {
        // Key/Value trennen
        $keyval = explode('=', $line, 2);
        // und entsprechend ins Array eintragen
        $template[$keyval[0]] = trim($keyval[1]); // 'Parameter=' wirft undefined offset 1
    }//*/

    $key = '';
    $mode = TEMPLATE_GET_KEY;
    $depth = 0;

    for ($i = 1, $length = strlen($text); $i < $length; ++$i) {
        if ($text[$i] === '{' && $text[$i + 1] === '{') { // weitere Vorlage
            ++$depth;
            ++$i;
            $template[$key] .= '{';
        } else if ($text[$i] === '}' && $text[$i + 1] === '}') { // geschlossene Vorlage

            if ($depth === 0) {
                break;
            } else {
                --$depth;
                ++$i;
                $template[$key] .= '}';
            }
        } else if ($text[$i] === '[' && $text[$i + 1] === '[') { // geöffneter Link
            ++$depth;
            ++$i;
            $template[$key] .= '[';
        } else if ($text[$i] === ']' && $text[$i + 1] === ']') { // geschlossener Link

            if ($depth === 0) {
                break;
            } else {
                --$depth;
                ++$i;
                $template[$key] .= ']';
            }
        }

        if ($text[$i] === '=' && $depth === 0) { // Wertzuweisung beginnt
            $mode = TEMPLATE_GET_VALUE;
            $depth = 0;
            $template[$key] = '';
        } else if ($text[$i] === '|' && $depth === 0) { // Parameter Sparierung
            $mode = TEMPLATE_GET_KEY;
            $key = '';
        } else if ($mode === TEMPLATE_GET_KEY) {
            $key .= $text[$i];
        } else if ($mode === TEMPLATE_GET_VALUE) { // Wert wird geschrieben
            $template[$key] .= $text[$i];
        }
    }

    return array_map('trim', $template);
}

// Liste aller Gebiete aus "Katgorie:Felder" bestimmen

preg_match_all("/>Felder:([^<]+)</",
    file_get_contents(WIKIURL . '/index.php/Kategorie:Felder'), $areas);

// Für jedes Gebiet die Felder und die Randfelder ermitteln,
// und alles in ein großes Array werfen

$fields = array();
foreach ($areas[1] as $area) {
    $fields = array_merge($fields, parse_field_article($area));
    $fields = array_merge($fields, parse_map_article($area));
}

// Ausgabe

foreach ($fields as $field) {
    // output wie maplist.pl
    printf("%s;%d;%d;%d;%s;%s;%s\n",
        $field['area'],
        $field['accessible'],
        $field['pos_x'],
        $field['pos_y'],
        is_null($field['npc']) ? "" : implode('/', $field['npc']),
        $field['url'],
        is_null($field['passages']) ? "" : implode('/', $field['passages']));
}

