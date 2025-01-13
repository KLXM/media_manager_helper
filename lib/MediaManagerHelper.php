<?php
/**
 * REDAXO Media Manager Helper
 * Einfaches Handling von Media Manager Typen/Effekten in AddOns
 */
class MediaManagerHelper
{
    /**
     * Zeigt die verfügbaren Parameter eines Effekts an
     * @param string $effect Name des Effekts (z.B. 'resize', 'crop')
     * @param bool $dump Wenn true, wird var_dump statt return verwendet
     * @return ?array Returns array mit Infos oder null wenn gedumpt
     */
    public function showEffectParams(string $effect, bool $dump = true): ?array
    {
        if (!$this->isEffectAvailable($effect)) {
            throw new rex_exception('Effect "' . $effect . '" is not available');
        }

        $className = 'rex_effect_' . $effect;
        $effectObj = new $className();
        
        $info = [
            'name' => $effect,
            'class' => $className,
            'params' => []
        ];

        foreach ($effectObj->getParams() as $param) {
            $info['params'][$param['name']] = [
                'type' => $param['type'],
                'default' => $param['default'] ?? null,
                'options' => $param['options'] ?? null,
                'notice' => $param['notice'] ?? null
            ];
        }

        if ($dump) {
            dump($info);
            return null;
        }

        return $info;
    }

    /**
     * Listet alle verfügbaren Effekte auf
     * @param bool $dump Wenn true, wird var_dump statt return verwendet
     * @return ?array Returns array mit Effekten oder null wenn gedumpt
     */
    public function listAvailableEffects(bool $dump = true): ?array
    {
        $effects = [];
        foreach (rex_media_manager::getSupportedEffects() as $class => $effect) {
            $effects[] = str_replace('rex_effect_', '', $effect);
        }
        sort($effects);

        if ($dump) {
            dump($effects);
            return null;
        }

        return $effects;
    }

    private array $types = [];
    private bool $removeOnUninstall = true;

    public static function factory(): self 
    {
        return new self();
    }

    /**
     * Medientyp hinzufügen
     */
    public function addType(string $name, string $description = ''): self 
    {
        $this->types[$name] = [
            'name' => $name,
            'description' => $description,
            'effects' => []
        ];
        return $this;
    }

    /**
     * Effekt zum Medientyp hinzufügen
     */
    public function addEffect(string $type, string $effect, array $params = [], int $priority = 1): self 
    {
        // Prüfen ob der Effekt überhaupt existiert
        if (!$this->isEffectAvailable($effect)) {
            throw new rex_exception('Effect "' . $effect . '" is not available');
        }

        if (!isset($this->types[$type])) {
            $this->addType($type);
        }

        // Parameter mit Namespace versehen
        $effectKey = 'rex_effect_' . $effect;
        $paramKeys = $this->getEffectParamKeys($effect);

        $parameters = [];
        foreach ($params as $key => $value) {
            $fullKey = $effectKey . '_' . $key;
            if (!in_array($fullKey, $paramKeys)) {
                throw new rex_exception('Unknown parameter "' . $key . '" for effect "' . $effect . '"');
            }
            $parameters[$fullKey] = $value;
        }

        $this->types[$type]['effects'][$priority] = [
            'effect' => $effect,
            'params' => [$effectKey => $parameters]
        ];

        return $this;
    }

    /**
     * Fügt mehreren Typen einen Effekt hinzu
     * @param string|array $types Pattern (z.B. "team_*") oder Array von Typnamen
     * @param string $effect Name des Effekts
     * @param array $params Effekt-Parameter
     * @param string $position 'append' oder 'prepend'
     */
    public function addEffectToTypes($types, string $effect, array $params = [], string $position = 'append'): self
    {
        // Wenn Pattern, dann passende Typen finden
        if (is_string($types) && str_ends_with($types, '*')) {
            $pattern = str_replace('*', '', $types);
            $sql = rex_sql::factory();
            $types = $sql->getArray('SELECT name FROM '.rex::getTable('media_manager_type').' WHERE name LIKE :pattern', [
                'pattern' => $pattern.'%'
            ]);
            $types = array_column($types, 'name');
        }
        
        // Effekt zu allen Typen hinzufügen
        foreach ($types as $type) {
            // Aktuelle Prioritäten laden
            $sql = rex_sql::factory();
            $priorities = $sql->getArray('
                SELECT priority 
                FROM '.rex::getTable('media_manager_type_effect').' 
                WHERE type_id = (SELECT id FROM '.rex::getTable('media_manager_type').' WHERE name = :name)
                ORDER BY priority', 
                ['name' => $type]
            );
            
            $priority = match($position) {
                'prepend' => 1,
                'append' => count($priorities) + 1,
            };
            
            // Bei prepend alle anderen Prioritäten um 1 erhöhen
            if ($position === 'prepend' && !empty($priorities)) {
                $sql->setQuery('
                    UPDATE '.rex::getTable('media_manager_type_effect').'
                    SET priority = priority + 1
                    WHERE type_id = (SELECT id FROM '.rex::getTable('media_manager_type').' WHERE name = :name)',
                    ['name' => $type]
                );
            }
            
            $this->addEffect($type, $effect, $params, $priority);
        }
        
        return $this;
    }

