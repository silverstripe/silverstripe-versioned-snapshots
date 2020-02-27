<?php


namespace SilverStripe\Snapshots\Migration;


use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Snapshots\SnapshotEvent;
use SilverStripe\Snapshots\SnapshotItem;
use SilverStripe\Versioned\Versioned;
use ReflectionException;

class MigrationService
{
    use Injectable;

    /**
     * @var string
     */
    private $snapshotsTable;

    /**
     * @var string
     */
    private $itemsTable;

    /**
     * @var array|null
     */
    private $classMap;

    /**
     * @var int
     */
    private $baseID = 0;

    /**
     * @var string|null
     */
    private $baseClassSubquery;

    /**
     * MigrationService constructor.
     * @throws ReflectionException
     */
    public function __construct()
    {
        $this->snapshotsTable = DataObject::getSchema()->baseDataTable(Snapshot::class);
        $this->itemsTable = DataObject::getSchema()->baseDataTable(SnapshotItem::class);
    }


    /**
     * @param string $baseClass
     * @return int
     */
    public function migrate(string $baseClass): int
    {
        /* @var DataObject $sng */
        $sng = $baseClass::singleton();
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
     */
    public function seedRelationTracking(): void
    {
        foreach (ClassInfo::subclassesFor(DataObject::class, false) as $class) {
            $tracking = $class::config()->uninherited('snapshot_relation_tracking');
            if (empty($tracking)) {
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
     */
    public function getClassesToMigrate(): array
    {
        return array_unique(array_values($this->getClassMap()));
    }

    /**
     * Restart the task
     */
    public function setup(): void
    {
        DB::query("DELETE FROM \"$this->snapshotsTable\"");
        DB::query("DELETE FROM \"$this->itemsTable\"");
        $eventTable = DataObject::getSchema()->baseDataTable(SnapshotEvent::class);
        DB::query("DELETE FROM \"$eventTable\"");
        $this->createTemporaryTable();
        $this->baseClassSubquery = <<<SQL
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

    /**
     * @param string $versionsTable
     * @return int
     */
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
                ORDER BY \"ID\" ASC
            )
            ");

        return (int) DB::affected_rows();
    }

    /**
     * @param string $versionsTable
     * @return int
     */
    private function migrateItems(string $versionsTable): int
    {
        DB::query(
            "INSERT INTO \"$this->itemsTable\"
            (
                \"Created\",
                \"LastEdited\",
                \"Version\",
                \"WasPublished\",
                \"WasDraft\",
                \"WasDeleted\",
                \"ObjectHash\",
                \"Modification\",
                \"SnapshotID\",
                \"ParentID\",
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
                    0,
                    \"RecordID\",
                    $this->baseClassSubquery
                FROM
                    \"$versionsTable\"
                ORDER BY \"ID\" ASC
            )
            ");

        return (int) DB::affected_rows();
    }

    private function createTemporaryTable()
    {
        DB::query("DROP TABLE IF EXISTS \"__ClassNameLookup\"");
        DB::create_table(
            '__ClassNameLookup',
            [
                'ObjectClassName' => 'varchar(255) not null',
                'BaseClassName' => 'varchar(255) not null',
            ]
        );
        $lines = [];
        foreach ($this->getClassMap() as $className => $baseClassName) {
            $lines[] = sprintf(
                "('%s', '%s')",
                $this->sanitiseClassName($className),
                $this->sanitiseClassName($baseClassName)
            );
        }
        $values = implode(",\n", $lines);
        $query = <<<SQL
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
     */
    private function getClassMap(): array
    {
        if ($this->classMap === null) {
            $this->generateClassMap();
        }

        return $this->classMap;
    }


    private function generateClassMap(): void
    {
        $map = [];
        foreach (ClassInfo::subclassesFor(DataObject::class, false) as $class) {
            $sng = Injector::inst()->get($class);
            if (!$sng->hasExtension(Versioned::class)) {
                continue;
            }

            $baseClass = $sng->baseClass();
            $map[$class] = $baseClass;
        }
        $this->classMap = $map;
    }

    /**
     * @param $class
     * @return string
     */
    private function sanitiseClassName($class): string
    {
        return str_replace('\\', '\\\\', $class);
    }

    public function getBaseID()
    {
        return $this->baseID;
    }
}
