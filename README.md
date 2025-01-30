# Media Manager Types verwalten

Mit der Klasse `rex_media_manager_type` lassen sich Media Manager Typen und deren Effekte programmatisch verwalten. Die Klasse folgt dem Muster von `rex_sql_table` und ermöglicht es, Medientypen bei der AddOn-Installation anzulegen und zu aktualisieren.

## Parameter für die Effekte ermitteln

Um die korrekten Parameter für einen Effekt zu finden, gibt es mehrere Möglichkeiten:

### 1. Parameter eines existierenden Media Types nachschlagen

Ein neuer Media Typ kann im Backend angelegt, die Effekte dort konfiguriert und anschließend exportiert werden:

```php
// Hole Parameter von einem existierenden Typ
$json = rex_media_manager_type::exportToJson(['mein_typ']);
dump(json_decode($json, true));
```

### 2. Parameter eines bestimmten Effekts anzeigen

Alle Parameter eines Effekts können so eingesehen werden:

```php
// Parameter des resize-Effekts anzeigen
$params = rex_media_manager_type::getEffectParameters('resize');
dump($params);

// Ausgabe:
array(3) {
  ["name"]=> "resize"
  ["class"]=> "rex_effect_resize"
  ["params"]=> array(4) {
    ["width"]=> array(
      ["type"]=> "int"
      ["default"]=> ""
    )
    ["height"]=> array(
      ["type"]=> "int"
      ["default"]=> ""
    )
    ["style"]=> array(
      ["type"]=> "select"
      ["options"]=> array(3) {
        [0]=> "maximum"
        [1]=> "minimum"
        [2]=> "exact"
      }
      ["default"]=> "fit"
    )
    ["allow_enlarge"]=> array(
      ["type"]=> "select"
      ["options"]=> array(2) {
        [0]=> "enlarge"
        [1]=> "not_enlarge"
      }
      ["default"]=> "enlarge"
    )
  }
}
```

### 3. Liste aller verfügbaren Effekte

Eine Übersicht aller verfügbaren Effekte erhält man so:

```php
$effects = rex_media_manager_type::getAvailableEffects();
dump($effects);

// Ausgabe:
array(15) {
  ["resize"]=> object(rex_effect_resize)
  ["crop"]=> object(rex_effect_crop)
  ["workspace"]=> object(rex_effect_workspace)
  // ... etc
}
```

### 4. Parameter im Backend nachschlagen

Die Parameter lassen sich auch direkt im REDAXO-Backend ermitteln:

1. Neuen Media Manager Typ anlegen
2. Gewünschten Effekt hinzufügen
3. Effekt konfigurieren
4. `rex_media_manager_type::exportToJson()` aufrufen um die Konfiguration zu erhalten

## Einfache Beispiele

### Neuer Media Manager Typ

```php
rex_media_manager_type::get('mein_typ')
    ->setDescription('Bildtyp für die Teamansicht')
    ->ensureEffectByName('resize', [
        'width' => 800,
        'height' => 600
    ])
    ->ensure();
```

### Mehrere Effekte hinzufügen

```php
rex_media_manager_type::get('mein_typ')
    ->ensureEffectByName('resize', [
        'width' => 800,
        'height' => 600
    ])
    ->ensureEffectByName('workspace', [
        'height' => 600,
        'set_transparent' => 'colored'
    ])
    ->ensure();
```

## Effekte positionieren

### Effekt am Ende hinzufügen (append)

```php
// Einzelnen Typ bearbeiten
rex_media_manager_type::get('mein_typ')
    ->ensureEffectByName('resize', [
        'width' => 800,
        'height' => 600
    ], 'append') // oder position parameter weglassen, da 'append' default ist
    ->ensure();

// Mehreren Typen einen Effekt am Ende hinzufügen
rex_media_manager_type::ensureEffectToTypes('gallery_*', 'resize', [
    'width' => 800,
    'height' => 600
], 'append');
```

### Effekt am Anfang hinzufügen (prepend)

