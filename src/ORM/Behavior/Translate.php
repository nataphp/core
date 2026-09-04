<?php
/**
 * NataPHP Framework
 *
 * Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 * @link          http://nataphp.com NataPHP Project
 * @since         NataPHP 1.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Nata\ORM\Behavior;

use Nata\I18n\I18n;
use Nata\Event\Event;
use Nata\ORM\Behavior;
use Nata\ORM\Query;
use Nata\ORM\Entity;
use Nata\ORM\Table;
use Nata\ORM\TableRegistry;
use Nata\Utility\Inflector;

/**
 * Makes the data of the table to which this is attached to be translated.
 * Provides methods for managing and retrieving localized data.
 *
 * Tables attaching this behavior are required to have a the name of the fields
 * you would like it to be localized.
 */
class Translate extends Behavior {

/**
 * Context value marking the canonical, context-free translation of a field.
 *
 * It must never be an empty string: Table::_getExistsConditions() replaces any empty
 * unique-index column with a placeholder that matches no row, which would make the ORM
 * treat every existing translation as new and insert a duplicate key.
 *
 * @var string
 */
    const NEUTRAL_CONTEXT = 'default';

/**
 * Locale.
 *
 * @var string
 */
    protected $_locale;

/**
 * Foreign key.
 *
 * @var string
 */
    protected $_foreignKey;

/**
 * Foreign model column name.
 *
 * @var string
 */
    protected $_foreignModelColumn;

/**
 * Association alias for the I18n.
 *
 * @var string
 */
    protected $_associationAlias = 'I18n';

/**
 * I18n association property name.
 *
 * @var string
 */
    protected $_propertyName = '__i18n__';

/**
 * Locale list.
 *
 * @var array
 */
    protected static $_localeList = [];

/**
 * Loaded context variants, memoized per request as [locale][field][context] => content.
 *
 * Keyed by locale because I18n::locale() can change mid request while this behavior
 * instance lives on the TableRegistry singleton for the whole request.
 *
 * @var array
 */
    private $_variants = [];

/**
 * Default config.
 * These are merged with user-provided configuration when the behavior is used.
 *
 * @var array
 */
    protected $_defaultConfig = [
        'implementedFinders' => [
            'translations' => 'findTranslations'
        ],
        'implementedMethods' => [
            'locale' => 'locale',
            'translatedField' => 'translatedField',
            'translationTable' => 'translationTable'
        ],
        'defaultLocale' => null,
        'fields' => [],
        'translationTable' => null,
        'polymorphic' => false,
        'allowEmptyTranslations' => false,
        'foreignKey' => null,
        'foreignModelColumn' => null,
        'contextPrecedence' => [],
        'contextEagerLoad' => false,
    ];


/**
 * Constructor
 *
 * @param \Nata\ORM\Table $table The table this registry is attached to
 * @param array $config Configuration
 */
    public function __construct(Table $table, array $config = []) {
        $config += [
            'defaultLocale' => I18n::defaultLocale()
        ];

        $this->config($config);

        parent::__construct($table, $config);
    }

/**
 * Initialize.
 *
 * @param array $config Behavior configuration
 */
    public function afterStartup($event) {
        $this->translationTable();
    }

/**
 * Get translation's \Nata\ORM\Table instance.
 *
 * @return \Nata\ORM\Table Table instance
 */
    public function translationTable() {
        $translationTable = $this->config('translationTable');
        if (!$translationTable) {
            $translationTable = Inflector::camelize($this->_table->table() . '_i18n');
            $this->config('translationTable', $translationTable);
        }

        $this->_table->hasMany($this->_associationAlias, [
            'className' => $translationTable,
            'propertyName' => $this->_propertyName,
            'saveStrategy' => 'append',
            'polymorphic' => $this->config('polymorphic'),
            'foreignKey' => $this->foreignKey(),
            'foreignModel' => $this->foreignModelColumn(),
            'dependent' => true
        ]);

        return TableRegistry::get($translationTable);
    }

/**
 * Translation finder.
 *
 * @param \Nata\ORM\Query $query Query instance
 * @param array $options Finder options
 * @return \Nata\Event\Event Event instance
 */
    public function findTranslations(Query $query, $options) {
        $locales = isset($options['locales']) ? $options['locales'] : [];

        return $query->contain([$this->_associationAlias => function ($q) use ($locales) {
            return $q->where([$this->_associationAlias . '.locale IN' => $locales]);
        }]);
    }

/**
 * Before find prepare query for retrieving translations.
 *
 * @param \Nata\Event\Event $event Event instance
 * @param \Nata\ORM\Query $query Query instance
 * @param array $options Finder options
 * @return \Nata\Event\Event Event instance
 */
    public function beforeFind(Event $event, Query $query, $options) {
        if ($this->_table->hasField('_locale')) {
            return;
        }

        $fields = $this->config('fields');
        $select = $query->clause('select');

        $translationTable = $this->translationTable();
        $tableName = $this->_table->table();
        $allFields = in_array($query->aliasField('*', $tableName), $select) || in_array('*', $select);

        foreach ($fields as $field) {
            $aliasedField = $query->aliasField($field, $tableName);
            if ($allFields || in_array($field, $select) || in_array($aliasedField, $select)) {
                $subQuery = $this->_buildFieldSubQuery($translationTable, $field, $query);
                $select[$field] = $subQuery;

                $key = array_search($field, $select);
                if ($key === false) {
                    $key = array_search($aliasedField, $select);
                }

                if ($key !== false) {
                    unset($select[$key]);
                }
            }
        }

        $query->select($select);

        return $event->data('query', $query);
    }

/**
 * Create sub query.
 *
 * @param \Nata\ORM\Table $translationTable Translation table instance
 * @param string $field Field name
 * @return \Nata\ORM\Query
 */
    private function _buildFieldSubQuery($translationTable, $field, $query) {
        $foreignKey = $this->foreignKey();
        $baseLocale = (string)I18n::sourceLocale();
        $defaultLocale = $this->config('defaultLocale');
        $locale = $this->locale();
        $userLocale = (string)I18n::locale();
        $allowEmptyTranslations = $this->config('allowEmptyTranslations');
        $aliasedContent = $translationTable->aliasField('content');
        $aliasedLocale = $translationTable->aliasField('locale');

        $subQuery = $translationTable->find()
            ->select($aliasedContent)
            ->orderByField($aliasedLocale, $this->_getLocalesList())
            ->limit(1);

        $where = $translationTable->aliasField($foreignKey) . ' = ' . $query->aliasField('id', $this->_table->table());
        $where .= " AND " . $translationTable->aliasField('field') . " = '{$field}'";

        // Never let a context variant win the LIMIT 1 and become the canonical value
        if ($translationTable->hasField('context')) {
            $where .= " AND " . $translationTable->aliasField('context') . " = '" . static::NEUTRAL_CONTEXT . "'";
        }

        // Locale conditions
        $whereLocales = [];

        // Main locale
        $whereLocales[$locale] = $aliasedLocale . " = '" . $locale . "'";
        $whereLocales[$defaultLocale] = $aliasedLocale . " = '" . $defaultLocale . "'";

        // Fallback locales
        if (!$allowEmptyTranslations) {
            $whereLocales[$userLocale] = $aliasedLocale . " = '" . $defaultLocale . "'";
            if (str_contains($userLocale, '-')) {
                [$fallbackLocale] = explode('-', $userLocale);
                $whereLocales[$fallbackLocale] = $aliasedLocale . " = '" . $fallbackLocale . "'";
            }
            $whereLocales[$baseLocale] = $aliasedLocale . " = '" . $baseLocale . "'";
        }

        $where .= sprintf(' AND (%s)', implode(' OR ', $whereLocales));
        $where .= " AND TRIM(COALESCE(" . $aliasedContent . ", '')) <> ''";

        $subQuery->where($where);

        return $subQuery;
    }

/**
 * Get list of locales.
 *
 * @return array
 */
    private function _getLocalesList() {
        if (!static::$_localeList) {
            $allowEmptyTranslations = $this->config('allowEmptyTranslations');

            $locale = $this->locale();
            static::$_localeList[$locale] = $locale;

            $defaultLocale = $this->config('defaultLocale');
            static::$_localeList[$defaultLocale] = $defaultLocale;

            $userLocale = (string)I18n::locale();
            static::$_localeList[$userLocale] = $userLocale;

            if (!$allowEmptyTranslations) {
                $locale = (string)I18n::sourceLocale();
                static::$_localeList[$locale] = $locale;
            }
        }
        return static::$_localeList;
    }

/**
 * Expand locale-keyed arrays on translatable fields into _translations entries.
 *
 * Called at the start of beforeSave(). For each translatable field whose value
 * is an associative array with keys matching the pattern 'pt' or 'pt-PT', each
 * entry is written into the entity's _translations map. The base field is then
 * set to null (pure i18n-table tables) or to the default-locale value (tables
 * that carry a _locale column on the row).
 *
 * If _translations already contains entries for the locale being expanded, the
 * field value is merged in rather than replacing the whole entry, so other
 * fields on that translation object are preserved.
 *
 * @param \Nata\ORM\Entity $entity Entity being saved
 * @return void
 */
    protected function _expandLocaleArrays(Entity $entity): void {
        $fields = $this->config('fields');
        $hasLocaleColumn = $this->_table->hasField('_locale');
        $defaultLocale = $this->config('defaultLocale');
        $allowEmptyTranslations = $this->config('allowEmptyTranslations');

        foreach ($fields as $field) {
            $rawValue = $entity->get($field);
            if (!is_array($rawValue) || empty($rawValue)) {
                continue;
            }

            foreach (array_keys($rawValue) as $localeKey) {
                if (!is_string($localeKey) || !preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', $localeKey)) {
                    continue 2;
                }
            }

            $existingTranslations = $entity->get('_translations') ?? [];
            $className = get_class($entity);

            foreach ($rawValue as $locale => $text) {
                $text = is_string($text) ? $text : '';
                if ($text === '' && !$allowEmptyTranslations) {
                    continue;
                }
                if (!isset($existingTranslations[$locale]) || !($existingTranslations[$locale] instanceof Entity)) {
                    $existingTranslations[$locale] = new $className([], ['markClean' => true]);
                }
                $existingTranslations[$locale]->set($field, $text);
            }

            $entity->set('_translations', $existingTranslations);
            $entity->dirty('_translations', true);

            if ($hasLocaleColumn) {
                $baseText = $rawValue[$defaultLocale] ?? reset($rawValue);
                $entity->set($field, is_string($baseText) && $baseText !== '' ? $baseText : null);
            } else {
                $entity->set($field, null);
            }
        }
    }

/**
 * Before Save event.
 *
 * Automatically expands locale-keyed arrays on translatable fields into proper
 * _translations entries so callers can write:
 *
 *   $entity->set('title', ['pt' => 'Título', 'en' => 'Title']);
 *
 * instead of manually calling $entity->translation($locale)->set($field, $value)
 * for each language. Recognised locale keys: 'pt' or 'pt-PT' format only.
 *
 * On tables with a _locale column the default-locale value is kept on the base
 * field so the row column is never nulled out. On pure i18n-table setups the
 * base field is set to null after expansion (translations are stored separately).
 * Empty string values are skipped unless allowEmptyTranslations is true.
 *
 * @param \Nata\Event\Event $event Event instance
 * @param \Nata\ORM\Entity $entity Entity instance
 * @return \Nata\Event\Event Event instance
 */
    public function beforeSave(Event $event, Entity $entity) {
        $this->_expandLocaleArrays($entity);

        $translationTable = $this->translationTable();

        $locale = $entity->get('_locale');
        if (!$locale && $this->_table->hasField('_locale')) {
            $locale = I18n::locale();
            $entity->set('_locale', $locale);
        }

        $fields = $this->config('fields');
        $translations = $entity->get('_translations');

        if (!$translations) {
            $dirtyProperties = $entity->extract($fields, true);
            if (!$dirtyProperties) {
                return;
            }

            $className = get_class($entity);
            $translations = [I18n::locale() => new $className($dirtyProperties)];
            $entity->set('_translations', $translations);
        }

        $i18n = [];
        $langs = array_keys($translations);
        $polymorphic = $this->config('polymorphic');
        $foreignKey = $this->foreignKey();
        $foreignModelColumn = $this->foreignModelColumn();
        $hasContext = $translationTable->hasField('context');
        foreach ($langs as $lang) {
            if ($lang === $locale) {
                continue;
            }
            $translation = $entity->translation($lang);
            if (!$translation->isDirty()) {
                continue;
            }

            $extraData = $translation->extract($translationTable->schema()->columns(), true);
            foreach ($fields as $field) {
                $content = $translation->get($field);
                if ($content === null && $translation->isNew()) {
                    continue;
                }

                $data = [
                    'locale' => $lang,
                    'field' => $field,
                    'content' => $translation->get($field)
                ];

                // The ORM derives INSERT vs UPDATE from the unique indexes and treats an empty
                // column as missing, so the neutral context has to be written explicitly instead
                // of being left to the column default. It is set before merging $extraData so a
                // stray context carried there cannot retag this row as a variant.
                if ($hasContext) {
                    $data['context'] = static::NEUTRAL_CONTEXT;
                }

                $data += $extraData;

                if ($polymorphic) {
                    $data[$foreignKey] = $entity->get('id');
                    $data[$foreignModelColumn] = $this->_table->registryAlias();
                }

                $i18n[] = $data;
            }
        }

        if (empty($i18n)) {
           return;
        }

        $entity->set($this->_propertyName, $i18n);

        // Force inclusion of associated I18n model
        $associated = $event->data('options.associated');
        if (is_array($associated) && (!isset($associated[$this->_associationAlias]) || !in_array($this->_associationAlias, $associated))) {
            $associated[$this->_associationAlias] = ['associated' => false];
        }

        $event->data('options.associated', $associated);
    }

/**
 * After Save event.
 *
 * @param \Nata\Event\Event $event Event instance
 * @param \Nata\ORM\Entity $entity Entity instance
 * @return \Nata\Event\Event Event instance
 */
    public function afterSave(Event $event, Entity $entity) {
        $entity->unsetProperty($this->_propertyName);
    }

/**
 * Get/set locale.
 *
 * @param string $locale Locale
 * @return \Nata\ORM\Table|string
 */
    public function locale($locale = null) {
        if ($locale === null) {
            if ($this->_locale === null) {
                $this->_locale = I18n::locale();
            }
            return $this->_locale;
        }
        $this->_locale = $locale;
        return $this->_table;
    }

/**
 * Get foreign key.
 *
 * @return string Foreign key name
 */
    public function foreignKey() {
        if ($this->_foreignKey === null) {
            $foreignKey = $this->config('foreignKey');
            if ($foreignKey === null) {
                if ($this->config('polymorphic')) {
                    $foreignKey = 'foreign_key';
                } else {
                    $foreignKey = Inflector::singularize($this->_table->table()) . '_id';
                }
            }
            $this->_foreignKey = $foreignKey;
        }
        return $this->_foreignKey;
    }

/**
 * Get foreign model column name.
 *
 * @return string Foreign model column name
 */
    public function foreignModelColumn() {
        $this->_foreignModelColumn = $this->config('foreignModelColumn');
        if ($this->_foreignModelColumn === null) {
            $this->_foreignModelColumn = 'foreign_model';
        }
        return $this->_foreignModelColumn;
    }

/**
 * Resolve a context variant of a translated field.
 *
 * Context variants let one field carry more than one translation per locale, chosen at
 * render time from facts the caller knows: the gender of the person a role name refers to,
 * the formality of a channel, the space available. Variants are keyed by an opaque
 * namespaced selector ("gender:female"), so the set of variants belongs to the stored data
 * rather than to the schema, and a translator can add a form the developer never anticipated.
 *
 * Returns null whenever nothing matches, so the caller keeps the canonical translation the
 * main query already loaded. Unknown facts are dropped by _normalizeContext() and therefore
 * resolve to the canonical value with no special casing at the call site.
 *
 * @param \Nata\ORM\Entity $entity Entity owning the translation.
 * @param string $field Translated field name.
 * @param string|array $context Context bag: an "axis:value" token, a list of tokens, or an axis => value map.
 * @return string|null Variant content, or null when there is no matching variant.
 */
    public function translatedField(Entity $entity, $field, $context = []) {
        $translationTable = $this->translationTable();
        if (!$translationTable->hasField('context')) {
            return null;
        }

        if (!in_array($field, (array)$this->config('fields'), true)) {
            return null;
        }

        $entityId = $entity->get('id');
        if (empty($entityId)) {
            return null;
        }

        $normalizedContext = $this->_normalizeContext($context);
        if (!$normalizedContext) {
            return null;
        }

        $variants = $this->_loadVariants($field, $entityId);
        if (empty($variants[$entityId])) {
            return null;
        }

        foreach ($this->_contextKeys($normalizedContext) as $contextKey) {
            if (isset($variants[$entityId][$contextKey])) {
                return $variants[$entityId][$contextKey];
            }
        }

        return null;
    }

/**
 * Normalize a context argument into an axis => value map.
 *
 * Accepts a single "axis:value" token, a list of such tokens, or an axis => value map, so a
 * literal reads compactly while a dynamic value can be passed without building a string.
 * Axes resolving to an empty value are dropped: an unknown fact must fall through to the
 * canonical translation rather than match nothing.
 *
 * @param string|array $context Context bag in any accepted shape.
 * @return array Axis => value map.
 */
    private function _normalizeContext($context) {
        if (is_string($context)) {
            $context = [$context];
        }

        $normalized = [];
        foreach ((array)$context as $axis => $value) {
            if (is_int($axis)) {
                if (!is_string($value) || !str_contains($value, ':')) {
                    continue;
                }
                [$axis, $value] = explode(':', $value, 2);
            }

            $axis = trim((string)$axis);
            $value = trim((string)$value);
            if ($axis === '' || $value === '') {
                continue;
            }

            // ':' separates axis from value and '|' joins axes, so neither can appear inside one
            if (str_contains($axis, ':') || str_contains($axis, '|') || str_contains($value, ':') || str_contains($value, '|')) {
                continue;
            }

            $normalized[$axis] = $value;
        }

        return $normalized;
    }

/**
 * Build the context keys to look for, most specific first.
 *
 * Axes are ordered by the configured precedence before being joined, so a composite key
 * always has the same shape as the stored one no matter what order the caller filled the
 * context bag in. Combinations are tried before single axes because a variant written for
 * two axes at once is more specific than either of them alone.
 *
 * @param array $normalizedContext Axis => value map.
 * @return array Context keys in lookup order.
 */
    private function _contextKeys(array $normalizedContext) {
        $precedence = (array)$this->config('contextPrecedence');
        $axes = array_keys($normalizedContext);

        $orderedAxes = array_values(array_intersect($precedence, $axes));
        foreach ($axes as $axis) {
            if (!in_array($axis, $orderedAxes, true)) {
                $orderedAxes[] = $axis;
            }
        }

        $singleKeys = [];
        foreach ($orderedAxes as $axis) {
            $singleKeys[] = $axis . ':' . $normalizedContext[$axis];
        }

        $keys = [];
        if (count($singleKeys) > 1) {
            $keys[] = implode('|', $singleKeys);
        }

        return array_merge($keys, $singleKeys);
    }

/**
 * Load the context variants of a field, memoized for the request.
 *
 * Variant rows are sparse - one exists only where somebody authored it - so a small table
 * can afford to load its whole variant set once and serve every entity from memory. That is
 * opt-in through the contextEagerLoad config: on a large table the same strategy would pull
 * the entire set, so the default queries a single entity instead.
 *
 * @param string $field Translated field name.
 * @param mixed $entityId Primary key of the entity being resolved.
 * @return array Map of entity id => [context key => content].
 */
    private function _loadVariants($field, $entityId) {
        $locale = (string)I18n::locale();
        $eagerLoad = (bool)$this->config('contextEagerLoad');
        $memoKey = $eagerLoad ? '*' : $entityId;

        if (isset($this->_variants[$locale][$field][$memoKey])) {
            return $this->_variants[$locale][$field][$memoKey];
        }

        $translationTable = $this->translationTable();
        $foreignKey = $this->foreignKey();

        $conditions = [
            'field' => $field,
            'context <>' => static::NEUTRAL_CONTEXT,
            'locale IN' => $this->_variantLocales()
        ];

        // A polymorphic translation table serves several models, so the owning model has to
        // be part of the lookup or another model's rows leak in
        if ($this->config('polymorphic')) {
            $conditions[$this->foreignModelColumn()] = $this->_table->registryAlias();
        }

        if (!$eagerLoad) {
            $conditions[$foreignKey] = $entityId;
        }

        $query = $translationTable->find()
            ->select([$foreignKey, 'context', 'content'])
            ->where($conditions)
            ->orderByField($translationTable->aliasField('locale'), $this->_variantLocales());

        $variants = [];
        foreach ($query as $row) {
            $content = $row->get('content');
            if ($content === null || trim((string)$content) === '') {
                continue;
            }

            $rowId = $row->get($foreignKey);
            $rowContext = (string)$row->get('context');

            // Rows arrive in locale preference order, so the first hit for a context wins
            if (!isset($variants[$rowId][$rowContext])) {
                $variants[$rowId][$rowContext] = $content;
            }
        }

        $this->_variants[$locale][$field][$memoKey] = $variants;

        return $variants;
    }

/**
 * Build the locale preference list used to resolve variants, most preferred first.
 *
 * Deliberately independent of _getLocalesList() and locale(): both memoize - the first into
 * a process-wide static - and go stale when I18n::locale() is changed mid request.
 *
 * @return array Locale codes, most preferred first.
 */
    private function _variantLocales() {
        $locales = [];

        $userLocale = (string)I18n::locale();
        if ($userLocale !== '') {
            $locales[] = $userLocale;
        }

        $defaultLocale = (string)$this->config('defaultLocale');
        if ($defaultLocale !== '') {
            $locales[] = $defaultLocale;
        }

        if (str_contains($userLocale, '-')) {
            [$baseLanguage] = explode('-', $userLocale);
            $locales[] = $baseLanguage;
        }

        $sourceLocale = (string)I18n::sourceLocale();
        if ($sourceLocale !== '') {
            $locales[] = $sourceLocale;
        }

        return array_values(array_unique($locales));
    }
}
