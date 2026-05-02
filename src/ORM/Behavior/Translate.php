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
            'translationField' => 'translationField',
            'translationTable' => 'translationTable'
        ],
        'defaultLocale' => null,
        'fields' => [],
        'translationTable' => null,
        'polymorphic' => false,
        'allowEmptyTranslations' => false,
        'foreignKey' => null,
        'foreignModelColumn' => null,
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
 * Before Save event.
 *
 * @param \Nata\Event\Event $event Event instance
 * @param \Nata\ORM\Entity $entity Entity instance
 * @return \Nata\Event\Event Event instance
 */
    public function beforeSave(Event $event, Entity $entity) {
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
                ] + $extraData;

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
}
