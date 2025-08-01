<?php

namespace SilverStripe\Snapshots\Migration;

use Exception;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Snapshots\SnapshotEvent;
use SilverStripe\Snapshots\SnapshotItem;
use SilverStripe\Versioned\Versioned;

/**
 * Migration regular version records to snapshot records
 */
class MigrationService
{

    use Injectable;

    private string $snapshotsTable;

    private string $itemsTable;

    /**
     * In-memory cache
     *
     * @var array|null
     */
    private ?array $classMap;

    private int $baseID = 0;

    private ?string $baseClassSubquery;

    /**
     * MigrationService constructor.
     */
    public function __construct()
    {
        $schema = DataObject::getSchema();
        $this->snapshotsTable = $schema->baseDataTable(Snapshot::class);
        $this->itemsTable = $schema->baseDataTable(SnapshotItem::class);
    }

    public function migrate(string $baseClass): int
    {
        $sng = DataObject::singleton($baseClass);
        $baseTable = $sng->baseTable();
        $versionsTable = $baseTable . '_Versions';
        $rows = $this->migrateSnapshots($versionsTable);
        $rows += $this->migrateItems($versionsTable);
        $this->baseID = (int) DB::query("SELECT MAX(\"ID\") FROM \"$this->snapshotsTable\"")->value();

        return $rows;
    }

    /**
     * For objects that have explicitly opted into relation tracking, we need to provide
     * a placeholder SnapshotItem that they can refer to (even if it's orphaned),
     * because implicit changes (checkboxes, elemental editor) don't necessarily
     * create a new version for the owner
     *
     * @throws ReflectionException
     * @throws ValidationException
     * @throws Exception
     */
    public function seedRelationTracking(): void
    {
        $classes = ClassInfo::subclassesFor(DataObject::class, false);

        foreach ($classes as $class) {
            $tracking = Config::forClass($class)->uninherited('snapshot_relation_tracking');

            if (!$tracking) {
                continue;
            }

            foreach ($class::get() as $record) {
                SnapshotItem::create()
                    ->hydrateFromDataObject($record)
                    ->write();
            }
        }
    }

    /**
     * @return array
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    public function getClassesToMigrate(): array
    {
        $classMap = $this->getClassMap();
        $classMap = array_values($classMap);

        return array_unique($classMap);
    }

    /**
     * Restart the task
     *
     * @return void
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    public function setup(): void
    {
        DB::query("DELETE FROM \"$this->snapshotsTable\"");
        DB::query("DELETE FROM \"$this->itemsTable\"");
        $eventTable = DataObject::getSchema()->baseDataTable(SnapshotEvent::class);
        DB::query("DELETE FROM \"$eventTable\"");
        $this->createTemporaryTable();
        $this->baseClassSubquery = <<<'SQL'
                    (
                        SELECT "BaseClassName"
                            FROM "__ClassNameLookup"
                            WHERE "ObjectClassName" = "ClassName"
                            LIMIT 1
                    )
SQL;
    }

    public function tearDown(): void
    {
        $this->removeTemporaryTable();
    }

    private function migrateSnapshots(string $versionsTable): int
    {
        DB::query(
            "INSERT INTO \"$this->snapshotsTable\"
            (
                \"ID\",
                \"Created\",
                \"LastEdited\",
                \"OriginHash\",
                \"AuthorID\",
                \"OriginID\",
                \"OriginClass\"
            )
            (
                SELECT
                    \"ID\" + $this->baseID,
                    \"Created\",
                    \"LastEdited\",
                    MD5(CONCAT($this->baseClassSubquery, ':', \"RecordID\")),
                    \"AuthorID\",
                    \"RecordID\",
                    $this->baseClassSubquery
                FROM
                    \"$versionsTable\"
                WHERE
                    \"WasDeleted\" = 0
                ORDER BY \"ID\" ASC
            )
            "
        );

        return DB::affected_rows();
    }

    private function migrateItems(string $versionsTable): int
    {
        DB::query(
            "INSERT INTO \"$this->itemsTable\"
            (
                \"Created\",
                \"LastEdited\",
                \"ObjectVersion\",
                \"WasPublished\",
                \"WasDraft\",
                \"WasDeleted\",
                \"ObjectHash\",
                \"Modification\",
                \"SnapshotID\",
                \"ObjectID\",
                \"ObjectClass\"
            )
            (
                SELECT
                    \"Created\",
                    \"LastEdited\",
                    \"Version\",
                    \"WasPublished\",
                    \"WasDraft\",
                    \"WasDeleted\",
                    MD5(CONCAT($this->baseClassSubquery, ':', \"RecordID\")),
                    1,
                    \"ID\" + $this->baseID,
                    \"RecordID\",
                    $this->baseClassSubquery
                FROM
                    \"$versionsTable\"
                WHERE
                    \"WasDeleted\" = 0
                ORDER BY \"ID\" ASC
            )
            "
        );

        return DB::affected_rows();
    }

    /**
     * @return void
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    private function createTemporaryTable(): void
    {
        DB::query("DROP TABLE IF EXISTS \"__ClassNameLookup\"");
        DB::create_table(
            '__ClassNameLookup',
            [
                'ObjectClassName' => 'varchar(255) not null',
                'BaseClassName' => 'varchar(255) not null',
            ]
        );

        $classMap = $this->getClassMap();
        $lines = [];

        foreach ($classMap as $className => $baseClassName) {
            $lines[] = sprintf(
                "('%s', '%s')",
                $this->sanitiseClassName($className),
                $this->sanitiseClassName($baseClassName)
            );
        }

        $values = implode(",\n", $lines);
        $query = <<<'SQL'
            INSERT INTO "__ClassNameLookup"
            ("ObjectClassName", "BaseClassName")
            VALUES
            $values
SQL;

        DB::query($query);
    }

    private function removeTemporaryTable(): void
    {
        DB::query("DROP TABLE \"__ClassNameLookup\"");
    }

    /**
     * @return array
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    private function getClassMap(): array
    {
        if ($this->classMap === null) {
            $this->generateClassMap();
        }

        return $this->classMap;
    }

    /**
     * @return void
     * @throws ReflectionException
     * @throws NotFoundExceptionInterface
     */
    private function generateClassMap(): void
    {
        $classes = ClassInfo::subclassesFor(DataObject::class, false);
        $map = [];

        foreach ($classes as $class) {
            $model = singleton($class);

            if (!$model->hasExtension(Versioned::class)) {
                continue;
            }

            $baseClass = $model->baseClass();
            $map[$class] = $baseClass;
        }

        $this->classMap = $map;
    }

    private function sanitiseClassName(string $class): string
    {
        return str_replace('\\', '\\\\', $class);
    }

    public function getBaseID(): int
    {
        return $this->baseID;
    }
}
