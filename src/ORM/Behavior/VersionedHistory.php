<?php
/**
 * NataPHP Framework
 *
 * @copyright     Copyright (c) Sérgio Dinis Lopes (http://nataphp.com)
 * @link          http://nataphp.com NataPHP Project
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Nata\ORM\Behavior;

use Nata\Core\ConfigAwareTrait;
use Nata\Core\Configure;
use Nata\Core\Registry;
use Nata\Event\Event;
use Nata\I18n\Time;
use Nata\ORM\Behavior;
use Nata\ORM\Entity;
use Nata\ORM\ResultSet;
use Nata\ORM\Table;
use Nata\ORM\TableRegistry;
use Nata\Routing\Router;
use Throwable;

/**
 * VersionedHistory Behavior
 *
 * Stores full snapshots of entity state on each save, enabling point-in-time restore.
 * Supports main entity fields, associations (belongsToMany, hasMany), and translations
 * when the Translate behavior is present.
 *
 * ## Configuration
 *
 * - `trackedAssociations`: List of association aliases, or `'*'` to track all (excludes `ignoredAssociations`).
 * - `ignoredAssociations`: Excluded when `trackedAssociations` is `'*'`.
 * - `trackedAssociationsConfig`: Per-association options; `depth` can be:
 *   - `ids` / `shallow`: Store only linked IDs (BelongsToMany, HasMany).
 *   - `deep`: Store full entity data (HasMany, HasOne).
 * - `maxVersionsPerEntity`: Prune older versions automatically.
 *
 * ## Example
 *
 *   $this->addBehavior('VersionedHistory', [
 *       'ignoredFields' => ['updated'],
 *       'trackedAssociations' => '*',
 *       'ignoredAssociations' => ['Author', 'References'],
 *       'trackedAssociationsConfig' => [
 *           'Promoters' => ['depth' => 'ids'],
 *           'Sessions'  => ['depth' => 'ids'],
 *       ],
 *       'maxVersionsPerEntity' => 50,
 *   ]);
 */
class VersionedHistory extends Behavior {