    /**
     * Medientypen bei Deinstallation behalten
     */
    public function keepTypesOnUninstall(): self 
    {
        $this->removeOnUninstall = false;
        return $this;
    }

    /**
     * Prüft ob ein Effekt verfügbar ist
     */
    private function isEffectAvailable(string $effect): bool 
    {
        $effects = rex_media_manager::getSupportedEffects();
        return isset($effects['rex_effect_' . $effect]);
    }

    private function getEffectName($effect): string 
    {
        if ($effect instanceof rex_effect_abstract) {
            $className = get_class($effect);
            return str_replace('rex_effect_', '', $className);
        }
        return $effect;
    }

    /**
     * Holt die verfügbaren Parameter für einen Effekt
     */
    private function getEffectParamKeys(string $effect): array 
    {
        $className = 'rex_effect_' . $effect;
        if (!class_exists($className)) {
            return [];
        }

        $effect = new $className();
        $validParams = [];
        foreach ($effect->getParams() as $param) {
            $validParams[] = 'rex_effect_' . $this->getEffectName($effect) . '_' . $param['name'];
        }
        return $validParams;
    }

    /**
     * Installiert oder aktualisiert die Medientypen
     */
    public function install(): void 
    {
        if (!rex_addon::get('media_manager')->isAvailable()) {
            return;
        }

        $sql = rex_sql::factory();

        foreach ($this->types as $type) {
            $sql->setQuery('SELECT id FROM ' . rex::getTable('media_manager_type') . ' WHERE name = :name', [':name' => $type['name']]);

            if ($sql->getRows()) {
                // Update
                $typeId = $sql->getValue('id');
                $sql->setTable(rex::getTable('media_manager_type'));
                $sql->setWhere(['id' => $typeId]);
                $sql->setValue('description', $type['description']);
                $sql->addGlobalUpdateFields();
                $sql->update();

                // Alte Effekte löschen
                $sql->setQuery('DELETE FROM ' . rex::getTable('media_manager_type_effect') . ' WHERE type_id = ?', [$typeId]);
            } else {
                // Neu anlegen
                $sql->setTable(rex::getTable('media_manager_type'));
                $sql->setValue('name', $type['name']);
                $sql->setValue('description', $type['description']);
                $sql->addGlobalCreateFields();
                $sql->insert();
                $typeId = $sql->getLastId();
            }

            // Effekte anlegen
            if (!empty($type['effects'])) {
                foreach ($type['effects'] as $priority => $effect) {
                    $sql->setTable(rex::getTable('media_manager_type_effect'));
                    $sql->setValue('type_id', $typeId);
                    $sql->setValue('effect', $effect['effect']);
                    $sql->setValue('priority', $priority);
                    $sql->setValue('parameters', json_encode($effect['params']));
                    $sql->addGlobalCreateFields();
                    $sql->insert();
                }
            }
        }

        rex_media_manager::deleteCache();
    }

    /**
     * Media Types aus JSON-Datei importieren
     */
    public function importFromJson(string $jsonFile): self
    {
        if (!file_exists($jsonFile)) {
            throw new rex_exception('JSON file not found: ' . $jsonFile);
        }

        $json = file_get_contents($jsonFile);
        $types = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new rex_exception('Invalid JSON file: ' . json_last_error_msg());
        }