```php
// Wasserzeichen als ersten Effekt einfügen
rex_media_manager_type::get('mein_typ')
    ->ensureEffectByName('insert_image', [
        'brandimage' => 'logo.png',
        'vpos' => 'bottom',
        'hpos' => 'right'
    ], 'prepend')
    ->ensure();

// Bildoptimierung bei allen Typen als ersten Effekt einfügen
rex_media_manager_type::ensureEffectToTypes('opt_*', 'image_properties', [
    'jpg_quality' => 85,
    'webp_quality' => 90
], 'prepend');
```

### Praktisches Beispiel: Reihenfolge der Effekte

Die Reihenfolge der Effekte ist oft wichtig. Hier ein Beispiel für eine sinnvolle Anordnung:

```php
rex_media_manager_type::get('content_image')
    // 1. Erst das Bild in die richtige Größe bringen
    ->ensureEffectByName('resize', [
        'width' => 1200,
        'height' => 800,
        'style' => 'maximum'
    ])
    // 2. Dann einen einheitlichen Arbeitsbereich schaffen
    ->ensureEffectByName('workspace', [
        'width' => 1200,
        'height' => 800,
        'set_transparent' => 'colored'
    ])
    // 3. Als letztes das Wasserzeichen, damit es auf dem fertigen Bild sitzt
    ->ensureEffectByName('insert_image', [
        'brandimage' => 'logo.png',
        'vpos' => 'bottom',
        'hpos' => 'right'
    ])
    ->ensure();
```

## Effekte und Typen löschen

### Effekte eines Types löschen

```php
// Einzelnen Effekt über die Priorität löschen
rex_media_manager_type::get('mein_typ')
    ->removeEffect(1)  // Effekt mit Priorität 1 entfernen
    ->ensure();

// Praxisbeispiel: Bestimmten Effekt bei allen Typen mit Prefix entfernen
$types = rex_sql::factory()->getArray('
    SELECT t.name, e.priority, e.effect
    FROM ' . rex::getTable('media_manager_type') . ' t
    LEFT JOIN ' . rex::getTable('media_manager_type_effect') . ' e
        ON e.type_id = t.id
    WHERE t.name LIKE :pattern',
    ['pattern' => 'gallery_%']
);

foreach ($types as $type) {
    if ($type['effect'] === 'insert_image') {
        rex_media_manager_type::get($type['name'])
            ->removeEffect($type['priority'])
            ->ensure();
    }
}
```

### Media Manager Typen löschen

```php
// Kompletten Typ mit allen Effekten löschen
rex_media_manager_type::get('mein_typ')->drop();

// In einer uninstall.php - Typen mit Prefix löschen
$types = rex_sql::factory()->getArray('
    SELECT name 
    FROM '.rex::getTable('media_manager_type').'
    WHERE name LIKE :pattern',
    ['pattern' => 'my_prefix_%']
);

foreach ($types as $type) {
    rex_media_manager_type::get($type['name'])->drop();
}
```

### Praxisbeispiel: Typ aktualisieren

```php
// Bestehenden Typ aktualisieren
$type = rex_media_manager_type::get('article_image');

if ($type->exists()) {
    // Vorhandene Effekte werden durch drop() entfernt
    $type->drop();
}

// Typ neu anlegen
$type
    ->setDescription('Bild für Artikel')
    // Bildoptimierung als erstes
    ->ensureEffectByName('image_properties', [
        'jpg_quality' => 85,
        'webp_quality' => 90
    ], 'prepend')
    // Dann die Größenanpassung
    ->ensureEffectByName('resize', [
        'width' => 800,
        'height' => 600
    ])
    // Zum Schluss das Wasserzeichen
    ->ensureEffectByName('insert_image', [
        'brandimage' => 'watermark.png'
    ], 'append')
    ->ensure();
```

## Komplexe Praxisbeispiele

### Beispiel: Bildtypen für eine Teamseite

