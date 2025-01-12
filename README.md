# MediaManagerHelper

Eine Hilfsklasse für REDAXO, um Media Manager Typen und Effekte einfach in AddOns zu verwalten.

## Features

- Einfaches Anlegen und Verwalten von Media Manager Typen
- Automatisches Update bestehender Typen
- Validierung von Effekten und Parametern
- Debug-Möglichkeiten für verfügbare Effekte
- Automatische Bereinigung bei AddOn-Deinstallation (optional)

## Installation

Die Klasse in das `lib/` Verzeichnis deines AddOns kopieren:

```
mein_addon/
  lib/
    MediaManagerHelper.php
```

## Einfache Verwendung

### In der install.php

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
$mm->addType('mein_thumb')->uninstall();
```

## Erweiterte Beispiele

### Mehrere Effekte kombinieren

```php
$mm = MediaManagerHelper::factory();

$mm->addType('quadrat_fokus', 'Quadratisches Bild mit Fokuspunkt')
    // Erst resize
    ->addEffect('quadrat_fokus', 'resize', [
        'width' => 2000,
        'height' => 2000,
        'style' => 'maximum',
        'allow_enlarge' => 'not_enlarge'
    ], 1)
    // Dann mit Fokuspunkt zuschneiden
    ->addEffect('quadrat_fokus', 'focuspoint_fit', [
        'width' => '1fr',     
        'height' => '1fr',    
        'zoom' => '0',       
        'meta' => 'med_focuspoint',
        'focus' => '50.0,50.0'
    ], 2)
    ->install();
```

### Typen bei Deinstallation behalten

```php
$mm->keepTypesOnUninstall()->uninstall();
```

## Debug-Funktionen

### Verfügbare Effekte anzeigen

```php
$mm = MediaManagerHelper::factory();

// Alle Effekte anzeigen
$mm->listAvailableEffects();

// Als Array zurückgeben statt dumpen
$effects = $mm->listAvailableEffects(false);
```

### Parameter eines Effekts anzeigen

```php
// Parameter eines Effekts anzeigen
$mm->showEffectParams('resize');

// Parameter als Array zurückgeben
$params = $mm->showEffectParams('resize', false);
```

## API

### Hauptmethoden

```php
// Typ hinzufügen
addType(string $name, string $description = '')

// Effekt hinzufügen
addEffect(string $type, string $effect, array $params = [], int $priority = 1)

// Installation durchführen
install()

// Deinstallation durchführen
uninstall()

// Typen bei Deinstallation behalten
keepTypesOnUninstall()
```

### Debug-Methoden

```php
// Verfügbare Effekte auflisten
listAvailableEffects(bool $dump = true)

// Parameter eines Effekts anzeigen
showEffectParams(string $effect, bool $dump = true)
```

## Fehlerbehandlung

Die Klasse prüft automatisch:
- Ob ein Effekt verfügbar ist
- Ob die angegebenen Parameter gültig sind
- Ob der Media Manager verfügbar ist

Bei Fehlern wird eine `rex_exception` geworfen.

## Beispiele für gängige Anwendungsfälle

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

## Lizenz

MIT
