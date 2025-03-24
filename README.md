# MediaManagerHelper

Eine Hilfsklasse für REDAXO, um Media Manager Typen und Effekte einfach in AddOns zu verwalten und ein Srcset-Effekt.

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
## SRCSET Helper Effekt

Der SRCSET Helper Effekt ermöglicht es, responsive Bilder mit unterschiedlichen Größen für verschiedene Bildschirmauflösungen anzubieten. Das Add-on bietet auch Art Direction-Unterstützung, um verschiedene Bildausschnitte für verschiedene Geräte zu definieren.

### Verwendung im Media Manager

1. Erstelle einen Media Manager Typ für dein Bild
2. Füge zuerst einen Resize- oder Crop-Effekt hinzu
3. Füge dann den "SRCSET Helper" Effekt hinzu
4. Konfiguriere das SRCSET-Attribut, z.B. `480 480w, 768 768w, 1024 1024w, 1600 1600w`

### Verwendung im Template

Zuerst die Klasse einbinden:

```php
use KLXM\MediaManagerHelper\ResponsiveImage;
```

Dann die Methoden verwenden:

```php
// Einfaches responsives Bild
echo ResponsiveImage::getImageByType('bild.jpg', 'mein_typ', [
    'alt' => 'Beschreibung',
    'class' => 'responsive-img'
]);

// Mit Art Direction (unterschiedliche Typen für Desktop und Mobile)
echo ResponsiveImage::getPictureTag(
    'bild.jpg',
    'desktop_typ',
    [
        '(max-width: 768px)' => 'mobile_typ'
    ],
    [
        'alt' => 'Beschreibung'
    ]
);

// Mit erweiterten Optionen (unterschiedliche Bilder und Größen)
echo ResponsiveImage::getPictureTag(
    'desktop-bild.jpg',
    'desktop_typ',
    [
        [
            'media' => '(max-width: 768px)',
            'file' => 'mobiles-bild.jpg',
            'type' => 'mobile_typ',
            'sizes' => '100vw'
        ]
    ],
    [
        'alt' => 'Beschreibung'
    ]
);
```

### Praktische Anwendungsbeispiele

#### Beispiel 1: Responsive Artikel-Bilder

```php
// Im Template für Artikel-Detailansichten
echo ResponsiveImage::getImageByType($article->getImage(), 'article_image', [
    'alt' => $article->getTitle(),
    'class' => 'article-image'
]);
```

#### Beispiel 2: Responsive Cards in unterschiedlichen Breiten

```php
// Für ein Kartenraster mit unterschiedlichen Kartengrößen
$cardType = '';

// Container-Klasse bestimmt den passenden Typ
if (strpos($container_class, 'uk-width-1-1') !== false) {
    $cardType = 'card_full';
} elseif (strpos($container_class, 'uk-width-1-2') !== false) {
    $cardType = 'card_half';
} elseif (strpos($container_class, 'uk-width-1-3') !== false) {
    $cardType = 'card_third';
}

echo ResponsiveImage::getImageByType($card->getImage(), $cardType, [
    'alt' => $card->getTitle(),
    'class' => 'card-image'
]);
```

#### Beispiel 3: Art Direction für Headerbild

```php
// Header-Bild mit unterschiedlichen Zuschnitten für verschiedene Geräte
echo ResponsiveImage::getPictureTag(
    'header.jpg',
    'header_desktop',
    [
        // Smartphone (Portrait)
        '(max-width: 576px)' => 'header_mobile_portrait',
        // Tablet (Landscape)
        '(max-width: 992px)' => 'header_tablet_landscape'
    ],
    [
        'alt' => 'Website Header',
        'class' => 'header-image'
    ]
);
```

#### Beispiel 4: Unterschiedliche Produktbilder für Desktop und Mobile

```php
// Zeige auf mobilen Geräten ein anderes Produktbild
echo ResponsiveImage::getPictureTag(
    $product->getDesktopImage(),
    'product_desktop',
    [
        [
            'media' => '(max-width: 768px)',
            'file' => $product->getMobileImage(),
            'type' => 'product_mobile',
            'sizes' => '100vw'
        ]
    ],
    [
        'alt' => $product->getName(),
        'class' => 'product-image'
    ]
);
```

### Tipps und Best Practices

#### Medientypen für verschiedene Anwendungsfälle erstellen

Für optimale Ergebnisse empfehlen wir, spezifische Medientypen für verschiedene Anwendungsfälle zu erstellen:

- **card_full** - für Container mit voller Breite
- **card_half** - für Container mit halber Breite
- **card_third** - für Container mit einem Drittel Breite
- **card_mobile_portrait** - für Mobil-Ansicht im Portrait-Format
- **card_desktop_landscape** - für Desktop-Ansicht im Landscape-Format

#### SVG und andere nicht-pixelbasierte Formate

Bei SVG und anderen nicht-pixelbasierten Formaten (PDF, EPS) wird das Bild immer direkt ohne SRCSET-Attribute ausgegeben, um Kompatibilitätsprobleme zu vermeiden.

#### Effektive Media Manager Typen konfigurieren

1. Erstelle einen Basistyp mit allgemeinen Einstellungen (z.B. Wasserzeichen oder Schärfen)
2. Erstelle davon abgeleitete Typen für verschiedene Anwendungsfälle
3. Füge den SRCSET-Helper Effekt hinzu und konfiguriere passende Bildgrößen:
   - Für volle Breite: `480 480w, 800 800w, 1200 1200w, 1920 1920w`
   - Für halbe Breite: `360 360w, 720 720w, 900 900w`
   - Für Drittel-Breite: `300 300w, 600 600w, 800 800w`

#### Optimale SRCSET-Konfiguration

- Wähle eine gute Bandbreite an Bildgrößen, um verschiedene Geräte und Auflösungen abzudecken
- Verwende den 2x Modifikator für Retina-Displays wo sinnvoll
- Vermeide zu viele Bildgrößen, da dies die Ladezeit und den Cache-Speicher beeinträchtigen kann

### Dynamische Bildanpassung mit JavaScript

Das SRCSET Attribut kann auch als data-srcset Attribut eingebunden werden. Dann lädt der Browser zunächst das Standardbild (im SRC-Attribut). Das JavaScript für dynamische Anpassung wird automatisch eingebunden, wenn ein data-srcset Attribut erkannt wird.

#### Beispiel:

```html
<img width="500" src="index.php?rex_media_type=ImgTypeName&rex_media_file=ImageFileName"
    data-srcset="index.php?rex_media_type=ImgTypeName__400&rex_media_file=ImageFileName 480w,
                 index.php?rex_media_type=ImgTypeName__700&rex_media_file=ImageFileName 768w,
                 index.php?rex_media_type=ImgTypeName__800&rex_media_file=ImageFileName 960w">
```

#### Manuelle JavaScript-Aktualisierung

Du kannst die Bildgrößen nach DOM-Änderungen manuell aktualisieren:

```javascript
// Nach dynamischen DOM-Änderungen
if (typeof window.klxmMediaSrcsetProcess === 'function') {
    window.klxmMediaSrcsetProcess();
}
```

### FAQ

#### Warum werden meine SVG-Dateien nicht mit dem SRCSET-Attribut versehen?

SVG-Dateien sind vektorbasiert und skalieren ohne Qualitätsverlust. Daher werden sie absichtlich ohne SRCSET-Attribut ausgegeben, um Kompatibilitätsprobleme zu vermeiden.

#### Kann ich unterschiedliche Bilder für Portrait- und Landscape-Orientierung verwenden?

Ja, mit der erweiterten `getPictureTag()`-Methode kannst du komplett unterschiedliche Bilder für verschiedene Viewports definieren. Siehe Beispiel 3 in der Dokumentation.

#### Wie kann ich das SRCSET-Attribut in einem REDAXO-Modul verwenden?

Du kannst die Methoden `getImageByType()` oder `getPictureTag()` in deinem Modul-Output verwenden. Beispiel:

```php
// Im Modul-Output
use KLXM\MediaManagerHelper\ResponsiveImage;

$output = '<div class="my-module">';
$output .= ResponsiveImage::getImageByType($media->getImage(), 'my_module_type', [
    'alt' => $media->getTitle(),
    'class' => 'module-image'
]);
$output .= '</div>';

return $output;
```

#### Wie kann ich eigene responsive Typen mit dem MediaManagerHelper anlegen?

Du kannst den MediaManagerHelper verwenden, um eigene responsive Typen anzulegen:

```php
// In einer Installationsroutine
$mmHelper = MediaManagerHelper::factory();

$mmHelper
    ->addType('card_full', 'Vollbreite responsive Bilder (100%)')
    ->addEffect('card_full', 'resize', [
        'width' => 1920, 
        'height' => '', 
        'style' => 'maximum',
        'allow_enlarge' => 'not_enlarge'
    ], 1)
    ->addEffect('card_full', 'srcset_helper', [
        'srcset' => '480 480w, 768 768w, 1024 1024w, 1366 1366w, 1920 1920w'
    ], 2)
    ->install();
```

## Lizenz

MIT