    use ConfigAwareTrait;

/**
 * Default configuration.
 *
 * ## Configuration Options
 * - `table`: The table name to store the version history.
 * - `ignoredFields`: The fields to ignore when building the snapshot.
 * - `trackedAssociations`: The associations to track when building the snapshot.
 * - `ignoredAssociations`: The associations to ignore when building the snapshot.
 * - `trackedAssociationsConfig`: The configuration for the associations to track when building the snapshot.
 * - `includeTranslations`: Whether to include translations in the snapshot.
 * - `maxVersionsPerEntity`: The maximum number of versions to store for an entity.
 * - `changedBy`: Entity or null for who made the change (fallback: Registry::get('authUser')).
 * - `restoreIgnoredFields`: The fields to ignore when restoring the entity.
 *
 * @var array
 */
    protected $_defaultConfig = [
        'implementedMethods' => [
            'restoreVersion' => 'restoreVersion',
            'getVersionHistory' => 'getVersionHistory',
        ],
        'table' => 'EntityVersions',
        'ignoredFields' => ['updated', 'modified'],
        'trackedAssociations' => [],
        'ignoredAssociations' => [],
        'trackedAssociationsConfig' => [],
        'includeTranslations' => true,
        'maxVersionsPerEntity' => null,
        'changedBy' => null,
        'restoreIgnoredFields' => ['id', 'created'],
    ];

/**
 * Versions table instance.
 *
 * @var \Nata\ORM\Table|null
 */
    protected $_versionsTable;

/**
 * Changed by entity instance.
 *
 * @var \Nata\ORM\Entity|null
 */
    protected $_changedBy;

/**
 * Before save callback.
 *
 * @param \Nata\Event\Event $event Event object.
 * @param \Nata\ORM\Entity $entity Entity being saved.
 * @param array $options Save options.
 * @return \Nata\Event\Event|null
 *
 * @internal Used by Model.beforeSave. Not intended for direct invocation.
 */
    public function beforeSave(Event $event, Entity $entity, array $options = []): ?Event {
        return $event;
    }

/**
 * After save callback.
 *
 * @param \Nata\Event\Event $event Event object.
 * @param \Nata\ORM\Entity $entity Saved entity.
 * @param array $options Save options.
 * @return void
 *
 * @internal Used by Model.afterSave. Not intended for direct invocation.
 */
    public function afterSave(Event $event, Entity $entity, array $options = []): void {
        $options = $options ?? $event->data('options') ?? [];
        $vh = $options['versionedHistory'] ?? [];
        if (!empty($vh['skipSnapshot'] ?? null)) {
            return;
        }
        $entityId = $entity->get('id');
        if (empty($entityId)) {
            return;
        }

        $foreignModel = $entity->source() ?: $this->_table->registryAlias();
        if (empty($foreignModel)) {
            return;
        }

        $snapshot = $this->_buildSnapshot($entity);
        $this->_persistVersion($entity, $snapshot, $options);
    }

/**
 * Restore an entity to a previous version.
 *
 * @param \Nata\ORM\Entity|int $entityOrId Entity instance or entity ID.
 * @param int $versionId Primary key of the entity_versions row to restore from.
 * @return \Nata\ORM\Entity|false Restored entity on success, false on failure.
 */
    public function restoreVersion(Entity|int $entityOrId, int $versionId): Entity|false {
        $table = $this->_versionsTable();
        $versionEntity = $table->get($versionId);

        if (!$versionEntity || empty($versionEntity->snapshot)) {
            return false;
        }

        $foreignModel = $this->_table->registryAlias();
        if ($versionEntity->foreign_model !== $foreignModel) {
            return false;
        }

        $entityId = is_object($entityOrId) ? $entityOrId->get('id') : (int) $entityOrId;
        if ($versionEntity->foreign_id != $entityId) {
            return false;
        }

        $entity = is_object($entityOrId) ? $entityOrId : $this->_table->get($entityId);
        $snapshot = is_array($versionEntity->snapshot) ? $versionEntity->snapshot : json_decode($versionEntity->snapshot, true);
        if (!is_array($snapshot)) {
            return false;
        }

        $ignored = $this->config('restoreIgnoredFields');
        foreach ($ignored as $field) {
            unset($snapshot[$field]);
        }

        $i18n = $snapshot['_i18n'] ?? null;
        $associations = $snapshot['_associations'] ?? null;
        unset($snapshot['_i18n'], $snapshot['_associations']);

        $entity->set($snapshot);

        if (!empty($associations)) {
            $this->_restoreAssociations($entity, $associations);
        }
        if (is_array($i18n)) {
            $this->_restoreTranslations($entity, $i18n);
        }

        return $this->_table->save($entity, ['versionedHistory' => ['skipSnapshot' => true]]) ?: false;
    }

/**
 * Get version history for an entity.
 *
 * @param int $entityId Primary key of the entity.
 * @param array $options Optional query options.
 * @return \Nata\ORM\ResultSet
 */
    public function getVersionHistory(int $entityId, array $options = []): ResultSet {
        $table = $this->_versionsTable();
        $foreignModel = $this->_table->registryAlias();

        $query = $table->find()
            ->where([
                'foreign_model' => $foreignModel,
                'foreign_id' => $entityId,
            ])
            ->order(['version' => 'DESC']);

        if (!empty($options['limit'])) {
            $query->limit($options['limit']);
        }
        if (!empty($options['order'])) {
            $query->order($options['order']);
        }
        if (isset($options['changeType'])) {
            $types = (array) $options['changeType'];
            $query->andWhere(['change_type IN' => $types]);
        }

        return $query->all();
    }

/**
 * Build snapshot array for the entity.
 *
 * @param \Nata\ORM\Entity $entity Entity to snapshot.
 * @return array Snapshot structure.
 */
    protected function _buildSnapshot(Entity $entity): array {
        $schema = $this->_table->schema();
        $columns = $schema->columns();
        $ignoredFields = $this->config('ignoredFields');
        $columns = array_diff($columns, $ignoredFields);

        $data = $entity->extract($columns);
        foreach ($data as $key => $value) {
            if ($value instanceof Time) {
                $data[$key] = $value->format('Y-m-d H:i:s');
            }
        }

        if ($this->config('includeTranslations') && $this->_table->behaviors()->has('Translate')) {
            $data['_i18n'] = $this->_extractTranslations($entity);
        }

        $associationsData = $this->_extractAssociations($entity);
        if (!empty($associationsData)) {
            $data['_associations'] = $associationsData;
        }

        return $data;
    }

/**
 * Extract translations from entity.
 *
 * @param \Nata\ORM\Entity $entity Entity.
 * @return array
 */
    protected function _extractTranslations(Entity $entity): array {
        $translateBehavior = $this->_table->behaviors()->get('Translate');
        $translationTable = $translateBehavior->translationTable();
        $foreignKey = $translateBehavior->foreignKey();

        $entityId = $entity->get('id');
        if (empty($entityId)) {
            return [];
        }

        $query = $translationTable->find()
            ->where([$foreignKey => $entityId]);

        // Snapshot canonical translations only. The snapshot is keyed by locale and field, so
        // a context variant would collapse onto the neutral value and be restored as canonical
        if ($translationTable->hasField('context')) {
            $query->andWhere(['context' => Translate::NEUTRAL_CONTEXT]);
        }

        $rows = $query->all();

        $result = [];
        foreach ($rows as $row) {
            $locale = $row->get('locale');
            $field = $row->get('field');
            $content = $row->get('content');
            if ($locale && $field) {
                if (!isset($result[$locale])) {
                    $result[$locale] = [];
                }
                $result[$locale][$field] = $content;
            }
        }
        return $result;
    }

/**
 * Extract associations for snapshot.
 *
 * @param \Nata\ORM\Entity $entity Entity.
 * @return array
 */
    protected function _extractAssociations(Entity $entity): array {
        $result = [];
        $associations = $this->_table->associations();
        $aliases = $associations->get();

        foreach ($aliases as $alias) {
            if (!$this->_shouldTrackAssociation($alias)) {
                continue;
            }
            try {
                $association = $associations->get($alias);
                $data = $this->_extractAssociationSnapshot($entity, $alias, $association);
                if ($data !== null) {
                    if (is_array($data) && isset($data['_sort_order'])) {
                        $result[$alias . '.sort_order'] = $data['_sort_order'];
                        $data = $data['_ids'] ?? $data;
                        unset($data['_sort_order']);
                    }
                    $result[$alias] = $data;
                }
            } catch (Throwable $e) {
                continue;
            }
        }
        return $result;
    }

/**
 * Determine whether an association should be tracked.
 *
 * @param string $associationName Association alias.
 * @return bool
 */
    protected function _shouldTrackAssociation(string $associationName): bool {
        $tracked = $this->config('trackedAssociations');
        $ignored = $this->config('ignoredAssociations');

        if (empty($tracked)) {
            return false;
        }

        $trackAll = $tracked === '*' || (is_array($tracked) && in_array('*', $tracked));
        if ($trackAll) {
            return !in_array($associationName, $ignored);
        }

        return in_array($associationName, (array) $tracked);
    }

/**
 * Extract association data for snapshot.
 *
 * @param \Nata\ORM\Entity $entity Source entity.
 * @param string $associationName Association alias.
 * @param \Nata\ORM\Association $association Association instance.
 * @return array|null
 */
    protected function _extractAssociationSnapshot(Entity $entity, string $associationName, $association): ?array {
        $type = $association->getType();
        $config = $this->config('trackedAssociationsConfig')[$associationName] ?? [];
        $depth = $config['depth'] ?? 'ids';

        if ($type === 'BelongsToMany') {
            return $this->_extractBelongsToManySnapshot($entity, $association, $config);
        }

        if ($type === 'HasMany') {
            return $this->_extractHasManySnapshot($entity, $association, $depth);
        }

        return null;
    }

/**
 * Extract BelongsToMany association snapshot.
 *
 * @param \Nata\ORM\Entity $entity Source entity.
 * @param \Nata\ORM\Association\BelongsToMany $association Association.
 * @param array $config Config.
 * @return array|null
 */
    protected function _extractBelongsToManySnapshot(Entity $entity, $association, array $config): ?array {
        $junction = $association->junction();
        $foreignKey = $association->foreignKey();
        $targetForeignKey = $association->targetForeignKey();
        $entityId = $entity->get('id');

        if (empty($entityId)) {
            return null;
        }

        $select = [$targetForeignKey];
        if ($junction->hasField('sort_order')) {
            $select[] = 'sort_order';
        }
        $query = $junction->find()
            ->select($select)
            ->where([$foreignKey => $entityId]);
        if ($junction->hasField('sort_order')) {
            $query->order(['sort_order' => 'ASC']);
        }

        $rows = $query->all();
        $ids = [];
        $sortOrder = [];
        $idx = 0;
        foreach ($rows as $row) {
            $targetId = $row->get($targetForeignKey);
            $ids[] = $targetId;
            $sortOrder[$targetId] = $junction->hasField('sort_order') ? ($row->get('sort_order') ?? $idx) : $idx;
            $idx++;
        }

        if (count($ids) <= 1 || !$junction->hasField('sort_order')) {
            return $ids;
        }
        return ['_ids' => $ids, '_sort_order' => $sortOrder];
    }

/**
 * Extract HasMany association snapshot.
 *
 * @param \Nata\ORM\Entity $entity Source entity.
 * @param \Nata\ORM\Association\HasMany $association Association.
 * @param string $depth Depth (ids or deep).
 * @return array|null
 */
    protected function _extractHasManySnapshot(Entity $entity, $association, string $depth): ?array {
        $target = $association->target();
        $foreignKey = $association->foreignKey();
        $entityId = $entity->get('id');

        if (empty($entityId)) {
            return null;
        }

        $rows = $target->find()
            ->where([$foreignKey => $entityId])
            ->all();

        if ($depth === 'deep') {
            $result = [];
            foreach ($rows as $row) {
                $result[] = $row->toArray();
            }
            return $result;
        }

        $ids = [];
        foreach ($rows as $row) {
            $ids[] = $row->get('id');
        }
        return $ids;
    }

/**
 * Restore translations from snapshot.
 *
 * @param \Nata\ORM\Entity $entity Entity.
 * @param array $i18n Snapshot _i18n: [locale => [field => content]].
 * @return void
 */
    protected function _restoreTranslations(Entity $entity, array $i18n): void {
        if (!$this->_table->behaviors()->has('Translate')) {
            return;
        }
        $translateBehavior = $this->_table->behaviors()->get('Translate');
        $translationTable = $translateBehavior->translationTable();
        $foreignKey = $translateBehavior->foreignKey();
        $entityId = $entity->get('id');
        if (empty($entityId)) {
            return;
        }
        $hasContext = $translationTable->hasField('context');

        // Context variants are outside the snapshot, so they must survive the wipe rather than
        // be deleted as collateral of restoring the canonical translations
        $deleteConditions = [$foreignKey => $entityId];
        if ($hasContext) {
            $deleteConditions['context'] = Translate::NEUTRAL_CONTEXT;
        }

        $translationTable->deleteAll($deleteConditions);
        foreach ($i18n as $locale => $fields) {
            if (!is_array($fields)) {
                continue;
            }
            foreach ($fields as $field => $content) {
                $row = [
                    $foreignKey => $entityId,
                    'locale' => $locale,
                    'field' => $field,
                    'content' => $content,
                ];

                // Written straight to the table rather than through Translate::beforeSave, so
                // the neutral context has to be set here for the unique index to match
                if ($hasContext) {
                    $row['context'] = Translate::NEUTRAL_CONTEXT;
                }

                $translationTable->save($translationTable->newEntity($row));
            }
        }
    }

/**
 * Restore associations from snapshot.
 *
 * @param \Nata\ORM\Entity $entity Entity.
 * @param array $associations Snapshot associations.
 * @return void
 */
    protected function _restoreAssociations(Entity $entity, array $associations): void {

        foreach ($associations as $alias => $data) {
            if (strpos($alias, '.') !== false) {
                continue;
            }
            if (!$this->_table->associations()->has($alias)) {
                continue;
            }
            try {
                $association = $this->_table->associations()->get($alias);
                if ($association->getType() === 'BelongsToMany') {
                    $sortKey = $alias . '.sort_order';
                    $sortOrder = $associations[$sortKey] ?? [];
                    $this->_restoreBelongsToMany($entity, $association, $data, $sortOrder);
                }
            } catch (Throwable $e) {
                continue;
            }
        }
    }

/**
 * Restore BelongsToMany association.
 *
 * @param \Nata\ORM\Entity $entity Entity.
 * @param \Nata\ORM\Association\BelongsToMany $association Association.
 * @param mixed $data Snapshot data (ids or array with _ids and _sort_order).
 * @param array $sortOrder Sort order map.
 * @return void
 */
    protected function _restoreBelongsToMany(Entity $entity, $association, $data, array $sortOrder = []): void {
        $ids = is_array($data) && isset($data['_ids']) ? $data['_ids'] : (array) $data;
        if (empty($ids) && empty($sortOrder)) {
            $ids = [];
        } elseif (empty($sortOrder) && !empty($ids)) {
            $sortOrder = array_combine($ids, array_keys($ids));
        }

        $junction = $association->junction();
        $foreignKey = $association->foreignKey();
        $targetForeignKey = $association->targetForeignKey();
        $entityId = $entity->get('id');

        $junction->deleteAll([$foreignKey => $entityId]);

        foreach ($ids as $idx => $targetId) {
            $row = [
                $foreignKey => $entityId,
                $targetForeignKey => $targetId,
            ];
            if ($junction->hasField('sort_order')) {
                $row['sort_order'] = $sortOrder[$targetId] ?? $idx;
            }
            $junction->save($junction->newEntity($row));
        }
    }

/**
 * Persist a version row to entity_versions.
 *
 * @param \Nata\ORM\Entity $entity Saved entity.
 * @param array $snapshot Snapshot array.
 * @param array $options Save options (may contain versionedHistory overrides).
 * @return \App\Model\Entity\EntityVersion|null
 */
    protected function _persistVersion(Entity $entity, array $snapshot, array $options = []): ?\App\Model\Entity\EntityVersion {
        $table = $this->_versionsTable();
        $foreignModel = $entity->source() ?: $this->_table->registryAlias();
        $foreignId = (int) $entity->get('id');

        $maxVersion = $table->find()
            ->select(['version'])
            ->andWhere([
                'foreign_model' => $foreignModel,
                'foreign_id' => $foreignId,
            ])
            ->order(['version' => 'DESC'])
            ->first();

        $version = $maxVersion ? ((int) $maxVersion->version) + 1 : 1;
        $changeType = $maxVersion ? 'updated' : 'created';
        $changedBy = $this->_getChangedBy($options);
        $audit = $this->_getAuditContext($options);

        $versionEntity = $table->newEntity([
            'foreign_model' => $foreignModel,
            'foreign_id' => $foreignId,
            'version' => $version,
            'snapshot' => json_encode($snapshot),
            'url' => $audit['url'],
            'referer' => $audit['referer'],
            'ip_address' => $audit['ip_address'],
            'env' => $audit['env'],
            'change_type' => $changeType,
            'changed_by_model' => $changedBy?->source(),
            'changed_by_id' => $changedBy?->get('id'),
            'changed_at' => Time::now()->format('Y-m-d H:i:s'),
            'source' => $audit['source'],
        ]);

        $saved = $table->save($versionEntity);
        if ($saved && $this->config('maxVersionsPerEntity')) {
            $this->_pruneIfNeeded($foreignModel, $foreignId);
        }
        return $saved;
    }

/**
 * Prune older versions when maxVersionsPerEntity is set.
 *
 * @param string $foreignModel Foreign model.
 * @param int $foreignId Foreign ID.
 * @return int
 */
    protected function _pruneIfNeeded(string $foreignModel, int $foreignId): int {
        $max = (int) $this->config('maxVersionsPerEntity');
        if ($max <= 0) {
            return 0;
        }

        $table = $this->_versionsTable();
        $toDelete = $table->find()
            ->select(['id'])
            ->where([
                'foreign_model' => $foreignModel,
                'foreign_id' => $foreignId,
            ])
            ->order(['version' => 'DESC'])
            ->offset($max)
            ->all();

        $ids = [];
        foreach ($toDelete as $row) {
            $ids[] = $row->id;
        }
        if (empty($ids)) {
            return 0;
        }
        return $table->deleteAll(['id IN' => $ids]);
    }

/**
 * Get changed by entity.
 *
 * @param array $options Save options (may contain versionedHistory.changedBy override).
 * @return \Nata\ORM\Entity|null
 */
    protected function _getChangedBy(array $options = []): ?Entity {
        $vh = $options['versionedHistory'] ?? [];
        if (isset($vh['changedBy'])) {
            return $vh['changedBy'];
        }
        if ($this->_changedBy === null) {
            $this->_changedBy = $this->config('changedBy') ?? Registry::get('authUser');
        }
        return $this->_changedBy;
    }

/**
 * Get audit context (url, referer, ip_address, env, source).
 *
 * @param array $options Save options (may contain versionedHistory overrides).
 * @return array{url: ?string, referer: ?string, ip_address: ?string, env: ?string, source: string}
 */
    protected function _getAuditContext(array $options = []): array {
        $vh = $options['versionedHistory'] ?? [];
        $request = Router::getRequest(true);

        $url = $vh['url'] ?? null;
        $referer = $vh['referer'] ?? null;
        $ipAddress = $vh['ip_address'] ?? null;
        $env = $vh['env'] ?? env('APP_ENV') ?? Configure::read('App.env') ?? null;
        $source = $vh['source'] ?? null;

        if ($request) {
            if ($url === null) {
                $url = $request->here();
            }
            if ($referer === null) {
                $ref = $request->referer();
                $referer = ($ref !== null && $ref !== '/') ? $ref : null;
            }
            if ($ipAddress === null) {
                $ipAddress = $request->clientIp() ?: null;
            }
        }

        if ($source === null) {
            $source = (php_sapi_name() === 'cli') ? 'cli' : 'http';
        }

        return [
            'url' => $url ? (string) $url : null,
            'referer' => $referer ? (string) $referer : null,
            'ip_address' => $ipAddress ? (string) $ipAddress : null,
            'env' => $env ? (string) $env : null,
            'source' => (string) $source,
        ];
    }

/**
 * Get versions table.
 *
 * @return Table
 */
    protected function _versionsTable(): Table {
        if ($this->_versionsTable === null) {
            $this->_versionsTable = TableRegistry::get($this->config('table'));
        }
        return $this->_versionsTable;
    }

}
