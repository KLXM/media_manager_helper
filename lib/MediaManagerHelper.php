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
     * Entfernt die Medientypen bei Deinstallation
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
}