```php
// install.php des AddOns

// Profilbild in voller Größe
rex_media_manager_type::get('team_full')
    ->setDescription('Team-Profilbild in voller Größe')
    ->ensureEffectByName('resize', [
        'width' => 800,
        'height' => 800,
        'style' => 'maximum'
    ])
    ->ensure();

// Profilbild als Vorschau
rex_media_manager_type::get('team_preview')
    ->setDescription('Team-Profilbild als Vorschau')
    ->ensureEffectByName('resize', [
        'width' => 400,
        'height' => 400,
        'style' => 'maximum'
    ])
    ->ensure();

// Profilbild als Thumbnail
rex_media_manager_type::get('team_thumb')
    ->setDescription('Team-Profilbild als Thumbnail')
    ->ensureEffectByName('workspace', [
        'width' => 150,
        'height' => 150
    ])
    ->ensureEffectByName('crop', [
        'width' => 150,
        'height' => 150
    ])
    ->ensure();
```

### Beispiel: Bildtypen für eine Galerie

```php
// Wasserzeichen allen Galerie-Typen hinzufügen
rex_media_manager_type::ensureEffectToTypes('gallery_*', 'insert_image', [
    'brandimage' => 'logo.png',
    'vpos' => 'bottom',
    'hpos' => 'right'
]);

// Galeriebild in verschiedenen Größen
$sizes = ['small' => 400, 'medium' => 800, 'large' => 1200];

foreach ($sizes as $name => $width) {
    rex_media_manager_type::get('gallery_'.$name)
        ->setDescription('Galeriebild '.$width.'px')
        ->ensureEffectByName('resize', [
            'width' => $width,
            'height' => round($width/16*9), // 16:9 Format
            'style' => 'maximum'
        ])
        ->ensure();
}
```

### Beispiel: Effekte für optimierte Bildausgabe

```php
// Allgemeine Bildoptimierungen für alle Typen mit dem Präfix "opt_"
rex_media_manager_type::ensureEffectToTypes('opt_*', 'image_properties', [
    'jpg_quality' => 85,
    'webp_quality' => 90,
    'avif_quality' => 80,
], 'prepend');

// Bildtyp für optimierte Ausgabe
rex_media_manager_type::get('opt_content')
    ->setDescription('Optimiertes Inhaltsbild')
    ->ensureEffectByName('resize', [
        'width' => 1000,
        'height' => 800,
        'style' => 'maximum'
    ])
    ->ensure();
```

## Import/Export

### Media Type exportieren

```php
// Alle Typen exportieren
$json = rex_media_manager_type::exportToJson();

// Nur bestimmte Typen exportieren
$json = rex_media_manager_type::exportToJson(['team_full', 'team_preview']);

// In Datei exportieren
rex_media_manager_type::exportToFile('media_types.json');
```

### Media Types importieren

```php
// Typen aus JSON-Datei importieren
rex_media_manager_type::importFromJson('media_types.json');
```


-----

# MediaManagerHelper

Eine Hilfsklasse für REDAXO, um Media Manager Typen und Effekte einfach in AddOns zu verwalten.

## Features

- Einfaches Anlegen und Verwalten von Media Manager Typen
- Automatisches Update bestehender Typen
- Validierung von Effekten und Parametern
- Debug-Möglichkeiten für verfügbare Effekte
- Import/Export von Medientypen als JSON
- Automatische Bereinigung bei AddOn-Deinstallation


## Warum ein MediaManagerHelper?

### Ohne Helper (Raw SQL)
```php
// Media Typ anlegen
$sql = rex_sql::factory();
$sql->setTable(rex::getTable('media_manager_type'));
$sql->setValue('name', 'mein_typ');
$sql->setValue('description', 'Mein Typ');
$sql->addGlobalCreateFields();
$sql->insert();

$typeId = $sql->getLastId();

// Effekt hinzufügen  
$sql->setTable(rex::getTable('media_manager_type_effect'));
$sql->setValue('type_id', $typeId); 
$sql->setValue('effect', 'resize');
$sql->setValue('parameters', json_encode([
    'rex_effect_resize' => [
        'width' => 500,
        'height' => 500
    ]
]));
$sql->setValue('priority', 1);
$sql->addGlobalCreateFields();
$sql->insert();
```

