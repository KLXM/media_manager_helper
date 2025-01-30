<?php

/**
 * Class to represent media manager types.
 *
 * @author gharlan
 *
 * @package redaxo\media-manager
 */
class rex_media_manager_type
{
    use rex_instance_pool_trait;

    private rex_sql $sql;
    private bool $new;
    private string $name;
    private string $originalName;
    private ?string $description;

    /** @var array<int, array{effect: string, parameters: array}> */
    private array $effects = [];

    /** @var array<int, array{effect: string, parameters: array}> */
    private array $effectsExisting = [];

    private function __construct(string $name)
    {
        $this->sql = rex_sql::factory();
        $this->name = $name;
        $this->originalName = $name;

        try {
            $this->sql->setQuery('SELECT * FROM ' . rex::getTable('media_manager_type') . ' WHERE name = ?', [$name]);
            
            if ($this->sql->getRows() > 0) {
                $this->new = false;
                $this->description = $this->sql->getValue('description');

                // Load existing effects
                $effects = $this->sql->getArray('
                    SELECT *
                    FROM ' . rex::getTable('media_manager_type_effect') . '
                    WHERE type_id = ?
                    ORDER BY priority', 
                    [$this->sql->getValue('id')]
                );

                foreach ($effects as $effect) {
                    $this->effectsExisting[$effect['priority']] = [
                        'effect' => $effect['effect'],
                        'parameters' => json_decode($effect['parameters'], true),
                    ];
                }
                $this->effects = $this->effectsExisting;
            } else {
                $this->new = true;
                $this->description = null;
            }
        } catch (rex_sql_exception) {
            $this->new = true;
            $this->description = null;
        }
    }

    /**
     * Gets a type instance for the given name.
     */
    public static function get(string $name): self
    {
        return static::getInstance([$name], static fn ($name) => new self($name));
    }