        foreach ($types as $type) {
            $this->addType($type['name'], $type['description'] ?? '');
            
            if (isset($type['effects'])) {
                foreach ($type['effects'] as $priority => $effect) {
                    $this->addEffect(
                        $type['name'],
                        $effect['effect'],
                        $effect['params'][$this->getEffectParamsKey($effect['effect'])] ?? [],
                        $priority
                    );
                }
            }
        }

        return $this;
    }

    /**
     * Exportiere Media Types als JSON
     * @param array|null $typeNames Welche Typen exportiert werden sollen, null für alle
     * @param string|null $file Optional: Datei in die exportiert werden soll
     * @param bool $prettyPrint JSON formatieren
     * @param bool $includeSystemTypes Auch System-Typen (rex_media_*) exportieren
     * @return string JSON String
     */
    public function exportToJson(
        ?array $typeNames = null,
        ?string $file = null,
        bool $prettyPrint = true,
        bool $includeSystemTypes = false
    ): string {
        $sql = rex_sql::factory();
        
        $where = [];
        $params = [];
        
        // System-Typen filtern
        if (!$includeSystemTypes) {
            $where[] = 'SUBSTR(name, 1, 10) != "rex_media_"';
        }
        
        // Bestimmte Typen filtern
        if ($typeNames !== null) {
            $where[] = 'name IN (:types)';
            $params['types'] = $typeNames;
        }
        
        // Query bauen
        $query = 'SELECT id, name, description FROM ' . rex::getTable('media_manager_type');
        if (!empty($where)) {
            $query .= ' WHERE ' . implode(' AND ', $where);
        }
        $query .= ' ORDER BY name ASC';
        
        // Typen holen
        $types = $sql->getArray($query, $params);

        $export = [];
        
        foreach ($types as $type) {
            $exportType = [
                'name' => $type['name'],
                'description' => $type['description'],
                'effects' => []
            ];

            // Effekte holen
            $effects = $sql->getArray('
                SELECT * FROM ' . rex::getTable('media_manager_type_effect') . '
                WHERE type_id = :id
                ORDER BY priority ASC
            ', ['id' => $type['id']]);

            foreach ($effects as $effect) {
                $exportType['effects'][$effect['priority']] = [
                    'effect' => $effect['effect'],
                    'params' => json_decode($effect['parameters'], true)
                ];
            }

            $export[] = $exportType;
        }

        $flags = $prettyPrint ? JSON_PRETTY_PRINT : 0;
        $json = json_encode($export, $flags | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($file !== null) {
            if (!rex_file::put($file, $json)) {
                throw new rex_exception('Could not write to file: ' . $file);
            }
        }

        return $json;
    }

    /**
     * Exportiere Media Types in eine Datei
     * @param string $file Zieldatei
     * @param array|null $typeNames Welche Typen exportiert werden sollen, null für alle
     * @param bool $prettyPrint JSON formatieren
     * @param bool $includeSystemTypes Auch System-Typen (rex_media_*) exportieren
     * @return bool Erfolgreich gespeichert
     */
    public function exportToFile(
        string $file, 
        ?array $typeNames = null,
        bool $prettyPrint = true,
        bool $includeSystemTypes = false
    ): bool {
        try {
            $this->exportToJson($typeNames, $file, $prettyPrint, $includeSystemTypes);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Deinstalliert die angegebenen Typen
     */
    public function uninstall(): void 
    {
        if (!$this->removeOnUninstall || !rex_addon::get('media_manager')->isAvailable()) {
            return;
        }

        $sql = rex_sql::factory();
        foreach ($this->types as $type) {
            $sql->setQuery('SELECT id FROM ' . rex::getTable('media_manager_type') . ' WHERE name = :name', [':name' => $type['name']]);
            if ($sql->getRows()) {
                $typeId = $sql->getValue('id');
                $sql->setQuery('DELETE FROM ' . rex::getTable('media_manager_type_effect') . ' WHERE type_id = ?', [$typeId]);
                $sql->setQuery('DELETE FROM ' . rex::getTable('media_manager_type') . ' WHERE id = ?', [$typeId]);
            }
        }
        rex_media_manager::deleteCache();
   }

   /**
    * Hilfsmethode für Effekt-Parameter-Key
    */
   private function getEffectParamsKey(string $effect): string
   {
       return 'rex_effect_' . $effect;
   }
}