### Mit Helper
```php
$mm = MediaManagerHelper::factory();
$mm->addType('mein_typ', 'Mein Typ')
   ->addEffect('mein_typ', 'resize', [
       'width' => 500, 
       'height' => 500
   ])
   ->install();
```

## Und das ist noch nicht alles

- Parameter für Effekte anzeigen: `$mm->showEffectParams('resize')`
- Typen exportieren/importieren: `$mm->exportToJson(['mein_typ'])`
- Automatische Validierung aller Parameter
- Automatisches Update bestehender Typen
- Mehrern Typen oder allen einen Effekt hinzufügen am Anfang oder am Ende

Der Helper macht's einfach, sicher und wartbar. 🚀


## Einfache Verwendung

### In der install.php des eigenen AddOns

```php
$mm = MediaManagerHelper::factory();

// Einfachen Thumbnail erstellen
$mm->addType('mein_thumb', 'Thumbnail für mein AddOn')
   ->addEffect('mein_thumb', 'resize', [
       'width' => 500,
       'height' => 500
   ])
   ->install();
```

### In der uninstall.php

```php
$mm = MediaManagerHelper::factory();
$mm->addType('mein_thumb')->uninstall();
```

## Erweiterte Beispiele

### Focuspoint_fit mit Resize 

```php
$mm = MediaManagerHelper::factory();
$mm->addType('quadrat_fokus', 'Quadratisches Bild mit Fokuspunkt')
    // Zuerst auf max 2000px bringen
    ->addEffect('quadrat_fokus', 'resize', [
        'width' => 2000,
        'height' => 2000,
        'style' => 'maximum',
        'allow_enlarge' => 'not_enlarge'
    ], 1)
    // Dann quadratisch zuschneiden mit Fokuspunkt
    ->addEffect('quadrat_fokus', 'focuspoint_fit', [
        'width' => '1fr',     
        'height' => '1fr',    
        'zoom' => '0',       
        'meta' => 'med_focuspoint',
        'focus' => '50.0,50.0'  // Fallback Fokuspunkt in der Mitte (x,y)
    ], 2)
    ->install();
```


## Import und Export

### Media Types als JSON exportieren

```php
$mm = MediaManagerHelper::factory();

// Alle Custom-Typen exportieren (ohne System-Typen)
$json = $mm->exportToJson();

// Nur bestimmte Typen exportieren
$json = $mm->exportToJson(['mein_typ', 'mein_anderer_typ']);

// Direkt in Datei speichern
$mm->exportToJson(
    ['mein_typ'], // typen
    'media_types.json', // datei
    true, // pretty print
    false // keine system typen
);

// Alternative Methode für Datei-Export
$success = $mm->exportToFile(
    'media_types.json',
    ['mein_typ']
);
```

### Media Types aus JSON importieren

```php
// In der install.php:
$mm = MediaManagerHelper::factory();

// Typen aus JSON-Datei importieren und installieren
$mm->importFromJson($this->getPath('media_types.json'))
   ->install();
```

Beispiel JSON-Datei (`media_types.json`):
```json
[
    {
        "name": "mein_typ",
        "description": "Mein Media Manager Typ",
        "effects": {
            "1": {
                "effect": "resize",
                "params": {
                    "rex_effect_resize": {
                        "width": 800,
                        "height": 600,
                        "style": "maximum",
                        "allow_enlarge": "not_enlarge"
                    }
                }
            }
        }
    }
]
```

## Debug-Funktionen

### Parameter eines Effekts anzeigen

