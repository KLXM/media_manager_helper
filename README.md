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
    13 => "klxm_mediaporxy"
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