    /**
     * @return bool
     */
    public function exists(): bool
    {
        return !$this->new;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return $this
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return $this
     */
    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return array<int, array{effect: string, parameters: array}>
     */
    public function getEffects(): array
    {
        return $this->effects;
    }

    /**
     * Ensures that the effect exists with the given parameters.
     * 
     * @return $this
     */
    public function ensureEffect(rex_effect_abstract $effect, ?int $priority = null): self
    {
        $effectName = str_replace('rex_effect_', '', get_class($effect));
        $parameters = [];

        foreach ($effect->getParams() as $param) {
            $name = 'rex_effect_' . $effectName . '_' . $param['name'];
            $value = $effect->params[$param['name']] ?? $param['default'] ?? null;
            $parameters[$name] = $value;
        }

        if (null === $priority) {
            $priority = count($this->effects) + 1;
        }

        $newEffect = [
            'effect' => $effectName,
            'parameters' => ['rex_effect_' . $effectName => $parameters],
        ];

        if (!isset($this->effectsExisting[$priority]) || $this->effectsExisting[$priority] !== $newEffect) {
            $this->effects[$priority] = $newEffect;
        }

        return $this;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function ensureEffectByName(string $effectName, array $parameters = [], ?int $priority = null): self
    {
        $effectClass = 'rex_effect_' . $effectName;
        if (!class_exists($effectClass)) {
            throw new InvalidArgumentException(sprintf('Effect class "%s" does not exist.', $effectClass));
        }

        $effect = new $effectClass();
        $effect->params = $parameters;

        return $this->ensureEffect($effect, $priority);
    }

    /**
     * Ensures that the effect exists at the start.
     */
    public function ensureEffectByNamePrepend(string $effectName, array $parameters = []): self
    {
        $this->prependEffect($effectName, $parameters);
        return $this;
    }

    /**
     * Ensures that the effect exists at the end.
     */
    public function ensureEffectByNameAppend(string $effectName, array $parameters = []): self 
    {
        $this->appendEffect($effectName, $parameters);
        return $this;
    }

    /**
     * @return $this
     */
    public function removeEffect(int $priority): self
    {
        unset($this->effects[$priority]);
        return $this;
    }

    /**
     * Reorders the effects by their keys.
     */
    private function reorderEffects(): void
    {
        ksort($this->effects);
        $effects = [];
        $priority = 1;
        foreach ($this->effects as $effect) {
            $effects[$priority++] = $effect;
        }
        $this->effects = $effects;
    }

    /**
     * Ensures that the type exists with the given definition.
     */
    public function ensure(): void
    {
        if ($this->new) {
            $this->create();
            return;
        }

        $this->alter();
    }

    /**
     * Creates the type.
     */
    public function create(): void
    {
        if (!$this->new) {
            throw new rex_exception(sprintf('Media Manager type "%s" already exists.', $this->name));
        }

        $this->sql->setTable(rex::getTable('media_manager_type'));
        $this->sql->setValue('name', $this->name);
        $this->sql->setValue('description', $this->description);
        $this->sql->addGlobalCreateFields();
        $this->sql->addGlobalUpdateFields();
        $this->sql->insert();

        $typeId = (int) $this->sql->getLastId();
        
        $this->reorderEffects();

        foreach ($this->effects as $priority => $effect) {
            $this->sql->setTable(rex::getTable('media_manager_type_effect'));
            $this->sql->setValue('type_id', $typeId);
            $this->sql->setValue('effect', $effect['effect']);
            $this->sql->setValue('priority', $priority);
            $this->sql->setValue('parameters', json_encode($effect['parameters']));
            $this->sql->addGlobalCreateFields();
            $this->sql->addGlobalUpdateFields();
            $this->sql->insert();
        }

        $this->new = false;
        $this->originalName = $this->name;
        $this->effectsExisting = $this->effects;

        rex_media_manager::deleteCache();
    }

    /**
     * Alters the type.
     */
    private function alter(): void
    {
        $typeId = (int) $this->sql->getValue('id');

        if ($this->name !== $this->originalName) {
            $this->sql->setTable(rex::getTable('media_manager_type'));
            $this->sql->setValue('name', $this->name);
            $this->sql->setValue('description', $this->description);
            $this->sql->addGlobalUpdateFields();
            $this->sql->setWhere(['id' => $typeId]);
            $this->sql->update();
        }

        $this->reorderEffects();

        // Delete old effects
        $this->sql->setQuery('DELETE FROM ' . rex::getTable('media_manager_type_effect') . ' WHERE type_id = ?', [$typeId]);

        // Insert new effects
        foreach ($this->effects as $priority => $effect) {
            $this->sql->setTable(rex::getTable('media_manager_type_effect'));
            $this->sql->setValue('type_id', $typeId);
            $this->sql->setValue('effect', $effect['effect']);
            $this->sql->setValue('priority', $priority);
            $this->sql->setValue('parameters', json_encode($effect['parameters']));
            $this->sql->addGlobalCreateFields();
            $this->sql->addGlobalUpdateFields();
            $this->sql->insert();
        }

        $this->originalName = $this->name;
        $this->effectsExisting = $this->effects;

        rex_media_manager::deleteCache();
    }

    /**
     * Drops the type if it exists.
     */
    public function drop(): void
    {
        if (!$this->new) {
            $typeId = (int) $this->sql->getValue('id');
            
            $this->sql->setQuery('DELETE FROM ' . rex::getTable('media_manager_type_effect') . ' WHERE type_id = ?', [$typeId]);
            $this->sql->setQuery('DELETE FROM ' . rex::getTable('media_manager_type') . ' WHERE id = ?', [$typeId]);
            
            rex_media_manager::deleteCache();
        }

        $this->new = true;
        $this->originalName = $this->name;
        $this->effects = [];
        $this->effectsExisting = [];
    }

    /**
     * Returns a list of all available effects.
     * 
     * @return array<string, rex_effect_abstract>
     */
    public static function getAvailableEffects(): array
    {
        $effects = [];
        foreach (rex_media_manager::getSupportedEffects() as $class => $effect) {
            $shortName = str_replace('rex_effect_', '', $effect);
            $effects[$shortName] = new $class();
        }

        // Sort by effect name
        uasort($effects, static function (rex_effect_abstract $a, rex_effect_abstract $b) {
            return strnatcmp($a->getName(), $b->getName());
        });

        return $effects;
    }

    /**
     * Returns effect parameters and their configuration.
     *
     * @return array{name: string, class: class-string<rex_effect_abstract>, params: array<string, array{type: string, default?: mixed, options?: array, notice?: string}>}
     */
    public static function getEffectParameters(string $effect): array
    {
        if (!self::isEffectAvailable($effect)) {
            throw new rex_exception('Effect "' . $effect . '" is not available');
        }

        $className = 'rex_effect_' . $effect;
        /** @var rex_effect_abstract $effectObj */
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

        return $info;
    }

    /**
     * Import types from a JSON file.
     * 
     * @throws rex_exception
     */
    public static function importFromJson(string $jsonFile): void
    {
        if (!file_exists($jsonFile)) {
            throw new rex_exception('JSON file not found: ' . $jsonFile);
        }

        $json = rex_file::get($jsonFile);
        if (!$json) {
            throw new rex_exception('Could not read JSON file: ' . $jsonFile);
        }

        $types = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new rex_exception('Invalid JSON file: ' . json_last_error_msg());
        }

        foreach ($types as $type) {
            $mediaType = self::get($type['name'])
                ->setDescription($type['description'] ?? null);
            
            if (isset($type['effects'])) {
                foreach ($type['effects'] as $priority => $effect) {
                    $params = rex_type::array($effect['params']['rex_effect_' . $effect['effect']] ?? []);
                    $mediaType->ensureEffectByName($effect['effect'], $params, $priority);
                }
            }

            $mediaType->ensure();
        }
    }

    /**
     * Export types to JSON.
     *
     * @param list<string>|null $typeNames Specific types to export, null for all
     * @param bool $includeSystemTypes Include system types (rex_media_*)
     * @param bool $prettyPrint Format JSON for readability
     * @return string JSON string
     */
    public static function exportToJson(?array $typeNames = null, bool $includeSystemTypes = false, bool $prettyPrint = true): string
    {
        $sql = rex_sql::factory();
        
        $where = [];
        $params = [];
        
        if (!$includeSystemTypes) {
            $where[] = 'name NOT LIKE "rex_media_%"';
        }
        
        if ($typeNames !== null) {
            $where[] = 'name IN (:types)';
            $params['types'] = $typeNames;
        }
        
        $query = 'SELECT id, name, description FROM ' . rex::getTable('media_manager_type');
        if (!empty($where)) {
            $query .= ' WHERE ' . implode(' AND ', $where);
        }
        $query .= ' ORDER BY name';
        
        $types = $sql->getArray($query, $params);

        $export = [];
        foreach ($types as $type) {
            $effects = $sql->getArray('
                SELECT effect, parameters, priority 
                FROM ' . rex::getTable('media_manager_type_effect') . '
                WHERE type_id = :id
                ORDER BY priority',
                ['id' => $type['id']]
            );

            $exportType = [
                'name' => $type['name'],
                'description' => $type['description'],
                'effects' => []
            ];

            foreach ($effects as $effect) {
                $exportType['effects'][$effect['priority']] = [
                    'effect' => $effect['effect'],
                    'params' => json_decode($effect['parameters'], true)
                ];
            }

            $export[] = $exportType;
        }

        $flags = $prettyPrint ? JSON_PRETTY_PRINT : 0;
        return json_encode($export, $flags | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Export types to a JSON file.
     *
     * @param list<string>|null $typeNames Specific types to export, null for all
     * @param bool $includeSystemTypes Include system types (rex_media_*)
     * @param bool $prettyPrint Format JSON for readability
     * @return bool Success
     */
    public static function exportToFile(string $file, ?array $typeNames = null, bool $includeSystemTypes = false, bool $prettyPrint = true): bool
    {
        try {
            $json = self::exportToJson($typeNames, $includeSystemTypes, $prettyPrint);
            return rex_file::put($file, $json) !== false;
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Ensures an effect exists in multiple types at once.
     *
     * @param string|list<string> $types Pattern (e.g. "team_*") or array of type names
     * @param rex_effect_abstract|string $effect Effect instance or name
     * @param array<string, mixed> $params Effect parameters (only used if $effect is a string)
     * @param string $position 'append' or 'prepend' 
     * @throws rex_exception
     */
    public static function ensureEffectToTypes($types, rex_effect_abstract|string $effect, array $params = [], string $position = 'append'): void
    {
        // Find matching types if pattern is given
        if (is_string($types) && str_ends_with($types, '*')) {
            $pattern = str_replace('*', '', $types);
            $sql = rex_sql::factory();
            $types = $sql->getArray('SELECT name FROM '.rex::getTable('media_manager_type').' WHERE name LIKE :pattern', [
                'pattern' => $pattern.'%'
            ]);
            $types = array_column($types, 'name');
        }

        if (!is_array($types)) {
            throw new rex_exception('$types must be an array or a pattern string');
        }
        
        foreach ($types as $type) {
            $mediaType = self::get($type);

            // Get current max priority
            $maxPriority = 0;
            foreach ($mediaType->getEffects() as $priority => $existingEffect) {
                $maxPriority = max($maxPriority, $priority);
            }

            $effects = $mediaType->getEffects();
            
            // Determine priority based on position
            $priority = match($position) {
                'append' => empty($effects) ? 1 : max(array_keys($effects)) + 1,
                'prepend' => 1,
                default => throw new rex_exception('Invalid position: ' . $position)
            };

            // For prepend, shift all priorities up by 1
            if ($position === 'prepend') {
                $mediaType->effects = [];
                foreach ($effects as $prio => $existingEffect) {
                    if ($effect instanceof rex_effect_abstract) {
                        $mediaType->ensureEffect(
                            new $existingEffect['effect']($existingEffect['parameters']['rex_effect_'.$existingEffect['effect']] ?? []),
                            $prio + 1
                        );
                    } else {
                        $mediaType->ensureEffectByName(
                            $existingEffect['effect'],
                            $existingEffect['parameters']['rex_effect_'.$existingEffect['effect']] ?? [], 
                            $prio + 1
                        );
                    }
                }
            }

            // Add new effect at the determined priority
            if ($effect instanceof rex_effect_abstract) {
                $mediaType->ensureEffect($effect, $priority);
            } else {
                $mediaType->ensureEffectByName($effect, $params, $priority);
            }

            $mediaType->ensure();
        }
    }

    private static function isEffectAvailable(string $effect): bool
    {
        return isset(rex_media_manager::getSupportedEffects()['rex_effect_' . $effect]);
    }
}