```php
// Parameter eines Effekts anzeigen
$mm = MediaManagerHelper::factory();
$mm->showEffectParams('focuspoint_fit');

/* Ausgabe:
array:3 [
    "name" => "focuspoint_fit"
    "class" => "rex_effect_focuspoint_fit"
    "params" => array:5 [
        "meta" => array:4 [
            "type" => "select"
            "default" => "med_focuspoint"
            "options" => array:2 [
                12 => "med_focuspoint"
                13 => "default => Koordinate / Ersatzwert"
            ]
            "notice" => null
        ]
        "focus" => array:4 [
            "type" => "string"
            "default" => null
            "options" => null
            "notice" => "x,y: 0.0,0.0 ... 100.0,100.0"
        ]
        "width" => array:4 [
            "type" => "int"
            "default" => null
            "options" => null
            "notice" => "absolut: n [px] | relativ: n % | Aspect-Ratio: n fr"
        ]
        "height" => array:4 [
            "type" => "int"
            "default" => null
            "options" => null
            "notice" => "absolut: n [px] | relativ: n % | Aspect-Ratio: n fr"
        ]
        "zoom" => array:4 [
            "type" => "select"
            "default" => null
            "options" => array:5 []
            "notice" => "0% = Zielgröße (kein Zoom) ... 100% = Ausschnitt größtmöglich wählen"
        ]
    ]
]
*/

// Aus dieser Debug-Ausgabe können wir dann den entsprechenden Effekt mit den richtigen Parametern bauen:
$mm->addType('quadrat_fokus', 'Quadratisches Bild mit Fokuspunkt')
    ->addEffect('quadrat_fokus', 'focuspoint_fit', [
        'width' => '1fr',     // Aus notice: "Aspect-Ratio: n fr"
        'height' => '1fr',    // Gleiches Seitenverhältnis für Quadrat
        'zoom' => '0',        // Aus notice: "0% = Zielgröße"
        'meta' => 'med_focuspoint', // Aus options
        'focus' => '50.0,50.0'  // Aus notice: "x,y: 0.0,0.0 ... 100.0,100.0"
    ])
    ->install();
```

### Alle verfügbaren Effekte anzeigen

```php
$mm = MediaManagerHelper::factory();
$mm->listAvailableEffects();

/* Ausgabe z.B.:
array:15 [
    0 => "convert2img"
    1 => "crop"
    2 => "filter_blur"
    3 => "filter_brightness"
    4 => "filter_contrast"
    5 => "filter_sharpen"
    6 => "flip"
    7 => "focuspoint_fit"
    8 => "header"
    9 => "image_format"
    10 => "image_properties"
    11 => "insert_image"
    12 => "mediapath"
    13 => "klxm_mediaproxy"
    14 => "resize"
    15 => "workspace"
]
*/
```

## Häufige Anwendungsfälle

### Thumbnail mit maximaler Größe

```php
$mm->addType('max_thumb', 'Thumbnail mit maximaler Größe')
   ->addEffect('max_thumb', 'resize', [
       'width' => 800,
       'height' => 600,
       'style' => 'maximum',
       'allow_enlarge' => 'not_enlarge'
   ]);
```

### Quadratischer Thumbnail

```php
$mm->addType('square_thumb', 'Quadratischer Thumbnail')
   ->addEffect('square_thumb', 'resize', [
       'width' => 400,
       'height' => 400,
       'style' => 'minimum'
   ])
   ->addEffect('square_thumb', 'crop', [
       'width' => 400,
       'height' => 400,
       'hpos' => 'center',
       'vpos' => 'middle'
   ], 2);
```

### Bild mit Wasserzeichen

```php
$mm->addType('watermark', 'Bild mit Wasserzeichen')
   ->addEffect('watermark', 'resize', [
       'width' => 1200,
       'height' => 1200,
       'style' => 'maximum'
   ])
   ->addEffect('watermark', 'insert_image', [
       'brandimage' => 'wasserzeichen.png',
       'hpos' => 'center',
       'vpos' => 'middle',
       'padding_x' => -20,
       'padding_y' => -20
   ], 2);
```

### Effekte mehreren Typen zuweisen

```php
/**
 * Fügt mehreren Typen einen Effekt hinzu
 * @param string|array $types Pattern (z.B. "team_*") oder Array von Typnamen
 * @param string $effect Name des Effekts
 * @param array $params Effekt-Parameter
 * @param string $position 'append' oder 'prepend'
 */
$mm = MediaManagerHelper::factory();

// Allen Team-Typen einen Wasserzeichen-Effekt hinzufügen
$mm->addEffectToTypes('team_*', 'insert_image', [
    'brandimage' => 'logo.png',
    'hpos' => 'center'
], 'append');

// Mehreren Typen einen Resize voranstellen
$mm->addEffectToTypes(
    ['type1', 'type2'], 
    'resize',
    ['width' => 2000],
    'prepend'
);

// Allen vorhandenen Typen einen Effekt anfügen
$mm->addEffectToTypes('*', 'resize', [
    'width' => 2000,
    'height' => 2000,
    'style' => 'maximum'
]);
```

## Fehlerbehandlung

Die Klasse prüft automatisch:
- Ob ein Effekt verfügbar ist
- Ob die angegebenen Parameter gültig sind
- Ob der Media Manager verfügbar ist

Bei Fehlern wird eine `rex_exception` geworfen.


## MediaManagerHelper - API Dokumentation

### Factory Methode

```php
public static function factory(): self
```
Erstellt eine neue Instanz der Helper-Klasse.

```php
$mm = MediaManagerHelper::factory();
```

### Media Type Methoden

```php
public function addType(string $name, string $description = ''): self
```
Fügt einen neuen Media Manager Typ hinzu.

```php
$mm->addType('mein_typ', 'Meine Bildbearbeitung');
```

```php
public function addEffect(
    string $type, 
    string $effect, 
    array $params = [], 
    int $priority = 1
): self
```
Fügt einem Typ einen Effekt hinzu. Die Priorität bestimmt die Ausführungsreihenfolge.

```php
$mm->addEffect('mein_typ', 'resize', [
    'width' => 500,
    'height' => 500
]);
```

Fügt mehreren Typen einen Effekt hinzu. `position` bestimmt, ob der Effekt am Anfang (prepend) oder Ende (append) der Effektkette eingefügt wird.

```php 
public function addEffectToTypes(
    $types,           // Pattern (z.B. "team_*") oder Array von Typnamen 
    string $effect,   // Name des Effekts
    array $params = [], // Effekt-Parameter
    string $position = 'append' // 'append' oder 'prepend'
): self
```

```php
public function install(): void
```
Installiert oder aktualisiert alle konfigurierten Typen.

```php
$mm->install();
```

```php
public function uninstall(): void
```
Deinstalliert die angegebenen Typen.

```php
$mm->addType('mein_typ')->uninstall();
```

### Import/Export Methoden

```php
public function exportToJson(
    ?array $typeNames = null,
    ?string $file = null,
    bool $prettyPrint = true,
    bool $includeSystemTypes = false
): string
```
Exportiert Media Manager Typen als JSON.

```php
// Alle Custom-Typen exportieren
$json = $mm->exportToJson();

// Bestimmte Typen in Datei exportieren
$mm->exportToJson(['mein_typ'], 'media_types.json');
```

```php
public function importFromJson(string $jsonFile): self
```
Importiert Media Manager Typen aus einer JSON-Datei.

```php
$mm->importFromJson($this->getPath('media_types.json'))->install();
```

### Debug Methoden

```php
public function showEffectParams(string $effect, bool $dump = true): ?array
```
Zeigt die verfügbaren Parameter eines Effekts an.

```php
// Parameter eines beliebigen Effekts anzeigen
$mm->showEffectParams('resize');
```

```php
public function listAvailableEffects(bool $dump = true): ?array
```
Listet alle verfügbaren Effekte auf.

```php
// Alle Effekte anzeigen
$mm->listAvailableEffects();

// Als Array zurückgeben
$effects = $mm->listAvailableEffects(false);
```

### JSON Format

Format für Import/Export:
```json
[
    {
        "name": "mein_typ",
        "description": "Beschreibung",
        "effects": {
            "1": {
                "effect": "resize",
                "params": {
                    "rex_effect_resize": {
                        "width": 800,
                        "height": 600
                    }
                }
            }
        }
    }
]
```


## Lizenz

MIT
